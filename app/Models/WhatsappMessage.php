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

    protected $fillable = [
        'store_id', 'order_id', 'direction', 'phone', 'body',
        'provider_message_id', 'status', 'intent', 'error',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
