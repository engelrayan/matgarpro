<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreDamanIntegration;
use App\Services\Daman\DamanClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Linking a store to Daman.
 *
 * The merchant already has a Daman account with a carrier contract on it; what
 * they do here is paste the API key from that account so this store can hand
 * orders over. Nothing is created on their behalf and no carrier is chosen
 * here — Daman owns both of those.
 */
class DamanController extends Controller
{
    public function __construct(private readonly DamanClient $daman) {}

    public function edit(Request $request): Response
    {
        $store = $this->currentStore($request);
        $link = $store->damanIntegration;

        return Inertia::render('settings/Daman', [
            // Never the key or the secret themselves — a page that can show a
            // credential is a page that can leak it.
            'integration' => $link ? [
                'connected' => true,
                'key_prefix' => $link->key_prefix,
                'environment' => $link->environment,
                'is_active' => $link->is_active,
                'cod_includes_shipping' => $link->cod_includes_shipping,
                'webhook_url' => $link->webhookUrl(),
                'webhook_ready' => filled($link->webhook_secret),
                'connected_at' => $link->connected_at?->diffForHumans(),
                'last_shipped_at' => $link->last_shipped_at?->diffForHumans(),
                'last_webhook_at' => $link->last_webhook_at?->diffForHumans(),
                'last_error' => $link->last_error,
            ] : ['connected' => false],

            // What the merchant would otherwise have to count by hand to know
            // whether the link is doing anything.
            'stats' => [
                'shipped' => $store->orders()->whereNotNull('daman_shipment_id')->count(),
                'failed' => $store->orders()->whereNotNull('daman_error')->whereNull('daman_shipment_id')->count(),
                'awaiting' => $store->orders()
                    ->where('status', \App\Models\Order::STATUS_CONFIRMED)
                    ->whereNull('daman_shipment_id')
                    ->count(),
            ],
        ]);
    }

    /**
     * Save the key, after checking it against Daman.
     *
     * Verified on the way in rather than on the first parcel: a revoked or
     * mistyped key that is only discovered at dispatch time costs a merchant
     * the morning they thought their orders were moving.
     */
    public function update(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);

        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:20', 'max:200'],
        ], [
            'api_key.required' => 'الصق مفتاح ضمان الأول.',
        ]);

        $apiKey = trim($validated['api_key']);
        $result = $this->daman->verify($apiKey);

        if (! $result['ok']) {
            throw ValidationException::withMessages(['api_key' => $result['message']]);
        }

        StoreDamanIntegration::updateOrCreate(
            ['store_id' => $store->id],
            [
                'api_key' => $apiKey,
                'key_prefix' => mb_substr($apiKey, 0, 16),
                'environment' => StoreDamanIntegration::environmentFor($apiKey),
                'is_active' => true,
                'connected_at' => now(),
                'last_error' => null,
                // Kept across a key rotation: Daman posts to the same URL and
                // signs with the same secret, so re-issuing the token would
                // silently break status updates the merchant already set up.
                'webhook_token' => $store->damanIntegration?->webhook_token
                    ?? StoreDamanIntegration::newWebhookToken(),
            ],
        );

        return back()->with('status', 'daman-connected');
    }

    /** The switch that decides whether "شحن عبر ضمان" appears at all. */
    public function toggle(Request $request): RedirectResponse
    {
        $link = $this->linkOrFail($request);

        $link->forceFill(['is_active' => ! $link->is_active])->save();

        return back()->with('status', $link->is_active ? 'daman-enabled' : 'daman-disabled');
    }

    /**
     * The webhook signing secret, as Daman issued it.
     *
     * Daman shows it once, on the screen where the merchant sets their webhook
     * URL. Without it we cannot tell a real status update from anyone who
     * guessed the URL, so an unsigned request is refused rather than trusted.
     */
    public function webhookSecret(Request $request): RedirectResponse
    {
        $link = $this->linkOrFail($request);

        $validated = $request->validate([
            'webhook_secret' => ['required', 'string', 'min:20', 'max:200'],
        ], [
            'webhook_secret.required' => 'الصق الـ secret اللي ضمان عرضه عليك.',
        ]);

        $link->forceFill(['webhook_secret' => trim($validated['webhook_secret'])])->save();

        return back()->with('status', 'daman-webhook-saved');
    }

    /** Which side of the shipping fee the collected amount sits on. */
    public function pricing(Request $request): RedirectResponse
    {
        $link = $this->linkOrFail($request);

        $validated = $request->validate([
            'cod_includes_shipping' => ['required', 'boolean'],
        ]);

        $link->forceFill(['cod_includes_shipping' => $validated['cod_includes_shipping']])->save();

        return back()->with('status', 'daman-pricing-saved');
    }

    /**
     * Forget the key.
     *
     * The orders keep their Daman numbers: those parcels are still out with a
     * carrier, and erasing the waybill because the link was removed would leave
     * the merchant with nothing to chase them by.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->linkOrFail($request)->delete();

        return back()->with('status', 'daman-disconnected');
    }

    private function linkOrFail(Request $request): StoreDamanIntegration
    {
        $link = $this->currentStore($request)->damanIntegration;

        abort_unless($link, 404);

        return $link;
    }

    private function currentStore(Request $request): Store
    {
        return $request->user()->currentStore();
    }
}
