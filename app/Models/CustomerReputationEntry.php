<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One order's contribution to a phone number's record.
 *
 * Exists so the aggregate can be rebuilt from facts rather than incremented
 * blindly: a merchant who marks an order delivered, then corrects it to
 * returned, must move the number by one — not add two.
 */
class CustomerReputationEntry extends Model
{
    use HasFactory;

    public const OUTCOME_DELIVERED = 'delivered';

    public const OUTCOME_REFUSED = 'refused';

    public const OUTCOME_PENDING = 'pending';

    protected $fillable = [
        'customer_reputation_id', 'store_id', 'order_id', 'outcome', 'settled_at',
    ];

    protected function casts(): array
    {
        return ['settled_at' => 'datetime'];
    }

    public function reputation(): BelongsTo
    {
        return $this->belongsTo(CustomerReputation::class, 'customer_reputation_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
