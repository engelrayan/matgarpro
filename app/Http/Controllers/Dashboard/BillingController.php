<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\StoreTopupRequest;
use App\Models\StoreWalletTransaction;
use App\Services\Billing\TopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "رصيدك": the merchant's balance, what an order costs them, and how to add
 * money — pick an amount, transfer it by InstaPay, send the receipt on
 * WhatsApp. An operator confirms; nothing here moves the balance.
 */
class BillingController extends Controller
{
    public function __construct(private readonly TopupService $topups) {}

    public function index(Request $request): Response
    {
        $store = $request->user()->currentStore()->loadMissing('plan');

        return Inertia::render('billing/Index', [
            'currency' => $store->currency,
            'balance' => (float) $store->balance,
            'overdraftLimit' => (float) config('billing.overdraft_limit'),
            // Read from the store rather than worked out on the page: a free
            // plan never hides anything, whatever the balance says.
            'hidesNewOrders' => $store->hidesNewOrders(),
            'locked' => $store->lockedOrdersSummary(),
            'prices' => [
                'in_intro' => $store->inIntroPeriod(),
                'intro_days_left' => $store->introDaysLeft(),
                'intro_ends_at' => $store->trial_ends_at?->translatedFormat('j F Y'),
                'order' => $store->pricePerOrder(),
                'with_confirmation' => $store->pricePerOrder() + $store->confirmationPrice(),
                'order_after' => $store->basePricePerOrder(),
                'with_confirmation_after' => $store->basePricePerOrder() + $store->confirmationPrice(),
            ],
            'amounts' => TopupService::amounts(),
            'instapay' => TopupService::instapayNumber(),
            'pending' => $this->topups->pending($store),
            'requests' => $store->topupRequests()->latest('id')->limit(10)->get()
                ->map(fn (StoreTopupRequest $r) => [
                    'id' => $r->id,
                    'amount' => (float) ($r->confirmed_amount ?? $r->amount),
                    'status' => $r->status,
                    'status_label' => StoreTopupRequest::statusLabel($r->status),
                    'note' => $r->review_note,
                    'created_at' => $r->created_at->format('Y-m-d'),
                ]),
            'transactions' => $store->walletTransactions()->latest('id')->limit(15)->get()
                ->map(fn (StoreWalletTransaction $t) => [
                    'id' => $t->id,
                    'description' => $t->description,
                    'amount' => (float) $t->amount,
                    'balance_after' => (float) $t->balance_after,
                    'created_at' => $t->created_at->format('Y-m-d'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['amount' => ['required', 'integer']], [
            'amount.required' => 'اختار المبلغ اللي هتشحن بيه.',
        ]);

        $this->topups->request($request->user()->currentStore(), $request->user(), (int) $validated['amount']);

        return back()->with('status', 'topup-requested');
    }

    public function destroy(Request $request, StoreTopupRequest $topup): RedirectResponse
    {
        // 404, not 403: another store's request is none of this merchant's business.
        abort_unless((int) $topup->store_id === (int) $request->user()->currentStore()->id, 404);

        $this->topups->cancel($topup);

        return back()->with('status', 'topup-cancelled');
    }
}
