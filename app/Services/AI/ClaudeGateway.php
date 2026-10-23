<?php

namespace App\Services\AI;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\RequestOptions;
use App\Models\AiCall;
use App\Models\Store;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The one door to Claude.
 *
 * Every feature — the product writer today; reply classification, address
 * cleanup, the copilot later — calls Claude through here and nowhere else.
 * That is what makes three promises hold without each feature re-making them:
 *
 *  1. Every call is metered: one ai_calls row with tokens and estimated cost,
 *     whether it succeeded, was refused, or never got an answer.
 *  2. Every call is capped: the store's daily allowance is checked before a
 *     request goes out, not after the money is spent.
 *  3. Every failure reaches the caller as an AiException whose message can be
 *     shown to a merchant, so no feature leaks an API error onto a screen.
 *
 * Calls are blocking and slow. Callers run inside queued jobs, never inside a
 * web request.
 */
class ClaudeGateway
{
    /** Opt-in header for `fallbacks: "default"`. */
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(private readonly AiUsageMeter $meter) {}

    /**
     * Ask for an answer shaped by a JSON schema, and get it back decoded.
     *
     * Structured outputs constrain the model to the schema, so a well-formed
     * answer is guaranteed — but not a sensible one. Lengths, ownership of ids
     * and anything that becomes HTML are still the caller's job to check.
     *
     * @param  array<int,array<string,mixed>>  $content  user-turn content blocks (text, images)
     * @param  array<string,mixed>  $schema
     * @return array<string,mixed>
     *
     * @throws AiException
     */
    public function json(
        Store $store,
        AiFeature $feature,
        string $system,
        array $content,
        array $schema,
        ?Model $subject = null,
    ): array {
        $message = $this->send($store, $feature, [
            // Cached: the instructions are identical on every call of a
            // feature, and a cache read costs a tenth of fresh input. Anything
            // that varies per store goes in the user turn, after this.
            'system' => [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]],
            'messages' => [['role' => 'user', 'content' => $content]],
            'outputConfig' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        ], $subject);

        if ($message->stopReason === 'refusal') {
            throw AiException::refused();
        }

        // Cut off mid-JSON. Never try to repair it: half an answer parsed
        // leniently is a draft with a description that stops mid-sentence.
        if ($message->stopReason === 'max_tokens') {
            throw AiException::failed();
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $decoded = json_decode($block->text, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        Log::warning('AI answer was not the JSON its schema promised.', [
            'feature' => $feature->value,
            'request_id' => $message->id,
        ]);

        throw AiException::failed();
    }

    /**
     * One metered request, with the caller's own parameters.
     *
     * The lower-level door, for features `json()` does not fit — a copilot
     * with tools needs the raw message back. The feature's config supplies
     * model, max_tokens and effort unless the caller overrides them.
     *
     * @param  array<string,mixed>  $params  named arguments for beta->messages->create()
     *
     * @throws AiException
     */
    public function send(Store $store, AiFeature $feature, array $params, ?Model $subject = null): BetaMessage
    {
        $this->meter->ensureAllowed($store, $feature);

        $client = $this->client();
        $params = $this->withFeatureDefaults($feature, $params);
        $started = hrtime(true);

        try {
            $message = $client->beta->messages->create(...$params);
        } catch (APIConnectionException|RateLimitException|InternalServerException $e) {
            // Busy, overloaded (529), or unreachable. Nothing was billed; the
            // job retries after a pause.
            $this->recordFailure($store, $feature, $subject, $params['model'], $started, $e);

            throw AiException::busy($e);
        } catch (AnthropicException $e) {
            // A request we built wrong, or a key that stopped working. Retrying
            // will not change the answer, and somebody needs to know.
            $this->recordFailure($store, $feature, $subject, $params['model'], $started, $e);
            report($e);

            throw AiException::failed($e);
        }

        $this->recordSuccess($store, $feature, $subject, $message, $started);

        return $message;
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private function withFeatureDefaults(AiFeature $feature, array $params): array
    {
        $params += [
            'model' => $feature->model(),
            'maxTokens' => $feature->maxTokens(),
        ];

        if ($feature->effort() !== null) {
            $params['outputConfig'] = ($params['outputConfig'] ?? []) + ['effort' => $feature->effort()];
        }

        /*
         | When the model declines on policy grounds, let the API hand the same
         | request to the fallback it picks for that kind of refusal, inside
         | the same call. Without it a false positive on a perfectly ordinary
         | product photo would just be a failed draft.
         */
        if ($feature->usesFallbacks()) {
            $params['fallbacks'] = 'default';
            $params['betas'] = array_values(array_unique([...($params['betas'] ?? []), self::FALLBACK_BETA]));
        }

        return $params;
    }

    private function client(): Client
    {
        $key = (string) config('services.anthropic.key');

        if ($key === '') {
            throw AiException::notConfigured();
        }

        return new Client(
            apiKey: $key,
            baseUrl: config('services.anthropic.base_url'),
            requestOptions: RequestOptions::with(
                timeout: (float) config('ai.timeout'),
                // See config/ai.php: the job retries, not the request.
                maxRetries: 0,
                transporter: $this->transport(),
            ),
        );
    }

    /**
     * Laravel's own HTTP stack, handed to the SDK as its PSR-18 client.
     *
     * Two reasons. Http::fake() and Http::preventStrayRequests() see every
     * Claude call, so a test can never reach the real API by accident. And the
     * timeout is enforced here — the SDK leaves enforcement to its transport.
     */
    private function transport(): GuzzleClient
    {
        return new GuzzleClient([
            'handler' => Http::buildHandlerStack(),
            'timeout' => (float) config('ai.timeout'),
            'connect_timeout' => 10,
            // Laravel's recorder and fake middleware read these on every
            // request; PendingRequest normally supplies them.
            'laravel_data' => [],
            'on_stats' => static fn () => null,
        ]);
    }

    private function recordSuccess(Store $store, AiFeature $feature, ?Model $subject, BetaMessage $message, int $started): void
    {
        $usage = $message->usage;
        $input = $usage->inputTokens;
        $output = $usage->outputTokens;
        $cacheWrite = (int) $usage->cacheCreationInputTokens;
        $cacheRead = (int) $usage->cacheReadInputTokens;

        $this->record($store, $feature, $subject, [
            'model' => $message->model,
            'status' => match ($message->stopReason) {
                'refusal' => AiCall::STATUS_REFUSED,
                'max_tokens' => AiCall::STATUS_INCOMPLETE,
                default => AiCall::STATUS_OK,
            },
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cache_write_tokens' => $cacheWrite,
            'cache_read_tokens' => $cacheRead,
            'cost_usd' => $this->meter->estimateCost($message->model, $input, $output, $cacheWrite, $cacheRead),
            'duration_ms' => $this->elapsedMs($started),
            'request_id' => $message->id,
        ]);
    }

    private function recordFailure(Store $store, AiFeature $feature, ?Model $subject, string $model, int $started, \Throwable $e): void
    {
        Log::warning('AI call failed.', ['feature' => $feature->value, 'store_id' => $store->id, 'error' => $e->getMessage()]);

        $this->record($store, $feature, $subject, [
            'model' => $model,
            'status' => AiCall::STATUS_FAILED,
            'duration_ms' => $this->elapsedMs($started),
            'error' => Str::limit(class_basename($e) . ': ' . $e->getMessage(), 490),
        ]);
    }

    /** @param array<string,mixed> $attributes */
    private function record(Store $store, AiFeature $feature, ?Model $subject, array $attributes): void
    {
        AiCall::create($attributes + [
            'store_id' => $store->id,
            'feature' => $feature->value,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);
    }

    private function elapsedMs(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
