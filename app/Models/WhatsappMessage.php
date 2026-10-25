<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One message, in or out. See the table's migration for why it is kept. */
class WhatsappMessage extends Model
{
    use HasFactory;

    public const DIRECTION_OUT = 'out';

    public const DIRECTION_IN = 'in';

    public const INTENT_CONFIRM = 'confirm';

    public const INTENT_CANCEL = 'cancel';

    public const INTENT_UNKNOWN = 'unknown';

    public const KIND_TEXT = 'text';

    /** Our confirmation, as a WhatsApp poll; `meta.options` holds the choices. */
    public const KIND_POLL = 'poll';

    /** The customer's choice in a poll, or a tapped button. */
    public const KIND_VOTE = 'vote';

    /** A photo, voice note or file — kept as a line saying so, for now. */
    public const KIND_MEDIA = 'media';

    protected $fillable = [
        'store_id', 'order_id', 'conversation_id', 'user_id', 'direction', 'kind',
        'phone', 'body', 'meta', 'provider_message_id', 'status', 'intent', 'error',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Every message files itself into its conversation, whoever wrote it.
     * That is what keeps the inbox complete without each sender knowing it
     * exists.
     */
    protected static function booted(): void
    {
        static::creating(function (self $message) {
            if (! $message->conversation_id && $message->store_id && $message->phone) {
                $message->conversation_id = WhatsappConversation::resolve($message->store_id, $message->phone)->id;
            }

            // WAHA echoes our own sends back as "written on the phone", and the
            // echo can beat us to the table. Ours carries the order and who
            // sent it, so it replaces the echo rather than sitting beside it.
            $key = self::providerKey($message->provider_message_id);

            if ($key && $message->direction === self::DIRECTION_OUT && data_get($message->meta, 'source') !== 'phone') {
                self::query()
                    ->where('conversation_id', $message->conversation_id)
                    ->where('meta->source', 'phone')
                    ->where('provider_message_id', 'like', '%' . $key)
                    ->delete();
            }
        });

        static::created(function (self $message) {
            $message->conversation?->record($message);
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** One line for the conversation list. */
    public function preview(): string
    {
        return match ($this->kind) {
            self::KIND_POLL => '📊 ' . strtok((string) $this->body, "\n"),
            self::KIND_VOTE => 'اختار: ' . $this->body,
            default => (string) $this->body,
        };
    }

    /**
     * WhatsApp's own id ends in the message's key; the part before it names
     * the chat, which WhatsApp may spell as a number or as a hidden @lid id.
     * The key alone is what identifies the same message both ways.
     */
    public static function providerKey(?string $id): ?string
    {
        if (! $id) {
            return null;
        }

        $key = substr($id, (int) strrpos($id, '_') + (str_contains($id, '_') ? 1 : 0));

        return $key !== '' ? $key : null;
    }
}
