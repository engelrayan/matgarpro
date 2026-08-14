<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_per_order',
        'billable_event',
        'is_default',
        'is_public',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_per_order' => 'decimal:2',
        'is_default' => 'boolean',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class, 'billing_plan_id');
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    public function isFree(): bool
    {
        return (float) $this->price_per_order <= 0;
    }
}
