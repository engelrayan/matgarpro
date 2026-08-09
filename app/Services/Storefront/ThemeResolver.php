<?php

namespace App\Services\Storefront;

use App\Models\Store;

/**
 * Resolves a store's chosen theme into the tokens the storefront renders with.
 *
 * Always returns a complete theme. A store whose saved theme has since been
 * removed from the platform must still render — falling back is the difference
 * between an old store looking slightly different and an old store looking
 * broken.
 */
class ThemeResolver
{
    /** @return array<string,mixed> */
    public function forStore(Store $store): array
    {
        $key = (string) data_get($store->settings, 'theme', config('themes.default'));

        return $this->byKey($key);
    }

    /** @return array<string,mixed> */
    public function byKey(string $key): array
    {
        $themes = (array) config('themes.themes');
        $fallback = (string) config('themes.default');

        $theme = $themes[$key] ?? $themes[$fallback];

        return [
            'key' => isset($themes[$key]) ? $key : $fallback,
            ...$theme,
            // Merged over the default's palette so a theme missing a token
            // inherits a sane one instead of rendering an unstyled element.
            'palette' => array_merge($themes[$fallback]['palette'], $theme['palette']),
        ];
    }

    /**
     * Every theme, for the picker.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        return collect(config('themes.themes'))
            ->map(fn (array $theme, string $key) => [...$this->byKey($key)])
            ->values()
            ->all();
    }

    /**
     * The theme's palette as a CSS custom-property block.
     *
     * Emitted inline in the document head rather than as a stylesheet: it is a
     * few hundred bytes, and a separate request would leave the store showing
     * the platform's colours for the first paint of every page.
     */
    public function cssVariables(array $theme): string
    {
        $lines = [];

        foreach ($theme['palette'] as $token => $value) {
            $lines[] = "--{$token}:{$value};";
        }

        $lines[] = "--radius:{$theme['radius']};";

        /*
         | The theme's typeface.
         |
         | This key sat in the config for months and nothing read it: every
         | store, whatever theme it picked, rendered in the platform's own
         | font. Type is the single biggest reason two shops look like two
         | shops rather than one shop in two colours — a watch boutique and a
         | toy store cannot share a typeface and both look right.
         |
         | Emitted as a variable rather than a hard font-family so the whole
         | Tailwind scale (`font-sans`, and everything composed from it) picks
         | it up in one place.
         */
        $lines[] = '--font-sans:' . $this->fontFamily($theme) . ';';

        return ':root{' . implode('', $lines) . '}';
    }

    /**
     * The CSS family name for a theme's font, quoted and with fallbacks.
     *
     * Looked up rather than derived from the slug: "ibm-plex-sans-arabic"
     * title-cases to "Ibm Plex Sans Arabic", which matches no font on any
     * machine and silently falls through to the system stack.
     */
    public function fontFamily(array $theme): string
    {
        $family = config('themes.fonts.' . $theme['font']) ?? 'IBM Plex Sans Arabic';

        return "'{$family}',ui-sans-serif,system-ui,sans-serif";
    }

    /** The stylesheet URL that actually delivers the theme's font. */
    public function fontUrl(array $theme): string
    {
        // Weights are fixed across themes: anything a storefront needs is
        // covered, and letting each theme pick its own would mean a theme that
        // asks for a weight it never uses paying for the download anyway.
        return 'https://fonts.bunny.net/css?family=' . $theme['font'] . ':400,500,600,700';
    }
}
