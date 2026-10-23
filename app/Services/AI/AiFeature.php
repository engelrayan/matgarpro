<?php

namespace App\Services\AI;

/**
 * Every thing the platform asks Claude to do.
 *
 * The value is what gets written to ai_calls.feature and the key into
 * config('ai.features'), so a new feature is a case here plus a config block —
 * the gateway, the meter and the daily cap pick it up from there.
 */
enum AiFeature: string
{
    case ProductWriter = 'product_writer';

    case StoreBuilder = 'store_builder';

    /**
     * What writes this feature's draft, for features the merchant waits on
     * screen for. Null for features that run inside other jobs.
     */
    public function generator(): ?GeneratesDraft
    {
        return match ($this) {
            self::ProductWriter => app(ProductWriter\ProductDraftWriter::class),
            self::StoreBuilder => app(StoreBuilder\StorePlanWriter::class),
        };
    }

    /** @return array<string,mixed> */
    public function config(): array
    {
        return (array) config("ai.features.{$this->value}");
    }

    public function model(): string
    {
        return (string) $this->config()['model'];
    }

    public function effort(): ?string
    {
        return $this->config()['effort'] ?? null;
    }

    public function maxTokens(): int
    {
        return (int) ($this->config()['max_tokens'] ?? 4000);
    }

    public function dailyLimit(): int
    {
        return (int) ($this->config()['daily_limit'] ?? 0);
    }

    public function usesFallbacks(): bool
    {
        return (bool) ($this->config()['fallbacks'] ?? false);
    }
}
