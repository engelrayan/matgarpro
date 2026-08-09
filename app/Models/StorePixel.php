<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePixel extends Model
{
    use HasFactory;

    public const PROVIDER_META = 'meta';

    protected $fillable = [
        'store_id', 'provider', 'pixel_id', 'access_token', 'test_event_code',
        'is_active', 'last_event_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            // Laravel's built-in encryption. The token can post conversions to
            // the merchant's ad account — it never leaves the server in clear.
            'access_token' => 'encrypted',
            'is_active' => 'boolean',
            'last_event_at' => 'datetime',
        ];
    }

    /** Never serialise the token, whatever a controller forgets to strip. */
    protected $hidden = ['access_token'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Can this pixel send server-side events?
     *
     * A pixel with no token still works in the browser — it just cannot
     * survive ad blockers. That is a degraded state, not a broken one, so the
     * two are tracked separately.
     */
    public function canSendServerSide(): bool
    {
        return $this->is_active && filled($this->access_token);
    }
}
