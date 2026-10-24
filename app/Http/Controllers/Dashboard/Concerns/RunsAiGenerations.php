<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Models\AiGeneration;
use App\Models\Store;
use App\Services\AI\AiException;
use App\Services\AI\AiFeature;
use App\Services\AI\AiUsageMeter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The start / poll half every on-screen AI feature shares.
 *
 * Kept in one place so each feature's controller only says what is new about
 * it — what it validates and what it stores as input — and the rules about
 * who may see a generation, how many may run, and when one expires cannot
 * drift apart between features.
 */
trait RunsAiGenerations
{
    /**
     * Why this store may not start one now, as a 429 — or null when it may.
     */
    protected function refuseToStart(Store $store, AiFeature $feature, string $busyMessage): ?JsonResponse
    {
        try {
            app(AiUsageMeter::class)->ensureAllowed($store, $feature);
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        /*
         | One at a time per store and feature. The button is disabled while
         | one runs, so a second only comes from a double-click or a second
         | tab — and each would be a paid call for the same thing.
         */
        $busy = AiGeneration::where('store_id', $store->id)
            ->where('feature', $feature->value)
            ->whereIn('status', [AiGeneration::STATUS_PENDING, AiGeneration::STATUS_RUNNING])
            ->where('created_at', '>=', now()->subMinutes(AiGeneration::EXPIRES_AFTER_MINUTES))
            ->exists();

        return $busy ? response()->json(['message' => $busyMessage], 429) : null;
    }

    /**
     * The generation, if it belongs to the merchant's current store and this
     * feature; a 404 otherwise — whether somebody else's draft exists is none
     * of the asker's business.
     */
    protected function ownedGeneration(Request $request, AiGeneration $generation, AiFeature $feature): AiGeneration
    {
        abort_unless(
            (int) $generation->store_id === (int) $request->user()->currentStore()->id
                && $generation->feature === $feature->value,
            404,
        );

        if ($generation->hasExpired()) {
            $generation->markFailed('الموضوع أخد وقت أكتر من اللازم. جرّب تاني.');
            $generation->deleteInputs();
        }

        return $generation;
    }

    /** @return array<string,mixed> */
    protected function presentGeneration(AiGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'status' => $generation->status,
            'draft' => $generation->status === AiGeneration::STATUS_DONE ? $generation->result : null,
            'error' => $generation->status === AiGeneration::STATUS_FAILED ? $generation->error : null,
        ];
    }
}
