<?php

namespace App\Models;

use App\Services\Whatsapp\PlatformWhatsapp;
use App\Services\Whatsapp\Waha\WahaClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * How a store messages its customers on WhatsApp.
 *
 * Either the merchant's own line, through a gateway they hold keys for, or the
 * platform's shared official number (`DRIVER_PLATFORM`), which needs nothing
 * from them and names their shop in the message instead.
 */
class StoreWhatsappIntegration extends Model
{
    use HasFactory;

    public const DRIVER_WAPILOT = 'wapilot';

    public const DRIVER_WHATS360 = 'whats360';

    public const DRIVER_CLOUD_API = 'cloud_api';

    /**
     * The platform's own official number. Not in DRIVERS: there are no keys to
     * paste, so it is switched on from its own button rather than the
     * gateway form, and the form can never select it.
     */
    public const DRIVER_PLATFORM = 'platform';

    /**
     * The store's own number through our self-hosted WAHA gateway, linked by
     * scanning a QR code. Not in DRIVERS either: nothing to paste, and its
     * one credential — the session's last status — is ours to write.
     */
    public const DRIVER_WAHA = 'waha';

    /** The gateways a merchant connects with their own keys. */
    public const DRIVERS = [self::DRIVER_WAPILOT, self::DRIVER_WHATS360, self::DRIVER_CLOUD_API];

    /**
     * What a merchant has to fill in, per gateway.
     *
     * Fields listed in `OPTIONAL_CREDENTIALS` may be left blank — everything
     * else has to be there before the store can send.
     */
    public const CREDENTIAL_FIELDS = [
        self::DRIVER_WAPILOT => ['token', 'instance'],
        self::DRIVER_WHATS360 => ['token', 'instance_id', 'base_url'],
        self::DRIVER_CLOUD_API => ['access_token', 'phone_number_id', 'app_secret'],
    ];

    /**
     * `app_secret` only verifies inbound signatures, and `base_url` falls back
     * to the gateway's own host — a store sends perfectly well without either.
     */
    public const OPTIONAL_CREDENTIALS = ['app_secret', 'base_url'];

    /** Everything the message can say about the order. */
    public const PLACEHOLDERS = [
        '{store}' => 'اسم المتجر',
        '{name}' => 'اسم العميل',
        '{number}' => 'رقم الطلب',
        '{items}' => 'المنتجات',
        '{total}' => 'الإجمالي',
        '{currency}' => 'العملة',
    ];

    public const DEFAULT_TEMPLATE = "أهلاً {name} 👋\n"
        . "طلبك رقم #{number} من {store} وصلنا:\n"
        . "{items}\n"
        . "الإجمالي: {total} {currency} — الدفع عند الاستلام\n\n"
        . "ردّ بـ ١ عشان نأكّد الطلب، أو ٢ لو عايز تلغيه.";

    /** On WAHA the reply is a poll under the message, so the message says to tap it. */
    public const WAHA_TEMPLATE = "أهلاً {name} 👋\n"
        . "طلبك رقم #{number} من {store} وصلنا:\n"
        . "{items}\n"
        . "الإجمالي: {total} {currency} — الدفع عند الاستلام\n\n"
        . 'اختار «تأكيد الطلب» من الاستطلاع اللي تحت عشان نجهّزه ونشحنه.';

    protected $fillable = [
        'store_id', 'driver', 'credentials', 'sender_phone', 'is_active',
        'auto_send', 'message_template', 'template_name', 'template_language',
        'webhook_token', 'verify_token', 'connected_at', 'last_sent_at',
        'last_inbound_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            // Laravel's built-in encryption. A token here sends from the
            // merchant's own number — it never leaves the server in clear.
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'auto_send' => 'boolean',
            'connected_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'last_inbound_at' => 'datetime',
        ];
    }

    /** Never serialise the credentials, whatever a controller forgets to strip. */
    protected $hidden = ['credentials', 'verify_token'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public function isCloudApi(): bool
    {
        return $this->driver === self::DRIVER_CLOUD_API;
    }

    public function isPlatform(): bool
    {
        return $this->driver === self::DRIVER_PLATFORM;
    }

    public function isWaha(): bool
    {
        return $this->driver === self::DRIVER_WAHA;
    }

    /** Can this store message a customer right now? */
    public function canSend(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isPlatform()) {
            return PlatformWhatsapp::configured();
        }

        // Only while the scanned number is actually linked: a logged-out
        // session would take the order's one confirmation and drop it.
        if ($this->isWaha()) {
            return WahaClient::configured() && $this->credential('status') === WahaClient::WORKING;
        }

        foreach (self::CREDENTIAL_FIELDS[$this->driver] ?? [] as $field) {
            if (! in_array($field, self::OPTIONAL_CREDENTIALS, true) && blank($this->credential($field))) {
                return false;
            }
        }

        return true;
    }

    /** True for the gateways that drive a real WhatsApp session over QR. */
    public function isSessionGateway(): bool
    {
        return in_array($this->driver, [self::DRIVER_WAPILOT, self::DRIVER_WHATS360, self::DRIVER_WAHA], true);
    }

    public function credential(string $key): ?string
    {
        $value = data_get($this->credentials, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function template(): string
    {
        // Fixed on the platform number: the words are the template Meta
        // approved, and a merchant's own wording would never be delivered.
        if ($this->isPlatform()) {
            return PlatformWhatsapp::BODY;
        }

        if (filled($this->message_template)) {
            return $this->message_template;
        }

        return $this->isWaha() ? self::WAHA_TEMPLATE : self::DEFAULT_TEMPLATE;
    }

    public function webhookUrl(): string
    {
        return url("/api/integrations/whatsapp/webhook/{$this->webhook_token}");
    }
}
