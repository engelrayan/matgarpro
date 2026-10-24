<?php

namespace App\Services\AI;

use App\Models\AiCall;
use App\Models\Store;

/**
 * What AI has cost a store, and whether it may spend more today.
 *
 * Two limits, for two audiences:
 *
 *  - a per-feature daily count, which a merchant can understand ("20 tries a
 *    day") and the UI can show;
 *  - a per-store daily spend in dollars, which nobody sees, and which exists
 *    so that one store hammering every feature at once costs us a known,
 *    small amount rather than whatever a loop can run up overnight.
 *
 * Neither is a lock: two requests racing past the last slot can both run. The
 * overshoot is one call per concurrent worker, which is cents, and taking a
 * row lock on every AI call to prevent it would cost more than it saves.
 */
class AiUsageMeter
{
    /** @throws AiException when the store has used up today's allowance. */
    public function ensureAllowed(Store $store, AiFeature $feature): void
    {
        if ($this->remaining($store, $feature) <= 0) {
            throw $feature->totalLimit() === null ? AiException::dailyLimit() : AiException::usedUp();
        }

        $cap = (float) config('ai.daily_cost_cap_usd');

        if ($this->spentToday($store) >= $cap) {
            throw AiException::dailyLimit();
        }
    }

    /**
     * Tries left today for this feature.
     *
     * Failed calls do not count: an outage on our side must not eat the
     * merchant's allowance. Refused and truncated ones do — Anthropic bills
     * them, and retrying the same thing in a loop is exactly what a limit is for.
     */
    public function remainingToday(Store $store, AiFeature $feature): int
    {
        return max(0, $feature->dailyLimit() - $this->used($store, $feature, today()));
    }

    /**
     * Tries left, on whichever limit the feature has: its lifetime one when
     * it has one, today's otherwise. The same rule about failed calls holds.
     */
    public function remaining(Store $store, AiFeature $feature): int
    {
        $total = $feature->totalLimit();

        return $total === null
            ? $this->remainingToday($store, $feature)
            : max(0, $total - $this->used($store, $feature));
    }

    private function used(Store $store, AiFeature $feature, ?\DateTimeInterface $since = null): int
    {
        return AiCall::where('store_id', $store->id)
            ->where('feature', $feature->value)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since))
            ->where('status', '!=', AiCall::STATUS_FAILED)
            ->count();
    }

    public function spentToday(Store $store): float
    {
        return (float) AiCall::where('store_id', $store->id)
            ->where('created_at', '>=', today())
            ->sum('cost_usd');
    }

    /**
     * Estimated dollars for one call.
     *
     * `input_tokens` from the API already excludes cached tokens, which are
     * reported — and priced — separately.
     */
    public function estimateCost(string $model, int $input, int $output, int $cacheWrite = 0, int $cacheRead = 0): float
    {
        $price = $this->priceFor($model);

        return round((
            $input * $price['input']
            + $output * $price['output']
            + $cacheWrite * $price['cache_write']
            + $cacheRead * $price['cache_read']
        ) / 1_000_000, 6);
    }

    /**
     * A model missing from config is priced as the most expensive one listed:
     * over-counting is a nuisance, under-counting is how a cap stops capping.
     *
     * @return array{input:float,output:float,cache_write:float,cache_read:float}
     */
    private function priceFor(string $model): array
    {
        $table = (array) config('ai.pricing');

        // The API may answer with a dated snapshot of the id we asked for.
        foreach ($table as $known => $price) {
            if ($model === $known || str_starts_with($model, $known . '-')) {
                return $price;
            }
        }

        return collect($table)->sortByDesc('output')->first()
            ?? ['input' => 0.0, 'output' => 0.0, 'cache_write' => 0.0, 'cache_read' => 0.0];
    }
}
