<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per billable thing that happened in a store.
 *
 * `unit_price` is a historical record, not a lookup — it is written once at the
 * moment of the event and never recalculated, so changing a store's pricing
 * tomorrow cannot rewrite what it was charged yesterday.
 */
class StoreUsageEvent extends Model
{
    use HasFactory;

    public const TYPE_ORDER = 'order';

    protected $fillable = [
        'store_id',
        'type',
        'billable_type',
        'billable_id',
        'quantity',
        'unit_price',
        'amount',
        'billing_plan_id',
        'price_source',
        'wallet_transaction_id',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(StoreWalletTransaction::class, 'wallet_transaction_id');
    }
}
