<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'options', 'price', 'stock', 'sku', 'image_id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    /**
     * The variant's own price, or the product's when it has none.
     *
     * The product is a parameter rather than `$this->product` on purpose: a
     * NULL price is the common case, so reading the relation here would lazy
     * load once per variant on every product page — and under strict mode it
     * throws instead. Callers always have the product in hand already.
     */
    public function effectivePrice(Product $product): string
    {
        return $this->price ?? $product->price;
    }

    /** "أحمر · L" — what the customer picked, for the order line and the cart. */
    public function label(): string
    {
        return implode(' · ', array_values($this->options ?? []));
    }

    /**
     * Stable key for a combination, independent of option order.
     *
     * The storefront sends selections as a map and the merchant may reorder
     * options later; sorting by key means the same physical variant always
     * hashes the same way.
     */
    public static function keyFor(array $options): string
    {
        ksort($options);

        return implode('|', array_map(
            fn ($k, $v) => "{$k}={$v}",
            array_keys($options),
            $options,
        ));
    }

    public function key(): string
    {
        return static::keyFor($this->options ?? []);
    }
}
