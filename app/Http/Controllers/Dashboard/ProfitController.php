<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\ProfitReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfitController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $store = $request->user()->currentStore();

        $range = in_array($request->query('range'), ['7d', '30d', '90d', 'all'], true)
            ? $request->query('range')
            : '30d';

        $from = match ($range) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            // Far enough back to mean "everything" without a null branch in
            // every query below it.
            'all' => now()->subYears(10),
            default => now()->subDays(30),
        };

        return Inertia::render('reports/Profit', [
            'range' => $range,
            'currency' => $store->currency,
            'report' => (new ProfitReport($store))->build($from->startOfDay(), now()),
            'return_cost' => (float) config('profit.return_cost_per_parcel'),
        ]);
    }
}
