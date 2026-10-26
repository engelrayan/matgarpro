<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_RETURNED = 'returned';

    /** Ordered as the merchant reads them, which is also the tab order. */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
        self::STATUS_RETURNED,
    ];

    protected $fillable = [
        'store_id', 'number', 'customer_name', 'customer_phone', 'customer_phone_alt',
        'customer_email', 'governorate', 'city', 'address', 'note', 'subtotal',
        'shipping_amount', 'discount_amount', 'total', 'status', 'payment_method',
        'ip', 'user_agent', 'tracking', 'locked_at',
    ];


    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'tracking' => 'array',
            'daman_sent_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
            'whatsapp_reminded_at' => 'datetime',
            'whatsapp_replied_at' => 'datetime',
            'locked_at' => 'datetime',
            'waybill_printed_at' => 'datetime',
        ];
    }

    /**
     * Arrived while the store was past its credit floor: real, but its
     * customer details stay hidden from the merchant until a top-up covers it.
     * See OrderUnlocker.
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** Orders the merchant can see in full. */
    public function scopeVisible($query)
    {
        return $query->whereNull('locked_at');
    }

    /** Orders waiting on a top-up. */
    public function scopeLocked($query)
    {
        return $query->whereNotNull('locked_at');
    }

    /** Messaged the customer and still waiting on their answer. */
    public function isAwaitingWhatsappReply(): bool
    {
        return $this->whatsapp_state === 'sent';
    }

    /** Has this order been handed over to Daman? */
    public function isWithDaman(): bool
    {
        return $this->daman_shipment_id !== null;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** The carrier payment that settled this order's cash; null while the carrier still holds it. */
    public function carrierCollection(): BelongsTo
    {
        return $this->belongsTo(CarrierCollection::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // -------------------------------------------------------------------------

    /** Statuses after which stock has left the building. */
    public function isOpen(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_DELIVERED, self::STATUS_CANCELLED, self::STATUS_RETURNED,
        ], true);
    }

    public function statusLabel(): string
    {
        return [
            self::STATUS_PENDING => 'قيد المراجعة',
            self::STATUS_CONFIRMED => 'تم التأكيد',
            self::STATUS_SHIPPED => 'تم الشحن',
            self::STATUS_DELIVERED => 'تم التوصيل',
            self::STATUS_CANCELLED => 'ملغي',
            self::STATUS_RETURNED => 'مرتجع',
        ][$this->status] ?? $this->status;
    }
}
