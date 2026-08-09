<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A half-filled order form.
 *
 * Worth more than a page view and less than an order: the customer chose a
 * product and gave a way to reach them, then stopped.
 */
class AbandonedCart extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'product_id', 'product_variant_id', 'quantity',
        'customer_name', 'customer_phone', 'governorate', 'visitor_id',
        'recovered_order_id', 'recovered_at', 'contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'recovered_at' => 'datetime',
            'contacted_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function recoveredOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'recovered_order_id');
    }

    /** Only a cart with a way to reach the customer is worth showing. */
    public function isReachable(): bool
    {
        return filled($this->customer_phone);
    }

    public function isRecovered(): bool
    {
        return $this->recovered_at !== null;
    }

    /** Estimated value, for sorting the list by what is worth chasing first. */
    public function value(): float
    {
        if (! $this->product) {
            return 0;
        }

        $unit = (float) ($this->variant?->effectivePrice($this->product) ?? $this->product->price);

        return $unit * max(1, $this->quantity);
    }
}
