<?php

namespace App\Services\Pixels;

use App\Models\StorePixel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts server-side events to Meta's Conversions API.
 *
 * Server-side exists because the browser pixel is unreliable by design now:
 * ad blockers drop it, Safari caps the cookies it relies on, and iOS strips
 * referrers. The same event sent from here arrives regardless — and carries
 * `event_id`, which is what lets Meta recognise the browser copy and the
 * server copy as one conversion instead of two.
 */
class MetaConversionsApi
{
    /**
     * @param  array<string,mixed>  $event
     * @return array{ok: bool, error: ?string, retryable: bool}
     */
    public function send(StorePixel $pixel, array $event): array
    {
        $config = config('pixels.meta');
        $url = "{$config['endpoint']}/{$config['version']}/{$pixel->pixel_id}/events";

        $payload = ['data' => [$event]];

        // Present only while the merchant is verifying setup in Events Manager;
        // events carrying it are excluded from real conversion reporting.
        if ($pixel->test_event_code) {
            $payload['test_event_code'] = $pixel->test_event_code;
        }

        try {
            $response = Http::timeout($config['timeout'])
                ->asJson()
                // The token goes in the body, not the query string: query
                // strings end up in access logs and proxy caches.
                ->post($url, [...$payload, 'access_token' => $pixel->access_token]);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => 'تعذّر الاتصال بسيرفرات ميتا.', 'retryable' => true];
        }

        if ($response->successful()) {
            return ['ok' => true, 'error' => null, 'retryable' => false];
        }

        $error = $response->json('error.message') ?? 'خطأ غير معروف من ميتا.';
        $code = (int) ($response->json('error.code') ?? 0);

        /*
         | 190 = token expired or revoked, 100 = malformed request. Retrying
         | either just burns worker time and republishes the same failure; a
         | 5xx or a 429 is worth another attempt.
         */
        $retryable = ! in_array($code, [100, 190], true)
            && ($response->serverError() || $response->status() === 429);

        Log::warning('Meta CAPI event rejected', [
            'pixel_id' => $pixel->pixel_id,
            'status' => $response->status(),
            'code' => $code,
            'message' => $error,
        ]);

        return ['ok' => false, 'error' => mb_substr($error, 0, 500), 'retryable' => $retryable];
    }

    /**
     * Build a Purchase event.
     *
     * `event_id` must be byte-identical to the one the browser pixel fires,
     * or Meta counts the sale twice and the merchant optimises against
     * inflated numbers.
     *
     * @param  array<string,mixed>  $userData
     * @param  array<int,array<string,mixed>>  $contents
     * @return array<string,mixed>
     */
    public function purchaseEvent(
        string $eventId,
        int $eventTime,
        string $sourceUrl,
        float $value,
        string $currency,
        array $userData,
        array $contents,
    ): array {
        return [
            'event_name' => 'Purchase',
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'event_source_url' => $sourceUrl,
            'action_source' => 'website',
            'user_data' => $userData,
            'custom_data' => [
                'currency' => $currency,
                'value' => round($value, 2),
                'contents' => $contents,
                'content_type' => 'product',
                'num_items' => array_sum(array_column($contents, 'quantity')),
            ],
        ];
    }
}
