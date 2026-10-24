<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A merchant's claim to have transferred money for their balance — see the
 * migration. Moves nothing by itself; TopupService does, on confirmation.
 */
class StoreTopupRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    /** Withdrawn by the merchant before anybody looked at it. */
    public const STATUS_CANCELLED = 'cancelled';

    public const METHOD_INSTAPAY = 'instapay';

    protected $fillable = [
        'store_id',
        'user_id',
        'amount',
        'confirmed_amount',
        'method',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'wallet_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confirmed_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public static function statusLabel(string $status): string
    {
        return [
            self::STATUS_PENDING => 'مستني التأكيد',
            self::STATUS_CONFIRMED => 'اتضاف لرصيدك',
            self::STATUS_REJECTED => 'اترفض',
            self::STATUS_CANCELLED => 'اتلغى',
        ][$status] ?? $status;
    }
}
