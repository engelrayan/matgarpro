<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StorePixel;
use App\Services\Pixels\PixelDiagnostics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pixel IDs, one box per network.
 *
 * Deliberately shaped like the tool merchants already use: a textarea per
 * platform with one id per line, rather than an add-one-at-a-time form. A
 * merchant running the same product on Facebook, TikTok and Snapchat pastes
 * three lists once and is done — and they arrive here already knowing that is
 * how it works.
 */
class PixelController extends Controller
{
    /** Networks we render, in the order the page shows them. */
    private const PROVIDERS = ['meta', 'tiktok', 'snapchat'];

    public function edit(Request $request): Response
    {
        $store = $request->user()->currentStore();
        $pixels = $store->pixels()->orderBy('id')->get();

        return Inertia::render('settings/Pixels', [
            // One newline-separated string per network, which is exactly what
            // the textarea binds to.
            'ids' => collect(self::PROVIDERS)->mapWithKeys(fn (string $provider) => [
                $provider => $pixels->where('provider', $provider)
                    ->where('is_active', true)
                    ->pluck('pixel_id')
                    ->implode("\n"),
            ]),
            // The Conversions API is a separate concern: it needs a token per
            // Meta pixel, and mixing it into these boxes is what makes the
            // page confusing.
            'capi' => $pixels->where('provider', 'meta')->map(fn (StorePixel $pixel) => [
                'id' => $pixel->id,
                'pixel_id' => $pixel->pixel_id,
                // Never the token itself — a page that can show it can leak it.
                'has_token' => filled($pixel->access_token),
                'last_event_at' => $pixel->last_event_at?->diffForHumans(),
                'last_error' => $pixel->last_error,
            ])->values(),

            // Computed from this store's own recent orders, so the advice is
            // about their data rather than a generic checklist.
            'match_quality' => app(PixelDiagnostics::class)->matchQuality($store),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $request->user()->currentStore();

        $validated = $request->validate([
            'meta' => ['nullable', 'string', 'max:2000'],
            'tiktok' => ['nullable', 'string', 'max:2000'],
            'snapchat' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach (self::PROVIDERS as $provider) {
            $this->sync($store, $provider, $this->parse($validated[$provider] ?? ''));
        }

        return back()->with('status', 'pixels-updated');
    }

    /**
     * Send a real test event and report what Meta said.
     *
     * A genuine round-trip rather than a format check: a token can be
     * well-formed and still revoked, and that is the failure merchants
     * actually hit.
     */
    public function test(Request $request, StorePixel $pixel, PixelDiagnostics $diagnostics): RedirectResponse
    {
        abort_unless(
            $request->user()->stores()->whereKey($pixel->store_id)->exists(),
            403,
        );

        $result = $diagnostics->testConnection($pixel);

        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    /** Add or replace the CAPI token on one Meta pixel. */
    public function token(Request $request, StorePixel $pixel): RedirectResponse
    {
        abort_unless(
            $request->user()->stores()->whereKey($pixel->store_id)->exists(),
            403,
        );

        $validated = $request->validate([
            'access_token' => ['required', 'string', 'max:500'],
        ]);

        $pixel->update([
            'access_token' => $validated['access_token'],
            // A new token is a new chance; the old failure is not about it.
            'last_error' => null,
        ]);

        return back()->with('status', 'token-saved');
    }

    /**
     * One id per line, ignoring blanks and anything that is not digits.
     *
     * Merchants paste the whole snippet from Events Manager more often than the
     * bare id. Pulling the digits out of each line means that paste works
     * instead of failing validation on something that looks correct to them.
     *
     * @return Collection<int,string>
     */
    private function parse(?string $raw): Collection
    {
        return collect(preg_split('/\R/', (string) $raw))
            ->map(fn (string $line) => preg_replace('/\D+/', '', $line) ?? '')
            ->filter(fn (string $id) => strlen($id) >= 10 && strlen($id) <= 20)
            ->unique()
            ->values();
    }

    /**
     * Reconcile one network's pixels against the submitted list.
     *
     * Deactivated rather than deleted: the row carries the CAPI token and the
     * last-error history, and a merchant who removes a line by accident should
     * get it all back by pasting the id again.
     *
     * @param  Collection<int,string>  $ids
     */
    private function sync(Store $store, string $provider, Collection $ids): void
    {
        $existing = $store->pixels()->where('provider', $provider)->get();

        foreach ($ids as $id) {
            $pixel = $existing->firstWhere('pixel_id', $id);

            $pixel
                ? $pixel->update(['is_active' => true])
                : $store->pixels()->create([
                    'provider' => $provider,
                    'pixel_id' => $id,
                    'is_active' => true,
                ]);
        }

        $existing
            ->reject(fn (StorePixel $pixel) => $ids->contains($pixel->pixel_id))
            ->each->update(['is_active' => false]);
    }
}
