<?php

namespace App\Actions\Stores;

use App\Models\BillingPlan;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates a merchant's store.
 *
 * Lives in an Action rather than a controller because the dashboard, the future
 * mobile API and the seeders all need identical behaviour — a store created
 * through one path must be indistinguishable from one created through another.
 */
class CreateStore
{
    public function handle(User $user, string $name, ?string $slug = null): Store
    {
        return $user->stores()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($slug ?: $name),
            'status' => Store::STATUS_ACTIVE,
            'currency' => config('billing.currency'),
            'billing_plan_id' => BillingPlan::default()?->id,
        ]);
    }

    /**
     * Turn a store name into a free sub-domain label.
     *
     * Str::slug() strips non-ASCII, so an Arabic name like "متجر محمود" comes
     * back empty — every Arabic store would collide on "". Those fall back to a
     * generated label, and the merchant can pick a nicer one later.
     */
    public function uniqueSlug(string $source): string
    {
        $base = Str::slug($source);

        if ($base === '' || is_numeric($base)) {
            $base = 'store';
        }

        $base = Str::limit($base, 40, '');
        $reserved = (array) config('storefront.reserved_slugs');

        $candidate = $base;
        $suffix = 0;

        while (in_array($candidate, $reserved, true) || Store::where('slug', $candidate)->exists()) {
            $suffix++;
            $candidate = $base . '-' . ($suffix === 1 ? Str::lower(Str::random(4)) : $suffix);
        }

        return $candidate;
    }
}
