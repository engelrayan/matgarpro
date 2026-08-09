<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Merchants, read-only.
 *
 * Everything an operator can actually change lives on the store — a merchant
 * row is a login, and there is nothing useful to do to it from here that is
 * not better done to the shop it owns. No password reset and no "sign in as"
 * either: both were considered and neither was asked for, and each would give
 * the panel a way into a merchant's own data that it currently does not have.
 */
class MerchantController extends Controller
{
    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q'));
        $sort = (string) $request->query('sort', 'newest');

        $merchants = User::query()
            ->withCount('stores')
            ->when($term !== '', function ($query) use ($term) {
                $like = '%' . $term . '%';

                $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->tap(fn ($q) => match ($sort) {
                'stores' => $q->orderByDesc('stores_count'),
                'name' => $q->orderBy('name'),
                default => $q->latest('id'),
            })
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'verified' => $user->email_verified_at !== null,
                'stores_count' => $user->stores_count,
                'created_at' => $user->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('admin/merchants/Index', [
            'merchants' => $merchants,
            'filters' => ['q' => $term, 'sort' => $sort],
        ]);
    }

    public function show(User $merchant): Response
    {
        $stores = $merchant->stores()->withCount('orders')->get();

        return Inertia::render('admin/merchants/Show', [
            'merchant' => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'email' => $merchant->email,
                'verified_at' => $merchant->email_verified_at?->format('Y-m-d H:i'),
                'created_at' => $merchant->created_at->format('Y-m-d'),
            ],
            'stores' => $stores->map(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'balance' => (float) $store->balance,
                'orders_count' => $store->orders_count,
                // Delivered only, matching every other revenue figure in the
                // panel — see PlatformInsights for why.
                'gmv' => (float) $store->orders()->where('status', Order::STATUS_DELIVERED)->sum('total'),
                'created_at' => $store->created_at->format('Y-m-d'),
            ]),
            'currency' => config('billing.currency'),
        ]);
    }
}
