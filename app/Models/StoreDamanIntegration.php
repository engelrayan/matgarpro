<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A store's link to Daman.
 *
 * Daman is the merchant's wakil: it holds their contracts with the actual
 * carriers, so a store that links here does not need an account with any
 * courier — it hands Daman a confirmed order and gets back a waybill.
 */
class StoreDamanIntegration extends Model
{
    use HasFactory;

    public const ENV_TEST = 'test';

    public const ENV_LIVE = 'live';

    protected $fillable = [
        'store_id', 'api_key', 'key_prefix', 'environment', 'is_active',
        'cod_includes_shipping', 'webhook_token', 'webhook_secret',
        'connected_at', 'last_shipped_at', 'last_webhook_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            // Laravel's built-in encryption. Both of these can move a
            // merchant's money — the key creates real shipments, the secret is
            // what we verify inbound status changes against.
            'api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
            'cod_includes_shipping' => 'boolean',
            'connected_at' => 'datetime',
            'last_shipped_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    /** Never serialise the credentials, whatever a controller forgets to strip. */
    protected $hidden = ['api_key', 'webhook_secret'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Which Daman environment a key talks to.
     *
     * Read off the key rather than asked for: a test key never dispatches to a
     * real carrier, and a merchant who mislabelled one would spend a day
     * believing parcels were on their way.
     */
    public static function environmentFor(string $apiKey): string
    {
        return str_starts_with($apiKey, 'dm_test_') ? self::ENV_TEST : self::ENV_LIVE;
    }

    /** The unguessable path segment Daman posts status changes to. */
    public static function newWebhookToken(): string
    {
        return Str::random(40);
    }

    public function isLive(): bool
    {
        return $this->environment === self::ENV_LIVE;
    }

    /** Can this store hand an order over right now? */
    public function canShip(): bool
    {
        return $this->is_active && filled($this->api_key);
    }

    public function webhookUrl(): string
    {
        return url("/api/integrations/daman/webhook/{$this->webhook_token}");
    }
}
