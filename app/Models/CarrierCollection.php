<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A carrier paying the merchant for delivered, cash-on-delivery orders.
 * See the carrier_collections migration, and CarrierLedger for the totals.
 */
class CarrierCollection extends Model
{
    protected $fillable = [
        'store_id', 'user_id', 'carrier', 'amount', 'expected_amount',
        'orders_count', 'received_on', 'reference', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'received_on' => 'date',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** What the carrier kept, or fell short by: expected minus received. */
    public function difference(): float
    {
        return round((float) $this->expected_amount - (float) $this->amount, 2);
    }
}
