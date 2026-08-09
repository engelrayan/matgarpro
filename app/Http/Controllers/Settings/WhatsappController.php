<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\DriverFactory;
use App\Services\Whatsapp\OrderConfirmationMessage;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connecting a store's own WhatsApp line.
 *
 * The merchant chooses their gateway and pastes its keys; nothing is created on
 * their behalf. Which gateway is a real decision with a real trade-off, so the
 * screen states it rather than picking for them.
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
                'template_name' => $link->template_name,
                'template_language' => $link->template_language,
                'has_app_secret' => filled($link->credential('app_secret')),
                'webhook_url' => $link->webhookUrl(),
                'verify_token' => $link->verify_token,
                'connected_at' => $link->connected_at?->diffForHumans(),
                'last_sent_at' => $link->last_sent_at?->diffForHumans(),
                'last_inbound_at' => $link->last_inbound_at?->diffForHumans(),
                'last_error' => $link->last_error,
            ] : ['connected' => false],

            'placeholders' => StoreWhatsappIntegration::PLACEHOLDERS,
            'default_template' => StoreWhatsappIntegration::DEFAULT_TEMPLATE,

            // What the merchant would otherwise count by hand to know whether
            // any of this is working.
            'stats' => $this->stats($store),

            // The last few messages either way. The whole point of keeping them.
            'recent' => WhatsappMessage::query()
                ->where('store_id', $store->id)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (WhatsappMessage $m) => [
                    'direction' => $m->direction,
                    'phone' => $m->phone,
                    'body' => mb_strimwidth((string) $m->body, 0, 120, '…'),
                    'status' => $m->status,
                    'intent' => $m->intent,
                    'error' => $m->error,
                    'at' => $m->created_at->diffForHumans(),
                ]),
        ]);
    }

    /**
     * Save the gateway keys, after checking them against it.
     *
     * Verified on the way in rather than on the first order: a revoked token
     * discovered at checkout time costs the merchant the confirmations for
     * however long it takes them to notice.
     */
    public function update(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);

        $validated = $request->validate([
            'driver' => ['required', Rule::in([
                StoreWhatsappIntegration::DRIVER_WAPILOT,
                StoreWhatsappIntegration::DRIVER_CLOUD_API,
            ])],
            // Wapilot
            'token' => ['required_if:driver,wapilot', 'nullable', 'string', 'max:300'],
            'instance' => ['required_if:driver,wapilot', 'nullable', 'string', 'max:100'],
            // Cloud API
            'access_token' => ['required_if:driver,cloud_api', 'nullable', 'string', 'max:1000'],
            'phone_number_id' => ['required_if:driver,cloud_api', 'nullable', 'string', 'max:64'],
            'app_secret' => ['nullable', 'string', 'max:200'],
        ], [
            'token.required_if' => 'محتاجين التوكن بتاع Wapilot.',
            'instance.required_if' => 'محتاجين رقم الـ instance بتاع Wapilot.',
            'access_token.required_if' => 'محتاجين الـ access token بتاع ميتا.',
            'phone_number_id.required_if' => 'محتاجين الـ phone number ID بتاع الرقم.',
        ]);

        $existing = $store->whatsappIntegration;

        // Built but not saved — the keys are checked against the gateway first,
        // and a rejected one must not overwrite a connection that works.
        $candidate = new StoreWhatsappIntegration([
            'store_id' => $store->id,
            'driver' => $validated['driver'],
            'credentials' => $this->credentials($validated, $existing),
            'template_name' => $existing?->template_name,
            'template_language' => $existing?->template_language ?? 'ar',
            'verify_token' => $existing?->verify_token ?? StoreWhatsappIntegration::newToken(),
            'webhook_token' => $existing?->webhook_token ?? StoreWhatsappIntegration::newToken(),
        ]);

        $result = $this->drivers->make($candidate)->verify();

        if (! $result['ok']) {
            throw ValidationException::withMessages(['driver' => $result['message']]);
        }

        StoreWhatsappIntegration::updateOrCreate(
            ['store_id' => $store->id],
            [
                'driver' => $candidate->driver,
                'credentials' => $candidate->credentials,
                'sender_phone' => $result['sender_phone'] ?? $existing?->sender_phone,
                'is_active' => true,
                'connected_at' => now(),
                'last_error' => null,
                // Kept across a key change: the merchant has already pasted this
                // URL into the gateway, and reissuing it would silently stop
                // every reply from arriving.
                'webhook_token' => $candidate->webhook_token,
                'verify_token' => $candidate->verify_token,
            ],
        );

        return back()->with('status', 'whatsapp-connected');
    }

    /** The wording, and whether a new order sends it by itself. */
    public function message(Request $request): RedirectResponse
    {
        $link = $this->linkOrFail($request);

        $validated = $request->validate([
            'message_template' => ['required', 'string', 'max:2000'],
            'auto_send' => ['required', 'boolean'],
            'template_name' => ['nullable', 'string', 'max:100'],
            'template_language' => ['nullable', 'string', 'max:12'],
        ], [
            'message_template.required' => 'الرسالة ماينفعش تفضل فاضية.',
        ]);

        $link->forceFill([
            'message_template' => $validated['message_template'],
            'auto_send' => $validated['auto_send'],
            'template_name' => $validated['template_name'] ?: null,
            'template_language' => $validated['template_language'] ?: 'ar',
        ])->save();

        return back()->with('status', 'whatsapp-message-saved');
    }

    /**
     * Send the real message to a number the merchant chooses.
     *
     * A genuine round-trip, and the only way to find out before a customer does
     * that a Cloud API template has not been approved yet.
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
        $result = $this->drivers->make($link)->send($phone, $message['body'], $message['variables']);

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
     * Forget the keys.
     *
     * The message log stays: it is the record of what customers were told, and
     * disconnecting a gateway is not a reason to lose it.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->linkOrFail($request)->delete();

        return back()->with('status', 'whatsapp-disconnected');
    }

    // ── private ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string,mixed>  $validated
     * @return array<string,string>
     */
    private function credentials(array $validated, ?StoreWhatsappIntegration $existing): array
    {
        $keep = $existing?->driver === $validated['driver'] ? (array) $existing->credentials : [];

        $fields = StoreWhatsappIntegration::CREDENTIAL_FIELDS[$validated['driver']] ?? [];

        $credentials = [];

        foreach ($fields as $field) {
            // A blank box means "leave it as it is" — merchants re-save this
            // form to change one thing, and cannot re-paste a token they were
            // shown once.
            $credentials[$field] = filled($validated[$field] ?? null)
                ? trim((string) $validated[$field])
                : (string) ($keep[$field] ?? '');
        }

        return array_filter($credentials, fn (string $value) => $value !== '');
    }

    /** @return array<string,int> */
    private function stats(Store $store): array
    {
        return [
            'sent' => $store->orders()->whereNotNull('whatsapp_sent_at')->count(),
            'awaiting' => $store->orders()->where('whatsapp_state', 'sent')->count(),
            'confirmed' => $store->orders()->where('whatsapp_state', 'confirmed')->count(),
            'cancelled' => $store->orders()->where('whatsapp_state', 'cancelled')->count(),
            'failed' => $store->orders()->where('whatsapp_state', 'failed')->count(),
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
