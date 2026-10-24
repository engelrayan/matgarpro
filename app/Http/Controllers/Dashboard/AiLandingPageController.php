<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\RunsAiGenerations;
use App\Jobs\RunAiGeneration;
use App\Models\AiGeneration;
use App\Models\Store;
use App\Models\StoreWalletTransaction;
use App\Services\AI\AiFeature;
use App\Services\AI\LandingPage\LandingPlanApplier;
use App\Services\Billing\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "اكتبلي صفحة الهبوط" — from inside the builder, on a product's landing page.
 *
 * Start (store), wait (show, polled), and put it in (apply): the plan lands in
 * the landing page's *draft*, so the builder's own preview is the review
 * screen and the live page does not change until the merchant presses نشر.
 */
class AiLandingPageController extends Controller
{
    use RunsAiGenerations;

    public function store(Request $request): JsonResponse
    {
        $store = $request->user()->currentStore();

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:800'],
        ], [
            'notes.max' => 'الكلام كتير شوية — اكتب أهم حاجات في كام سطر.',
        ]);

        // One of the merchant's own products, or nothing.
        $product = $store->products()->findOrFail($data['product_id']);

        // Asked before the call, not after: writing a page the merchant then
        // cannot pay for is a call we paid for and they never see.
        if (! $this->canAfford($store)) {
            return response()->json([
                'message' => 'رصيدك مش كفاية — صفحة الهبوط بـ ' . $this->price() . ' جنيه. اشحن رصيدك وجرّب تاني.',
            ], 402);
        }

        if ($refusal = $this->refuseToStart($store, AiFeature::LandingPage, 'لسه بنكتب الصفحة. استنى ثواني.')) {
            return $refusal;
        }

        $generation = AiGeneration::create([
            'store_id' => $store->id,
            'user_id' => $request->user()->id,
            'feature' => AiFeature::LandingPage->value,
            'status' => AiGeneration::STATUS_PENDING,
            'input' => [
                'product_id' => $product->id,
                'notes' => trim((string) ($data['notes'] ?? '')),
            ],
        ]);

        RunAiGeneration::dispatch($generation->id);

        return response()->json($this->presentGeneration($generation->refresh()), 202);
    }

    public function show(Request $request, AiGeneration $generation): JsonResponse
    {
        return response()->json($this->presentGeneration(
            $this->ownedGeneration($request, $generation, AiFeature::LandingPage),
        ));
    }

    /**
     * Into the builder draft — and, the first time, onto the bill.
     *
     * Charged here rather than when the job finishes: this is the moment the
     * merchant receives the page. Charged once per generation however many
     * times it is applied, so a double click or a retried request is never a
     * second charge.
     */
    public function apply(Request $request, AiGeneration $generation, LandingPlanApplier $applier, WalletService $wallet): JsonResponse
    {
        $generation = $this->ownedGeneration($request, $generation, AiFeature::LandingPage);

        abort_unless($generation->status === AiGeneration::STATUS_DONE, 404);

        $store = $request->user()->currentStore();
        $product = $store->products()->findOrFail((int) ($generation->input['product_id'] ?? 0));

        DB::transaction(function () use ($store, $product, $generation, $applier, $wallet) {
            $applier->apply($store, $product, (array) $generation->result);

            // Re-read under lock: two applies racing must not both see "unpaid".
            $locked = AiGeneration::whereKey($generation->id)->lockForUpdate()->first();

            if (filled($locked->result['charged_at'] ?? null) || $this->price() <= 0) {
                return;
            }

            $wallet->debit(
                $store,
                $this->price(),
                StoreWalletTransaction::TYPE_AI_FEE,
                "صفحة هبوط بالذكاء الاصطناعي — {$product->name}",
                // The product, not the generation: the ledger's source is an
                // integer id and generations are UUIDs, which ride in meta.
                $product,
                meta: ['ai_generation_id' => $locked->id],
            );

            $locked->update(['result' => [...$locked->result, 'charged_at' => now()->toIso8601String()]]);
        });

        return response()->json(['ok' => true, 'heads_up' => $generation->result['heads_up'] ?? '']);
    }

    private function price(): float
    {
        return (float) config('billing.ai_landing_page_price');
    }

    /** Same floor as orders: the wallet may dip into the overdraft, no further. */
    private function canAfford(Store $store): bool
    {
        return $this->price() <= 0
            || (float) $store->balance - $this->price() >= -1 * (float) config('billing.overdraft_limit');
    }
}
