<?php

namespace App\Services\Builder;

use App\Models\Store;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Turns whatever the builder posted into a section list this codebase is
 * willing to render.
 *
 * It **rebuilds** rather than validates. Every value in the output was written
 * by the loop below, walking the registry's field schema — an unknown key in
 * the request has nowhere to land, so it is gone rather than rejected with an
 * error the merchant has to decode. The same pass clamps numbers, forces
 * selects onto a real option, sanitises HTML and drops product ids that belong
 * to somebody else's shop.
 *
 * That direction matters: a validator has to think of every bad input, and
 * misses the one nobody thought of. A rebuilder only has to know what good
 * input looks like.
 */
class SectionSanitizer
{
    /** Ceiling on a rich-text body, after sanitising. */
    private const RICHTEXT_MAX = 20000;

    /** Sections on a single page. Beyond this it is not a page, it is a scroll. */
    private const MAX_SECTIONS = 40;

    public function __construct(private readonly SectionRegistry $registry) {}

    /**
     * @param  array<int,mixed>  $input
     * @return array<int,array<string,mixed>>
     */
    public function sections(Store $store, string $page, array $input): array
    {
        $seen = [];
        $out = [];

        foreach (array_slice($input, 0, self::MAX_SECTIONS) as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $type = (string) ($raw['type'] ?? '');

            // Unknown type, or a section that does not belong on this page —
            // a `product_main` posted onto the home page would render a buy
            // form with no product behind it.
            if (! $this->registry->has($type) || ! $this->registry->allowedOn($type, $page)) {
                continue;
            }

            // Per-type ceiling. Two heroes is not a layout choice, it is a
            // mistake, and three countdowns is a page that never loads.
            $limit = $this->registry->find($type)['limit'] ?? null;
            $seen[$type] = ($seen[$type] ?? 0) + 1;

            if ($limit !== null && $seen[$type] > $limit) {
                continue;
            }

            $out[] = [
                // Kept if it looks like one of ours, minted otherwise. The id
                // is what the preview uses to point at a section, so it has to
                // survive a save without becoming a place to inject anything.
                'id' => $this->id($raw['id'] ?? null),
                'type' => $type,
                // A locked section may be reordered but never switched off:
                // hiding the buy form leaves a product page that cannot sell.
                'visible' => $this->registry->isLocked($type) ? true : (bool) ($raw['visible'] ?? true),
                'settings' => $this->settings(
                    $store,
                    $this->registry->find($type)['fields'] ?? [],
                    (array) ($raw['settings'] ?? []),
                ),
            ];
        }

        return $this->withLockedSections($store, $page, $out);
    }

    /**
     * Locked sections are put back if they went missing.
     *
     * A page whose `product_main` was dropped — by a stale client, a hand-made
     * request, or a bug — would render a product page with no way to buy. The
     * merchant can move it; they cannot lose it.
     *
     * @param  array<int,array<string,mixed>>  $sections
     * @return array<int,array<string,mixed>>
     */
    private function withLockedSections(Store $store, string $page, array $sections): array
    {
        $present = array_column($sections, 'type');

        foreach ($this->registry->forPage($page) as $definition) {
            if (! ($definition['locked'] ?? false) || in_array($definition['type'], $present, true)) {
                continue;
            }

            $sections[] = [
                'id' => $this->id(null),
                'type' => $definition['type'],
                'visible' => true,
                'settings' => $this->registry->defaultSettings($definition['type']),
            ];
        }

        return $sections;
    }

    /**
     * @param  array<int,array<string,mixed>>  $fields
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function settings(Store $store, array $fields, array $input): array
    {
        $out = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $out[$key] = $this->value($store, $field, $input[$key] ?? null);
        }

        return $out;
    }

    private function value(Store $store, array $field, mixed $raw): mixed
    {
        $default = $field['default'] ?? null;

        return match ($field['type']) {
            'text' => $this->text($raw, $field['max'] ?? 200),
            'textarea' => $this->text($raw, $field['max'] ?? 2000),
            'richtext' => $this->richtext($raw),
            'number' => $this->number($raw, $field, $default),
            'toggle' => (bool) $raw,
            'select' => $this->select($raw, $field, $default),
            'color' => $this->color($raw, $default),
            'image' => $this->image($raw),
            'link' => $this->link($raw),
            'datetime' => $this->datetime($raw),
            'products' => $this->ownedIds($store->products(), $raw, $field['max'] ?? 12),
            'categories' => $this->ownedIds($store->categories(), $raw, $field['max'] ?? 4),
            'repeater' => $this->repeater($store, $field, $raw),
            default => $default,
        };
    }

    private function text(mixed $raw, int $max): string
    {
        // Tags stripped, not escaped: these land in `{{ }}` which escapes on
        // output anyway, and storing the escaped form would show a merchant
        // their own `&amp;` back in the editor.
        return Str::limit(trim(strip_tags((string) $raw)), $max, '');
    }

    private function richtext(mixed $raw): string
    {
        // The same allow-list the product description already goes through —
        // one sanitiser for all merchant-authored HTML, so a tag that is unsafe
        // in one place cannot be safe in another.
        return Str::limit((string) HtmlSanitizer::clean((string) $raw), self::RICHTEXT_MAX, '');
    }

    private function number(mixed $raw, array $field, mixed $default): int
    {
        if (! is_numeric($raw)) {
            return (int) $default;
        }

        return (int) max($field['min'] ?? 0, min($field['max'] ?? PHP_INT_MAX, (int) $raw));
    }

    private function select(mixed $raw, array $field, mixed $default): string
    {
        $allowed = array_column($field['options'] ?? [], 'value');

        return in_array($raw, $allowed, true) ? (string) $raw : (string) $default;
    }

    private function color(mixed $raw, mixed $default): ?string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', (string) $raw) ? strtolower((string) $raw) : $default;
    }

    /**
     * An uploaded image is referenced by its path on the public disk, never by
     * a URL. Anything with a scheme, a backslash or a `..` in it is not a path
     * we wrote, so it is dropped rather than repaired.
     */
    private function image(mixed $raw): ?string
    {
        $path = trim((string) $raw);

        if ($path === '' || strlen($path) > 255) {
            return null;
        }

        if (! str_starts_with($path, 'builder/') || str_contains($path, '..')) {
            return null;
        }

        return preg_match('#^[A-Za-z0-9/_.-]+$#', $path) ? $path : null;
    }

    /**
     * Links are either somewhere inside this storefront (a path) or an ordinary
     * web address. `javascript:` and `data:` are the two that turn a merchant's
     * own banner into a script tag, so nothing but http and https survives.
     */
    private function link(mixed $raw): string
    {
        $value = trim((string) $raw);

        if ($value === '' || strlen($value) > 500) {
            return '';
        }

        if (str_starts_with($value, '/')) {
            // A protocol-relative `//evil.com` is not an internal path.
            return str_starts_with($value, '//') ? '' : $value;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $value : '';
    }

    private function datetime(mixed $raw): ?string
    {
        if (blank($raw)) {
            return null;
        }

        try {
            return Carbon::parse((string) $raw)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Ids that actually belong to this store, in the order the merchant chose.
     *
     * The ownership check is the point: without it, typing another store's
     * product id into the request would render their product — name, price and
     * photo — inside a competitor's shop.
     *
     * @return array<int,int>
     */
    private function ownedIds($relation, mixed $raw, int $max): array
    {
        $ids = collect(is_array($raw) ? $raw : [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take($max);

        if ($ids->isEmpty()) {
            return [];
        }

        $owned = $relation->whereIn('id', $ids->all())->pluck('id')->all();

        // Re-ordered to the merchant's sequence, not the database's.
        return $ids->filter(fn (int $id) => in_array($id, $owned, true))->values()->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function repeater(Store $store, array $field, mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach (array_slice($raw, 0, $field['max'] ?? 10) as $item) {
            if (is_array($item)) {
                $out[] = $this->settings($store, $field['fields'] ?? [], $item);
            }
        }

        return $out;
    }

    private function id(mixed $raw): string
    {
        $value = (string) $raw;

        return preg_match('/^[a-z0-9]{8,24}$/', $value) ? $value : Str::lower(Str::random(12));
    }
}
