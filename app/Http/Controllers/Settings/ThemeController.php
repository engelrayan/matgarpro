<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function __construct(private readonly ThemeResolver $themes) {}

    public function edit(Request $request): Response
    {
        $store = $request->user()->currentStore();

        /*
         | Each theme's showroom URL.
         |
         | A real storefront rather than a rendered card: a merchant judging a
         | theme from swatches is judging it blind, and any mockup drifts from
         | the real templates the moment either one changes.
         */
        $showrooms = Store::where('is_demo', true)
            ->get()
            ->mapWithKeys(fn (Store $demo) => [
                Str::after($demo->slug, 'demo-') => $demo->canonicalUrl(),
            ]);

        /*
         | What each card shows: three real products from that theme's own
         | showroom catalogue (config/demo.php → theme_previews), in the
         | theme's own typeface. A card of empty grey boxes asks the merchant
         | to imagine the shop; this one shows it.
         */
        $catalogueRows = collect(config('demo.catalogues'))
            ->flatMap(fn (array $catalogue) => $catalogue['products'])
            ->keyBy('photo');

        $previewProducts = fn (string $key) => collect(config("demo.theme_previews.{$key}", []))
            ->map(fn (int $photo) => $catalogueRows->get($photo))
            ->filter()
            ->map(fn (array $row) => [
                'name' => $row['name'],
                'price' => number_format((float) $row['price']),
                'compare' => $row['compare'] ? number_format((float) $row['compare']) : null,
                'image' => "/images/theme-previews/{$row['photo']}.jpg",
            ])
            ->values()
            ->all();

        return Inertia::render('settings/Themes', [
            'themes' => collect($this->themes->all())
                ->map(fn (array $theme) => [
                    ...$theme,
                    'preview_url' => $showrooms[$theme['key']] ?? null,
                    'products' => $previewProducts($theme['key']),
                    'font_family' => $this->themes->fontFamily($theme),
                    'font_url' => $this->themes->fontUrl($theme),
                ])
                ->all(),
            'current' => $this->themes->forStore($store)['key'],
            // The merchant should be able to look at the result immediately;
            // a theme picked from swatches alone is a theme picked blind.
            'storeUrl' => $store->canonicalUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $request->user()->currentStore();

        $validated = $request->validate([
            // Only keys we actually ship. An unknown one would be stored and
            // then silently fall back on every page render.
            'theme' => ['required', 'string', Rule::in(array_keys((array) config('themes.themes')))],
        ], [
            'theme.in' => 'الثيم ده مش موجود.',
        ]);

        $store->update([
            'settings' => [...(array) $store->settings, 'theme' => $validated['theme']],
        ]);

        return back()->with('status', 'theme-updated');
    }
}
