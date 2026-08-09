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

        return ':root{' . implode('', $lines) . '}';
    }
}
