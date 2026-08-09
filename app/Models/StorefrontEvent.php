<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of the storefront funnel.
 *
 * Deliberately append-only and timestamp-only (`created_at`, no `updated_at`):
 * an event is something that happened, not a record that gets corrected.
 */
class StorefrontEvent extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const TYPE_VIEW = 'view';

    public const TYPE_CHECKOUT_START = 'checkout_start';

    public const TYPE_ORDER = 'order';

    protected $fillable = [
        'store_id', 'product_id', 'type', 'visitor_id', 'user_agent', 'referrer',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
