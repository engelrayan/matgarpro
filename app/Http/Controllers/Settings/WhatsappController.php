<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\InboxController;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\ConfirmsWithButtons;
use App\Services\Whatsapp\DriverFactory;
use App\Services\Whatsapp\OrderConfirmationMessage;
use App\Services\Whatsapp\PlatformWhatsapp;
use App\Services\Whatsapp\Waha\WahaClient;
use App\Services\Whatsapp\Waha\WahaException;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where the confirmations go out from: the platform's official number, a
 * service switched on with one click, or the store's own number, linked by
 * scanning a QR code. Each has its price and its trade-off, so the screen
 * puts them side by side rather than picking for the merchant.
 */
class WhatsappController extends Controller
{
    public function __construct(
        private readonly DriverFactory $drivers,
        private readonly OrderConfirmationMessage $composer,
    ) {}

    public function edit(Request $request): Response
    {
        $store = $this->currentStore($request);
        $link = $store->whatsappIntegration;

        return Inertia::render('settings/Whatsapp', [
            // Never the credentials themselves — a page that can show a token
            // is a page that can leak it.
            'integration' => $link ? [
                'connected' => true,
                'driver' => $link->driver,
                'sender_phone' => $link->sender_phone,
                'is_active' => $link->is_active,
                'auto_send' => $link->auto_send,
                'message_template' => $link->template(),
                'connected_at' => $link->connected_at?->diffForHumans(),
                'last_sent_at' => $link->last_sent_at?->diffForHumans(),
                'last_inbound_at' => $link->last_inbound_at?->diffForHumans(),
                'last_error' => $link->last_error,
            ] : ['connected' => false],

            'placeholders' => StoreWhatsappIntegration::PLACEHOLDERS,
            'default_template' => StoreWhatsappIntegration::WAHA_TEMPLATE,

            // The no-setup option, offered only once the platform number is
            // configured — and the message it sends, with this shop's name in
            // it, so the merchant sees what their customers will get.
            'platform' => [
                'available' => PlatformWhatsapp::configured(),
                'preview' => strtr(PlatformWhatsapp::BODY, [
                    '{name}' => 'سارة',
                    '{number}' => '1042',
                    '{store}' => $store->name,
                    '{items}' => '• 2× قميص قطن (أبيض · L)',
                    '{amount}' => '798.00 ' . $store->currency,
                ]),
                'confirm_label' => PlatformWhatsapp::CONFIRM_LABEL,
                'cancel_label' => PlatformWhatsapp::CANCEL_LABEL,
                'fee' => $store->basePricePerOrder() > 0 ? Store::confirmationFeeFor(StoreWhatsappIntegration::DRIVER_PLATFORM) : 0,
            ],

            // The store's own number by QR, through our WAHA server.
            'waha' => [
                'available' => WahaClient::configured(),
                'fee' => $store->basePricePerOrder() > 0 ? Store::confirmationFeeFor(StoreWhatsappIntegration::DRIVER_WAHA) : 0,
                'status' => $link?->isWaha() ? ($link->credential('status') ?? 'STARTING') : null,
            ],

            // The order's own price, so each channel can show what an order
            // with its confirmation costs in total — the number a merchant
            // actually compares.
            'orderPrice' => [
                'now' => $store->pricePerOrder(),
                'after' => $store->basePricePerOrder(),
                'intro' => $store->inIntroPeriod(),
            ],
            'currency' => $store->currency,

            // What the merchant would otherwise count by hand to know whether
            // any of this is working.
            'stats' => $this->stats($store),

            // The latest conversations, each one a click from its chat in the
            // inbox — a list of raw messages here read as a log, not as people.
            'recent' => WhatsappConversation::query()
                ->where('store_id', $store->id)
                ->whereNotNull('last_message_at')
                ->latest('last_message_at')
                ->limit(6)
                ->get()
                ->map(fn (WhatsappConversation $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => InboxController::localPhone($c->phone),
                    'preview' => $c->last_preview,
                    'last_direction' => $c->last_direction,
                    'last_at' => $c->last_message_at?->toIso8601String(),
                    'unread' => $c->unread_count,
                    'tags' => $c->tags ?? [],
                ]),
        ]);
    }

    /**
     * Switch the store onto the platform's own number. One click, no keys.
     *
     * Replaces the store's own number if it had one linked: a store sends from
     * one number, and two would message the same customer twice.
     */
    public function platform(Request $request): RedirectResponse
    {
        abort_unless(PlatformWhatsapp::configured(), 404);

        $store = $this->currentStore($request);
        $existing = $store->whatsappIntegration;

        StoreWhatsappIntegration::updateOrCreate(
            ['store_id' => $store->id],
            [
                'driver' => StoreWhatsappIntegration::DRIVER_PLATFORM,
                'credentials' => [],
                'sender_phone' => null,
                'is_active' => true,
                // The point of it is that nobody has to press anything.
                'auto_send' => true,
                'connected_at' => now(),
                'last_error' => null,
                'webhook_token' => $existing?->webhook_token ?? StoreWhatsappIntegration::newToken(),
                'verify_token' => $existing?->verify_token ?? StoreWhatsappIntegration::newToken(),
            ],
        );

        return back()->with('status', 'whatsapp-platform');
    }

    /**
     * Link the store's own number through our WAHA gateway: create (or
     * restart) its session, then the screen polls `wahaStatus` for the QR.
     *
     * Replaces the platform number if the store was on it; the webhook token
     * is kept.
     */
    public function waha(Request $request, WahaClient $waha): RedirectResponse
    {
        abort_unless(WahaClient::configured(), 404);

        $store = $this->currentStore($request);
        $existing = $store->whatsappIntegration;

        $link = StoreWhatsappIntegration::updateOrCreate(
            ['store_id' => $store->id],
            [
                'driver' => StoreWhatsappIntegration::DRIVER_WAHA,
                'credentials' => ['status' => 'STARTING'],
                'sender_phone' => null,
                'is_active' => true,
                'auto_send' => true,
                // Back to the poll's own wording.
                'message_template' => null,
                'connected_at' => now(),
                'last_error' => null,
                'webhook_token' => $existing?->webhook_token ?? StoreWhatsappIntegration::newToken(),
                'verify_token' => $existing?->verify_token ?? StoreWhatsappIntegration::newToken(),
            ],
        );

        try {
            $waha->connect($link);
        } catch (WahaException $e) {
            $link->forceFill(['last_error' => $e->getMessage()])->save();

            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'whatsapp-waha-started');
    }

    /**
     * Where the linking is, for the screen to poll every few seconds: the QR
     * while it waits for a scan, the number once it is linked.
     */
    public function wahaStatus(Request $request, WahaClient $waha): JsonResponse
    {
        $link = $this->linkOrFail($request);
        abort_unless($link->isWaha(), 404);

        try {
            $status = $waha->status($link);
            $qr = $status['status'] === WahaClient::SCAN_QR ? $waha->qr($link) : null;
        } catch (WahaException $e) {
            return response()->json(['status' => 'UNREACHABLE', 'message' => $e->getMessage()]);
        }

        // Kept on the link too, so sending works off it without asking WAHA.
        $link->forceFill([
            'credentials' => [...(array) $link->credentials, 'status' => $status['status']],
            'sender_phone' => $status['phone'] ?? $link->sender_phone,
            'last_error' => $status['status'] === WahaClient::WORKING ? null : $link->last_error,
        ])->save();

        return response()->json([
            'status' => $status['status'],
            'phone' => $status['phone'],
            'name' => $status['name'],
            'qr' => $qr,
        ]);
    }

    /** The wording, and whether a new order sends it by itself. */
    public function message(Request $request): RedirectResponse
    {
        $link = $this->linkOrFail($request);

        $validated = $request->validate([
            'message_template' => ['required', 'string', 'max:2000'],
            'auto_send' => ['required', 'boolean'],
        ], [
            'message_template.required' => 'الرسالة ماينفعش تفضل فاضية.',
        ]);

        $link->forceFill([
            'message_template' => $validated['message_template'],
            'auto_send' => $validated['auto_send'],
        ])->save();

        return back()->with('status', 'whatsapp-message-saved');
    }

    /**
     * Send the real message to a number the merchant chooses.
     *
     * A genuine round-trip — the only way to see, before a customer does, that
     * the linked number really sends.
     */
    public function test(Request $request): RedirectResponse
    {
        $link = $this->linkOrFail($request);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:24'],
        ], ['phone.required' => 'اكتب رقم نبعتله رسالة تجربة.']);

        $phone = Phone::e164($validated['phone']);

        if ($phone === '') {
            throw ValidationException::withMessages(['phone' => 'الرقم ده مش مظبوط.']);
        }

        $store = $this->currentStore($request);

        // A real order when there is one, so the message the merchant reads is
        // the message a customer would read — not a preview of it.
        $order = $store->orders()->with('items')->latest('id')->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'phone' => 'مفيش طلبات في المتجر لسه — اعمل طلب تجربة الأول عشان نبعت رسالة حقيقية.',
            ]);
        }

        $message = $this->composer->build($link, $order);
        $driver = $this->drivers->make($link);

        /*
         | Exactly what a customer gets — buttons or the poll included. A test
         | that sent only the text made the merchant think the customer would
         | have to type. Safe to tap: a reply only moves an order when it
         | comes from that order's own phone, and this goes to the merchant's.
         */
        $result = $driver instanceof ConfirmsWithButtons
            ? $driver->sendOrderConfirmation($phone, $message['body'], $message['variables'], $order)
            : $driver->send($phone, $message['body'], $message['variables']);

        WhatsappMessage::create([
            'store_id' => $store->id,
            'order_id' => null,
            'direction' => WhatsappMessage::DIRECTION_OUT,
            'phone' => $phone,
            'body' => $message['body'],
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->ok ? 'sent' : 'failed',
            'error' => $result->error,
        ]);

        return back()->with(
            $result->ok ? 'status' : 'error',
            $result->ok ? 'الرسالة اتبعتت — شوف الواتساب.' : (string) $result->error,
        );
    }

    public function toggle(Request $request): RedirectResponse
    {
        $link = $this->linkOrFail($request);

        $link->forceFill(['is_active' => ! $link->is_active])->save();

        return back()->with('status', $link->is_active ? 'whatsapp-enabled' : 'whatsapp-disabled');
    }

    /**
     * Stop confirming on WhatsApp.
     *
     * The message log stays: it is the record of what customers were told, and
     * switching the service off is not a reason to lose it.
     */
    public function destroy(Request $request, WahaClient $waha): RedirectResponse
    {
        $link = $this->linkOrFail($request);

        // On our own gateway, disconnecting also unlinks the phone — the
        // merchant sees the linked device disappear from their WhatsApp.
        if ($link->isWaha()) {
            $waha->disconnect($link);
        }

        $link->delete();

        return back()->with('status', 'whatsapp-disconnected');
    }

    // ── private ─────────────────────────────────────────────────────────────

    /** @return array<string,int> */
    private function stats(Store $store): array
    {
        return [
            'sent' => $store->orders()->whereNotNull('whatsapp_sent_at')->count(),
            'awaiting' => $store->orders()->where('whatsapp_state', 'sent')->count(),
            'confirmed' => $store->orders()->where('whatsapp_state', 'confirmed')->count(),
            'cancelled' => $store->orders()->where('whatsapp_state', 'cancelled')->count(),
            'failed' => $store->orders()->where('whatsapp_state', 'failed')->count(),
            'no_reply' => $store->orders()->where('whatsapp_state', 'no_reply')->count(),
        ];
    }

    private function linkOrFail(Request $request): StoreWhatsappIntegration
    {
        $link = $this->currentStore($request)->whatsappIntegration;

        abort_unless($link, 404);

        return $link;
    }

    private function currentStore(Request $request): Store
    {
        return $request->user()->currentStore();
    }
}
