<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A store's own WhatsApp line.
 *
 * The number belongs to the merchant, not to the platform — the customer has to
 * recognise who is messaging them about an order they just placed.
 */
class StoreWhatsappIntegration extends Model
{
    use HasFactory;

    public const DRIVER_WAPILOT = 'wapilot';

    public const DRIVER_WHATS360 = 'whats360';

    public const DRIVER_CLOUD_API = 'cloud_api';

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

    /** Can this store message a customer right now? */
    public function canSend(): bool
    {
        if (! $this->is_active) {
            return false;
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
        return in_array($this->driver, [self::DRIVER_WAPILOT, self::DRIVER_WHATS360], true);
    }

    public function credential(string $key): ?string
    {
        $value = data_get($this->credentials, $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function template(): string
    {
        return filled($this->message_template) ? $this->message_template : self::DEFAULT_TEMPLATE;
    }

    public function webhookUrl(): string
    {
        return url("/api/integrations/whatsapp/webhook/{$this->webhook_token}");
    }
}
