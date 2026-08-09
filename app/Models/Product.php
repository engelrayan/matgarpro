<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'store_id', 'name', 'slug', 'description', 'price', 'compare_at_price', 'sale_ends_at',
        'cost', 'sku', 'track_stock', 'stock', 'options', 'settings', 'status',
        'sort_order', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'sale_ends_at' => 'datetime',
            'cost' => 'decimal:2',
            'track_stock' => 'boolean',
            'stock' => 'integer',
            'options' => 'array',
            'settings' => 'array',
        ];
    }

    /**
     * Page settings, merged over the platform defaults.
     *
     * Merged rather than stored whole so a toggle added to the platform later
     * takes effect on products saved before it existed.
     *
     * Named `pageSettings`, not `settings`: a no-argument method matching a
     * column name is what Eloquent tries to resolve as a relation, and that
     * only shows up later as a confusing error under eager loading.
     *
     * @return array<string,mixed>
     */
    public function pageSettings(): array
    {
        return array_merge(
            (array) config('products.defaults'),
            (array) ($this->settings ?? []),
        );
    }

    public function setting(string $key): mixed
    {
        return $this->pageSettings()[$key] ?? null;
    }

    /** Should this product be hidden from the storefront right now? */
    public function isHidden(): bool
    {
        if (! $this->isActive()) {
            return true;
        }

        return $this->track_stock
            && $this->availableStock() <= 0
            && (bool) $this->setting('hide_when_out_of_stock');
    }

    /** Total sellable units — summed across variants when the product has any. */
    public function availableStock(): int
    {
        if (! $this->hasVariants()) {
            return $this->stock;
        }

        return (int) $this->variants()->sum('stock');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // -------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasVariants(): bool
    {
        return filled($this->options);
    }

    public function primaryImage(): ?ProductImage
    {
        return $this->images->first();
    }

    /** Discount percentage, or null when the product is not on sale. */
    public function discountPercent(): ?int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return (int) round((1 - $this->price / $this->compare_at_price) * 100);
    }

    /** Is this product discounted right now? */
    public function isOnSale(): bool
    {
        return $this->discountPercent() !== null
            && ! $this->saleHasExpired();
    }

    /**
     * A deadline the storefront can count down to, or null.
     *
     * Returns null once the moment has passed, so an expired sale simply stops
     * showing a timer instead of rendering a negative one.
     */
    public function saleDeadline(): ?\Illuminate\Support\Carbon
    {
        return $this->sale_ends_at && $this->sale_ends_at->isFuture()
            ? $this->sale_ends_at
            : null;
    }

    private function saleHasExpired(): bool
    {
        return $this->sale_ends_at !== null && $this->sale_ends_at->isPast();
    }

    /**
     * Can `$quantity` of this product (or one of its variants) be sold?
     *
     * Stock lives on the variant when there are variants and on the product
     * otherwise, so the caller never has to decide which one to look at.
     */
    public function canFulfil(int $quantity, ?ProductVariant $variant = null): bool
    {
        if (! $this->track_stock) {
            return true;
        }

        return ($variant?->stock ?? $this->stock) >= $quantity;
    }

    /**
     * A slug that is free within this store.
     *
     * Str::slug() strips Arabic to an empty string, so a product named "قميص"
     * would collide with every other Arabic-named product. Those fall back to
     * the product's own id-free random label, which the merchant can edit.
     */
    public static function uniqueSlug(int $storeId, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'product';
        $base = Str::limit($base, 60, '');

        $candidate = $base;
        $suffix = 0;

        while (
            static::withTrashed()
                ->where('store_id', $storeId)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $suffix++;
            $candidate = $base . '-' . ($suffix === 1 ? Str::lower(Str::random(4)) : $suffix);
        }

        return $candidate;
    }
}
