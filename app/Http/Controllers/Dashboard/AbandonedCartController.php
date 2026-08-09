<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AbandonedCartController extends Controller
{
    public function index(Request $request): Response
    {
        $store = $request->user()->currentStore();

        $carts = $store->abandonedCarts()
            ->with(['product.images', 'variant'])
            ->whereNotNull('customer_phone')
            ->when(
                $request->string('filter')->toString() === 'recovered',
                fn ($q) => $q->whereNotNull('recovered_at'),
                fn ($q) => $q->whereNull('recovered_at'),
            )
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AbandonedCart $cart) => [
                'id' => $cart->id,
                'customer_name' => $cart->customer_name,
                'customer_phone' => $cart->customer_phone,
                'governorate' => $cart->governorate,
                'quantity' => $cart->quantity,
                'value' => number_format($cart->value(), 2, '.', ''),
                'product' => $cart->product?->name,
                'variant' => $cart->variant?->label(),
                'image' => $cart->product?->primaryImage()?->url(),
                'contacted_at' => $cart->contacted_at?->diffForHumans(),
                'recovered' => $cart->isRecovered(),
                'abandoned_at' => $cart->updated_at->diffForHumans(),
            ]);

        $open = $store->abandonedCarts()->whereNull('recovered_at')->whereNotNull('customer_phone');

        return Inertia::render('carts/Index', [
            'carts' => $carts,
            'filter' => $request->string('filter')->toString() ?: 'open',
            'currency' => $store->currency,
            'summary' => [
                'open' => (clone $open)->count(),
                // What is sitting on the table right now. The number that
                // decides whether the merchant spends the next hour here.
                'open_value' => round($this->valueOf((clone $open)->get()), 2),
                'recovered' => $store->abandonedCarts()->whereNotNull('recovered_at')->count(),
            ],
        ]);
    }

    /**
     * Mark that the merchant reached out.
     *
     * Not "recovered" — that only happens when an order actually arrives. This
     * exists so a list worked through this morning does not look untouched
     * this afternoon.
     */
    public function contacted(Request $request, AbandonedCart $cart): RedirectResponse
    {
        abort_unless(
            $request->user()->stores()->whereKey($cart->store_id)->exists(),
            403,
        );

        $cart->update(['contacted_at' => now()]);

        return back();
    }

    /** @param \Illuminate\Support\Collection<int,AbandonedCart> $carts */
    private function valueOf($carts): float
    {
        return $carts->sum(fn (AbandonedCart $cart) => $cart->value());
    }
}
