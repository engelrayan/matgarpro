<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformInsights;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /** Windows an operator can pick, in days back from today. */
    public const RANGES = [
        'today' => 0,
        '7d' => 6,
        '30d' => 29,
        '90d' => 89,
        '365d' => 364,
    ];

    public function __invoke(Request $request): Response
    {
        [$from, $to, $range] = self::window($request);

        return Inertia::render('admin/Overview', [
            'range' => $range,
            'insights' => (new PlatformInsights($from, $to))->all(),
            'currency' => config('billing.currency'),
        ]);
    }

    /**
     * Resolve the requested window, falling back to a week.
     *
     * Shared with the other admin screens so "آخر ٣٠ يوم" means the same span
     * everywhere — a range that shifts between screens makes two numbers that
     * should match look like a bug.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    public static function window(Request $request): array
    {
        $range = array_key_exists((string) $request->query('range'), self::RANGES)
            ? (string) $request->query('range')
            : '30d';

        $to = CarbonImmutable::now()->endOfDay();
        $from = $to->subDays(self::RANGES[$range])->startOfDay();

        return [$from, $to, $range];
    }
}
