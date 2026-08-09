<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One page's layout, in two states: what the merchant is editing, and what
 * customers are being served.
 *
 * Nothing outside {@see \App\Services\Builder\PageBuilder} should write these
 * columns — the builder is what guarantees a saved section list has been put
 * back together from the schema rather than trusted as it arrived.
 */
class StorePage extends Model
{
    protected $fillable = ['store_id', 'key', 'draft_sections', 'published_sections', 'published_at'];

    protected function casts(): array
    {
        return [
            'draft_sections' => 'array',
            'published_sections' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Is there anything unpublished sitting in the draft? */
    public function hasUnpublishedChanges(): bool
    {
        if ($this->draft_sections === null) {
            return false;
        }

        // Compared as encoded JSON: a plain === on two decoded arrays is true
        // only when the keys are in the same order too, which they need not be
        // after a round-trip through the builder.
        return json_encode($this->draft_sections) !== json_encode($this->published_sections);
    }
}
