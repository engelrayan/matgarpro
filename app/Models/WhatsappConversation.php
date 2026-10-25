<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Everything said with one customer number, in one place.
 *
 * Never created by hand: a message files itself here when it is written (see
 * WhatsappMessage::booted), so the confirmation job, the acknowledgement, a
 * reply typed in the inbox and one typed on the phone all land in the same
 * thread without any of them knowing the inbox exists.
 */
class WhatsappConversation extends Model
{
    /** The tags a merchant picks from; any other word is allowed too. */
    public const SUGGESTED_TAGS = ['عميل جديد', 'مهم', 'متابعة', 'شكوى', 'استرجاع', 'تم الحل'];

    protected $fillable = ['store_id', 'phone', 'name', 'tags', 'unread_count', 'summary', 'summary_at'];

    protected $casts = [
        'tags' => 'array',
        'last_message_at' => 'datetime',
        'summary_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsappConversationNote::class, 'conversation_id');
    }

    public static function resolve(int $storeId, string $phone): self
    {
        return self::firstOrCreate(['store_id' => $storeId, 'phone' => $phone]);
    }

    /**
     * The store's orders from this number — matched on the last nine digits,
     * like replies are, because the order holds whatever the customer typed.
     */
    public function orders(): Builder
    {
        return Order::query()
            ->where('store_id', $this->store_id)
            ->whereRaw('RIGHT(REPLACE(customer_phone, " ", ""), 9) = ?', [substr($this->phone, -9)]);
    }

    /** Called for every message written into this conversation. */
    public function record(WhatsappMessage $message): void
    {
        $this->forceFill([
            'last_message_at' => $message->created_at ?? now(),
            'last_preview' => Str::limit($message->preview(), 250, '…'),
            'last_direction' => $message->direction,
            'unread_count' => $message->direction === WhatsappMessage::DIRECTION_IN
                ? $this->unread_count + 1
                : $this->unread_count,
            'name' => $this->name ?: $this->nameFor($message),
        ])->save();
    }

    /** The orders' name for this customer, or the name on their WhatsApp. */
    private function nameFor(WhatsappMessage $message): ?string
    {
        $fromOrder = $this->orders()->latest('id')->value('customer_name');

        return $fromOrder ?: (data_get($message->meta, 'push_name') ?: null);
    }
}
