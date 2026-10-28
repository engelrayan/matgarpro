<?php

namespace App\Services\Billing;

use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A merchant topping up their balance: they ask, an operator confirms.
 *
 * The only way a top-up request touches money. Confirming is idempotent at
 * the row: the request is locked, re-checked as pending, and only then credited
 * — so two operators pressing "confirm" at once, or one pressing it twice,
 * credit the store once.
 *
 * When online payment arrives, a gateway callback confirms the same rows the
 * same way; nothing on the merchant's side changes.
 */
class TopupService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly AuditLogger $audit,
        private readonly OrderUnlocker $unlocker,
    ) {}

    /** @return array<int,int> */
    public static function amounts(): array
    {
        return array_map('intval', (array) config('billing.topup.amounts'));
    }

    /**
     * One open request per store. A second one would be a second transfer the
     * operator has to match against the same bank statement — more often a
     * double-tap than a merchant really sending twice.
     *
     * @throws ValidationException
     */
    public function request(Store $store, ?User $user, int $amount): StoreTopupRequest
    {
        if (! in_array($amount, self::amounts(), true)) {
            throw ValidationException::withMessages(['amount' => 'اختار مبلغ من المبالغ اللي قدامك.']);
        }

        return DB::transaction(function () use ($store, $user, $amount) {
            Store::whereKey($store->id)->lockForUpdate()->first();

            if ($store->topupRequests()->where('status', StoreTopupRequest::STATUS_PENDING)->exists()) {
                throw ValidationException::withMessages([
                    'amount' => 'عندك طلب شحن لسه مستني التأكيد. استنى لما يتأكد أو الغيه الأول.',
                ]);
            }

            return $store->topupRequests()->create([
                'user_id' => $user?->id,
                'amount' => $amount,
                'method' => StoreTopupRequest::METHOD_INSTAPAY,
                'status' => StoreTopupRequest::STATUS_PENDING,
            ]);
        });
    }

    public static function instapayNumber(): string
    {
        return (string) config('billing.topup.instapay_number');
    }

    /**
     * The store's open request as the merchant's screens show it — رصيدك and
     * the dashboard both — with the WhatsApp link that carries the receipt.
     *
     * @return array{id: int, amount: float, created_at: string, whatsapp_url: ?string}|null
     */
    public function pending(Store $store): ?array
    {
        $pending = $store->topupRequests()->where('status', StoreTopupRequest::STATUS_PENDING)->latest('id')->first();

        return $pending ? [
            'id' => $pending->id,
            'amount' => (float) $pending->amount,
            'created_at' => $pending->created_at->translatedFormat('j F Y — g:i a'),
            'whatsapp_url' => $this->receiptLink($store, $pending),
        ] : null;
    }

    /**
     * WhatsApp to the InstaPay number, with a message that already says which
     * store and which request — so the operator can match the receipt to the
     * row without asking.
     */
    private function receiptLink(Store $store, StoreTopupRequest $request): ?string
    {
        $digits = preg_replace('/\D/', '', self::instapayNumber());

        if ($digits === '') {
            return null;
        }

        $international = str_starts_with($digits, '0') ? '2' . $digits : $digits;
        $amount = number_format((float) $request->amount);
        $text = "إيصال تحويل لشحن رصيد متجر «{$store->name}» ({$store->slug}) — المبلغ {$amount} جنيه — طلب رقم {$request->id}";

        return "https://wa.me/{$international}?text=" . rawurlencode($text);
    }

    public function cancel(StoreTopupRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $locked = StoreTopupRequest::whereKey($request->id)->lockForUpdate()->first();

            if ($locked?->isPending()) {
                $locked->update(['status' => StoreTopupRequest::STATUS_CANCELLED]);
            }
        });
    }

    /**
     * Credit what actually arrived — which may differ from what was picked.
     *
     * @throws ValidationException when the request is no longer pending
     */
    public function confirm(StoreTopupRequest $request, Admin $admin, float $amount): StoreTopupRequest
    {
        $request = DB::transaction(function () use ($request, $admin, $amount) {
            $locked = StoreTopupRequest::with('store')->whereKey($request->id)->lockForUpdate()->firstOrFail();

            $this->ensurePending($locked);

            $transaction = $this->wallet->credit(
                store: $locked->store,
                amount: $amount,
                type: StoreWalletTransaction::TYPE_TOPUP,
                description: "شحن رصيد عبر إنستاباي — طلب #{$locked->id}",
                source: $locked,
                meta: ['admin_id' => $admin->id, 'admin_name' => $admin->name, 'requested' => (float) $locked->amount],
            );

            $locked->update([
                'status' => StoreTopupRequest::STATUS_CONFIRMED,
                'confirmed_amount' => $amount,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'wallet_transaction_id' => $transaction->id,
            ]);

            return $locked;
        });

        $edited = abs($amount - (float) $request->amount) >= 0.01
            ? " (الطلب كان {$request->amount})"
            : '';

        $this->audit->log(
            action: 'store.topup_confirmed',
            summary: "أكّد شحن رصيد متجر «{$request->store->name}» بمبلغ {$amount}{$edited}",
            subject: $request->store,
            changes: ['balance' => ['from' => (float) $request->store->refresh()->balance - $amount, 'to' => (float) $request->store->balance]],
            actor: $admin,
        );

        // The point of topping up: the orders that arrived hidden open now.
        $this->unlocker->unlock($request->store);

        return $request;
    }

    /** @throws ValidationException when the request is no longer pending */
    public function reject(StoreTopupRequest $request, Admin $admin, string $note): StoreTopupRequest
    {
        $request = DB::transaction(function () use ($request, $admin, $note) {
            $locked = StoreTopupRequest::with('store')->whereKey($request->id)->lockForUpdate()->firstOrFail();

            $this->ensurePending($locked);

            $locked->update([
                'status' => StoreTopupRequest::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            return $locked;
        });

        $this->audit->log(
            action: 'store.topup_rejected',
            summary: "رفض طلب شحن رصيد متجر «{$request->store->name}» بمبلغ {$request->amount} — {$note}",
            subject: $request->store,
            actor: $admin,
        );

        return $request;
    }

    private function ensurePending(StoreTopupRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'topup' => 'الطلب ده اتراجع قبل كده أو التاجر لغاه.',
            ]);
        }
    }
}
