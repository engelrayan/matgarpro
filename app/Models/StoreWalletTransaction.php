<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoreWalletTransaction extends Model
{
    use HasFactory;

    public const TYPE_TOPUP = 'topup';

    public const TYPE_ORDER_FEE = 'order_fee';

    public const TYPE_SUBSCRIPTION_FEE = 'subscription_fee';

    public const TYPE_REFUND = 'refund';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'store_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'source_type',
        'source_id',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'meta' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function isCredit(): bool
    {
        return (float) $this->amount > 0;
    }
}
