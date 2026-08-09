<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What the platform knows about how a phone number receives parcels.
 *
 * Read on every checkout and on every order row, so it is a maintained
 * aggregate rather than a query across all orders — the alternative is a
 * full-table scan on the busiest page in the product.
 */
class CustomerReputation extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone', 'delivered', 'refused', 'pending', 'stores_count',
        'first_seen_at', 'last_outcome_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered' => 'integer',
            'refused' => 'integer',
            'pending' => 'integer',
            'stores_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_outcome_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CustomerReputationEntry::class);
    }

    /** Parcels that actually reached a conclusion. */
    public function settled(): int
    {
        return $this->delivered + $this->refused;
    }

    /**
     * Delivery rate, or null when nothing has settled yet.
     *
     * Null rather than zero on purpose: a first-time buyer with no history is
     * not a 0% customer, and showing them as one would have merchants refusing
     * perfectly good orders.
     */
    public function deliveryRate(): ?int
    {
        return $this->settled() > 0
            ? (int) round($this->delivered / $this->settled() * 100)
            : null;
    }

    /**
     * Is there enough here to warn a merchant?
     *
     * Two refusals, not one. A single refusal is as likely to be a wrong
     * address or a courier that never called as it is a bad customer, and a
     * warning that fires on noise is a warning merchants learn to ignore.
     */
    public function isRisky(): bool
    {
        return $this->refused >= (int) config('reputation.risky_refusals', 2)
            && ($this->deliveryRate() ?? 100) < (int) config('reputation.risky_rate', 60);
    }

    /**
     * A one-line verdict for the merchant, or null when we have nothing worth
     * saying. Silence beats filling the screen with "no data".
     */
    public function summary(): ?string
    {
        if ($this->settled() === 0) {
            return null;
        }

        if ($this->isRisky()) {
            return "رفض {$this->refused} طرود على المنصة — نسبة تسليمه {$this->deliveryRate()}%";
        }

        if ($this->delivered >= 3 && ($this->deliveryRate() ?? 0) >= 90) {
            return "عميل موثوق — استلم {$this->delivered} طرود، نسبة تسليمه {$this->deliveryRate()}%";
        }

        return null;
    }
}
