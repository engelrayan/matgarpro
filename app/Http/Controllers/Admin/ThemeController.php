<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Admin\PlatformInsights;
use App\Services\Storefront\ThemeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Which themes the platform ships, and who is actually using them.
 *
 * Themes are config, not rows — see `config/themes.php` for why there is one
 * template tree and not four. So this screen reads rather than edits: the
 * useful operator questions are "is anybody on this one" and "did somebody get
 * stranded on a theme we removed", and both are answered here. Changing a
 * particular store's theme is done from that store's page.
 */
class ThemeController extends Controller
{
    public function __construct(private readonly ThemeResolver $themes) {}

    public function index(Request $request): Response
    {
        $insights = new PlatformInsights(
            CarbonImmutable::now()->subDays(29)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );

        $default = (string) config('themes.default');
        $selected = (string) $request->query('theme', '');

        return Inertia::render('admin/Themes', [
            'themes' => collect($insights->themeUsage())
                ->map(fn (array $theme) => [
                    ...$theme,
                    'is_default' => $theme['key'] === $default,
                    'preview_url' => $this->showroomUrl($theme['key']),
                ])
                ->all(),
            // A theme key on a store that we no longer ship. Those stores are
            // silently rendering the default right now.
            'orphans' => $insights->orphanThemes(),
            'selected' => $selected,
            // Drilldown: who exactly is on the theme the operator clicked.
            'stores' => $selected === '' ? [] : $this->storesOnTheme($selected, $default),
        ]);
    }

    /**
     * The live showroom for a theme, when one has been seeded.
     *
     * A real storefront rather than a rendered card — the same reasoning as
     * the merchant-facing picker.
     */
    private function showroomUrl(string $key): ?string
    {
        return Store::where('is_demo', true)
            ->where('slug', 'demo-' . $key)
            ->first()?->canonicalUrl();
    }

    /** @return array<int,array<string,mixed>> */
    private function storesOnTheme(string $key, string $default): array
    {
        return Store::query()
            ->where('is_demo', false)
            ->when(
                $key === $default,
                // Stores that never opened the picker have no `theme` key and
                // render the default, so they belong in this list.
                fn ($q) => $q->where(fn ($inner) => $inner->whereNull('settings->theme')->orWhere('settings->theme', $default)),
                fn ($q) => $q->where('settings->theme', $key),
            )
            ->withCount('orders')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'orders_count' => $store->orders_count,
            ])
            ->all();
    }
}
