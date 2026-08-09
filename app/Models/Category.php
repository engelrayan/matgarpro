<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id', 'name', 'slug', 'description', 'image_path', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * A slug that is free within this store.
     *
     * Str::slug() strips Arabic to an empty string, so "رجالي" and "حريمي"
     * would both reduce to "" and collide on the unique index.
     */
    public static function uniqueSlug(int $storeId, string $source, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug($source) ?: 'category', 60, '');

        $candidate = $base;
        $suffix = 0;

        while (
            static::where('store_id', $storeId)
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
