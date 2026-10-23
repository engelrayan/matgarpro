<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One request to Claude, metered.
 *
 * Written only by ClaudeGateway. `cost_usd` is an estimate frozen at the time
 * of the call; see the migration for why it is never recomputed.
 */
class AiCall extends Model
{
    public const STATUS_OK = 'ok';

    /** The model declined. Still billed by Anthropic, so still counted. */
    public const STATUS_REFUSED = 'refused';

    /** Ran out of max_tokens mid-answer. Billed, unusable. */
    public const STATUS_INCOMPLETE = 'incomplete';

    /** The request never produced an answer (network, 5xx, bad key). */
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'store_id',
        'feature',
        'model',
        'status',
        'input_tokens',
        'output_tokens',
        'cache_write_tokens',
        'cache_read_tokens',
        'cost_usd',
        'duration_ms',
        'request_id',
        'error',
        'subject_type',
        'subject_id',
        'store_usage_event_id',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cache_write_tokens' => 'integer',
        'cache_read_tokens' => 'integer',
        'cost_usd' => 'decimal:6',
        'duration_ms' => 'integer',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function usageEvent(): BelongsTo
    {
        return $this->belongsTo(StoreUsageEvent::class, 'store_usage_event_id');
    }
}
