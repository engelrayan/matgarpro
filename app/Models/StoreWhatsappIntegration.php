<?php

namespace App\Models;

use App\Services\Whatsapp\PlatformWhatsapp;
use App\Services\Whatsapp\Waha\WahaClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * How a store messages its customers on WhatsApp — one of two ways:
 *
 *  - the platform's shared official number (DRIVER_PLATFORM), a service the
 *    merchant switches on or off; it needs nothing from them and names their
 *    shop in the message;
 *  - the store's own number (DRIVER_WAHA), linked by scanning a QR code,
 *    which also carries the inbox.
 *
 * Third-party gateways (Wapilot, Whats360, a merchant's own Cloud API keys)
 * were dropped: two ways to choose between is a decision, five was a form.
 */
class StoreWhatsappIntegration extends Model
{
    use HasFactory;

    public const DRIVER_PLATFORM = 'platform';

    public const DRIVER_WAHA = 'waha';

    /**
     * Not a store's choice: the transport PlatformDriver speaks, through Meta's
     * Cloud API, with the platform's keys in an unsaved link.
     */
    public const DRIVER_CLOUD_API = 'cloud_api';

    /** Everything the message can say about the order. */
    public const PLACEHOLDERS = [
        '{store}' => 'اسم المتجر',
        '{name}' => 'اسم العميل',
        '{number}' => 'رقم الطلب',
        '{items}' => 'المنتجات',
        '{total}' => 'الإجمالي',
        '{currency}' => 'العملة',
    ];

    /** One message: a poll whose title is this, so it ends by asking for the tap. */
    public const WAHA_TEMPLATE = "أهلاً {name} 👋\n"
        . "طلبك رقم #{number} ({items}) من متجر {store} وصلنا "
        . "بإجمالي {total} {currency} — الدفع عند الاستلام.\n"
        . 'برجاء تأكيد الطلب 👇';

    protected $fillable = [
        'store_id', 'driver', 'credentials', 'sender_phone', 'is_active',
        'auto_send', 'message_template', 'template_name', 'template_language',
        'webhook_token', 'verify_token', 'connected_at', 'last_sent_at',
        'last_inbound_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted, though all it holds now is the WAHA session's last
            // status: whatever lands in it later stays out of the database
            // in clear.
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
        return $this->isWaha()
            && WahaClient::configured()
            && $this->credential('status') === WahaClient::WORKING;
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

        return self::WAHA_TEMPLATE;
    }

    public function webhookUrl(): string
    {
        return url("/api/integrations/whatsapp/webhook/{$this->webhook_token}");
    }
}
