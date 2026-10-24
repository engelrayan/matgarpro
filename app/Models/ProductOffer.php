<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quantity offer on one product: so many pieces, at so much each.
 *
 * Once a product has any, they are the only way to buy it — the customer picks
 * one instead of typing a quantity, and a single piece is on sale only if the
 * merchant made an offer for one.
 */
class ProductOffer extends Model
{
    protected $fillable = [
        'product_id', 'quantity', 'unit_price', 'title', 'badge', 'note',
        'free_shipping', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'free_shipping' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function total(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }

    /** "اشتري 3 قطع بـ 600" — the merchant's wording, or this when they left it blank. */
    public function label(): string
    {
        return filled($this->title)
            ? $this->title
            : 'اشتري ' . self::pieces($this->quantity) . ' بـ ' . self::number($this->total());
    }

    /**
     * What each piece would cost without the offer: the struck-through "before"
     * price the product page already shows, or the plain price when there is
     * none. Taken as a parameter so a page of offers never lazy-loads the
     * product once per row.
     */
    public static function referencePrice(Product $product): float
    {
        $compare = (float) $product->compare_at_price;

        return max($compare, (float) $product->price);
    }

    /** How much the customer keeps against buying the same pieces at the reference price. */
    public function savingAgainst(Product $product): float
    {
        return max(0, (self::referencePrice($product) - (float) $this->unit_price) * $this->quantity);
    }

    /** Counted the way it is said: قطعة، قطعتين، 3 قطع، 11 قطعة. */
    public static function pieces(int $count): string
    {
        return match (true) {
            $count === 1 => 'قطعة',
            $count === 2 => 'قطعتين',
            $count <= 10 => "{$count} قطع",
            default => "{$count} قطعة",
        };
    }

    private static function number(float $value): string
    {
        return fmod($value, 1.0) === 0.0 ? number_format($value) : number_format($value, 2);
    }
}
