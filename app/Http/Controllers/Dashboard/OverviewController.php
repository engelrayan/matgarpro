<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\Dashboard\StoreInsights;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /** Windows the merchant can pick, in days back from today. */
    private const RANGES = [
        'today' => 0,
        '7d' => 6,
        '30d' => 29,
        '90d' => 89,
    ];

    public function __invoke(Request $request): Response
    {
        $store = $request->user()->currentStore();

        $range = array_key_exists($request->query('range'), self::RANGES)
            ? $request->query('range')
            : '7d';

        $to = CarbonImmutable::now()->endOfDay();
        $from = $to->subDays(self::RANGES[$range])->startOfDay();

        $insights = new StoreInsights($store, $from, $to);

        return Inertia::render('Dashboard', [
            'store' => [
                'name' => $store->name,
                'currency' => $store->currency,
                'url' => $store->canonicalUrl(),
                'host' => $store->canonicalHost(),
                'logo_url' => $store->logoUrl(),
            ],
            /*
             | The three free months, counted down where the merchant will
             | actually see it.
             |
             | A trial that ends silently and turns into the first charge is
             | how a platform loses someone it already won — so the number is
             | on the front page from day one, not buried in a settings tab
             | nobody opens.
             */
            'billing' => [
                'on_trial' => $store->onTrial(),
                'trial_days_left' => $store->trialDaysLeft(),
                'trial_ends_at' => $store->trial_ends_at?->translatedFormat('j F Y'),
                'price_per_order' => $store->loadMissing('plan')->priceAfterTrial(),
                'balance' => (float) $store->balance,
            ],
            'range' => $range,
            'insights' => $insights->all(),
            // The setup list disappears once done rather than sitting there
            // permanently ticked — a finished checklist is just noise.
            'setup' => [
                'has_product' => $store->products()->exists(),
                'has_domain' => $store->domains()->exists(),
                'has_logo' => filled($store->logo_path),
                'has_order' => $store->orders()->exists(),
            ],
            'recent' => $store->orders()->latest('id')->limit(6)->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'customer_name' => $o->customer_name,
                    'total' => $o->total,
                    'status' => $o->status,
                    'status_label' => $o->statusLabel(),
                    'created_at' => $o->created_at->diffForHumans(),
                ]),
            'products_active' => $store->products()->where('status', Product::STATUS_ACTIVE)->count(),
        ]);
    }
}
