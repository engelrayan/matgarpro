<?php

namespace App\Jobs;

use App\Models\AiGeneration;
use App\Services\AI\AiException;
use App\Services\AI\ProductWriter\ProductDraftWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Writes one product draft while the merchant's form polls for it.
 *
 * Queued, on the `ai` queue, because a Claude call with photos takes long
 * enough that doing it inside the request would hold a PHP worker for most of
 * a minute — and the dashboard has few of those to spare.
 */
class GenerateProductDraft implements ShouldQueue
{
    use Queueable;

    /** One retry, for a busy API. Anything else will not fix itself. */
    public int $tries = 2;

    /**
     * Inside the queue's retry_after (90s), with room to spare. A job still
     * running past retry_after is handed to a second worker, which would pay
     * for the same draft again.
     */
    public int $timeout = 85;

    public function __construct(public readonly string $generationId)
    {
        $this->onQueue(config('ai.queue'));
    }

    public function backoff(): int
    {
        return 15;
    }

    public function handle(ProductDraftWriter $writer): void
    {
        $generation = AiGeneration::with('store')->find($this->generationId);

        // Gone, or already answered by an earlier attempt.
        if (! $generation || $generation->isFinished() || ! $generation->store) {
            return;
        }

        $generation->markRunning();

        try {
            $draft = $writer->write(
                $generation->store,
                $generation->input['notes'] ?? null,
                $this->images($generation),
                $generation,
            );
        } catch (AiException $e) {
            // Busy: leave the generation running so the browser keeps waiting,
            // and try once more. The merchant sees one slow answer instead of
            // an error they would have to retry by hand.
            if ($e->retryable && $this->attempts() < $this->tries) {
                $this->release($this->backoff());

                return;
            }

            $this->finish($generation, fn () => $generation->markFailed($e->getMessage()));

            return;
        }

        $this->finish($generation, fn () => $generation->markDone($draft));
    }

    /** Killed by the timeout, or an exception nobody expected. */
    public function failed(?Throwable $e): void
    {
        $generation = AiGeneration::find($this->generationId);

        if ($generation && ! $generation->isFinished()) {
            $this->finish($generation, fn () => $generation->markFailed(AiException::failed()->getMessage()));
        }
    }

    /** The photos go as soon as there is an answer — they are unpublished. */
    private function finish(AiGeneration $generation, callable $mark): void
    {
        $mark();
        $generation->deleteInputs();
    }

    /** @return array<int,array{bytes:string,media_type:string}> */
    private function images(AiGeneration $generation): array
    {
        $disk = Storage::disk('local');

        return collect($generation->input['images'] ?? [])
            ->filter(fn ($image) => $disk->exists($image['path']))
            ->map(fn ($image) => ['bytes' => $disk->get($image['path']), 'media_type' => $image['media_type']])
            ->values()
            ->all();
    }
}
