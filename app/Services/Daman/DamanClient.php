<?php

namespace App\Services\Daman;

use App\Models\StoreDamanIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;

/**
 * Talks to Daman's public merchant API.
 *
 * Thin on purpose: Daman already decides which carrier takes a parcel, what it
 * costs and whether the merchant is within their credit limit. Duplicating any
 * of that here would only create a second answer to a question that already has
 * one — this class carries the request there and normalises what comes back.
 */
class DamanClient
{
    /** Daman refuses more than this in one call; we split before it has to. */
    public const MAX_PER_REQUEST = 100;

    /**
     * Is this key real, and which environment does it belong to?
     *
     * A genuine round-trip rather than a format check: a key can be perfectly
     * well-formed and still revoked, and that is the failure a merchant hits —
     * otherwise not until the first parcel silently fails to ship.
     *
     * @return array{ok: bool, message: string}
     */
    public function verify(string $apiKey): array
    {
        try {
            $response = $this->request($apiKey)->get($this->url('governorates'));
        } catch (ConnectionException $e) {
            return ['ok' => false, 'message' => 'مقدرناش نوصل لضمان دلوقتي. جرّب تاني بعد شوية.'];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return ['ok' => false, 'message' => 'المفتاح ده مرفوض من ضمان. اتأكد إنك نسخته كامل ومالغتوش.'];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'ضمان رد بخطأ (' . $response->status() . '). حاول تاني.'];
        }

        return ['ok' => true, 'message' => 'المفتاح شغّال والاتصال بضمان تمام.'];
    }

    /**
     * Hand a batch of shipments over.
     *
     * Returns one result per row, in the order they were sent. Daman answers
     * 207 when only some of a batch was accepted — a rejected governorate on
     * one order must not hold back the twenty next to it — so a partial failure
     * is a normal answer here, not an exception.
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array{ok: bool, shipment?: array<string,mixed>, error?: string}>
     */
    public function createShipments(StoreDamanIntegration $link, array $rows): array
    {
        $body = ['shipments' => array_values($rows)];

        try {
            $response = $this->request($link->api_key)
                // Keyed by the payload itself: a double-clicked "ship" button
                // sends an identical body and gets Daman's original answer
                // replayed instead of a second parcel, while a genuine retry
                // after fixing an address is a different body and so a new
                // request rather than a 422 about a reused key.
                ->withHeader('Idempotency-Key', $this->idempotencyKey($link, $body))
                ->post($this->url('shipments'), $body);
        } catch (ConnectionException $e) {
            return $this->allFailed($rows, 'مقدرناش نوصل لضمان دلوقتي. الطلبات دي ماتبعتتش، جرّب تاني.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return $this->allFailed($rows, 'ضمان رفض المفتاح. راجع إعدادات الربط.');
        }

        $results = $response->json('data.results');

        // Anything that is not the documented envelope — a 500, a proxy error
        // page, a global validation failure — is one failure for the whole
        // batch. Guessing per-row outcomes out of a shape we do not recognise
        // is how orders get marked shipped without a waybill.
        if (! is_array($results)) {
            return $this->allFailed($rows, $this->globalError($response));
        }

        return $this->normalise($rows, $results);
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<int,mixed>  $results
     * @return array<int,array{ok: bool, shipment?: array<string,mixed>, error?: string}>
     */
    private function normalise(array $rows, array $results): array
    {
        $out = [];

        foreach (array_values($rows) as $i => $row) {
            $result = $results[$i] ?? null;

            if (($result['status'] ?? null) === 'created' && isset($result['shipment'])) {
                $out[] = ['ok' => true, 'shipment' => $result['shipment']];

                continue;
            }

            $out[] = ['ok' => false, 'error' => $this->rowError($result)];
        }

        return $out;
    }

    /**
     * Daman returns validation errors keyed by field. Merchants do not read
     * JSON, so we flatten it into the sentence they will actually act on.
     */
    private function rowError(mixed $result): string
    {
        $errors = collect((array) ($result['errors'] ?? []))
            ->flatten()
            ->filter(fn ($line) => is_string($line) && $line !== '')
            ->take(2);

        return $errors->isNotEmpty()
            ? $errors->implode(' · ')
            : 'ضمان رفض الطلب من غير ما يقول السبب.';
    }

    private function globalError(Response $response): string
    {
        $message = $response->json('message');

        return is_string($message) && $message !== ''
            ? $message
            : 'ضمان رد بخطأ (' . $response->status() . ').';
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array{ok: bool, error: string}>
     */
    private function allFailed(array $rows, string $message): array
    {
        return array_fill(0, count($rows), ['ok' => false, 'error' => $message]);
    }

    /** @param array<string,mixed> $body */
    private function idempotencyKey(StoreDamanIntegration $link, array $body): string
    {
        return (string) Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'matgarpro:daman:' . $link->store_id . ':' . hash('sha256', json_encode($body)),
        );
    }

    private function request(string $apiKey): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout(45)
            // Only on a dropped connection. A 4xx is Daman's considered answer
            // and repeating it just makes the merchant wait longer for it.
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.daman.base_url'), '/') . '/' . $path;
    }
}
