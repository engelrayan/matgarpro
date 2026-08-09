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

    public const DRIVER_CLOUD_API = 'cloud_api';

    /** What a merchant has to fill in, per gateway. */
    public const CREDENTIAL_FIELDS = [
        self::DRIVER_WAPILOT => ['token', 'instance'],
        self::DRIVER_CLOUD_API => ['access_token', 'phone_number_id', 'app_secret'],
    ];

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
            // The app secret only verifies inbound signatures; a store can send
            // perfectly well without it.
            if ($field !== 'app_secret' && blank($this->credential($field))) {
                return false;
            }
        }

        return true;
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
