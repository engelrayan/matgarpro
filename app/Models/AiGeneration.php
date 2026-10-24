<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * AI work a merchant is waiting for on screen — see the migration.
 */
class AiGeneration extends Model
{
    use HasUlids;
    use Prunable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /**
     * After this long without an answer the merchant is told to try again.
     *
     * Covers a worker that is down or a job that died without reaching its
     * failed() hook; without it the browser would wait on a spinner forever.
     */
    public const EXPIRES_AFTER_MINUTES = 5;

    protected $fillable = [
        'store_id',
        'user_id',
        'feature',
        'status',
        'input',
        'result',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'input' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    public function hasExpired(): bool
    {
        return ! $this->isFinished()
            && $this->created_at->lt(now()->subMinutes(self::EXPIRES_AFTER_MINUTES));
    }

    public function markRunning(): void
    {
        $this->update(['status' => self::STATUS_RUNNING, 'started_at' => $this->started_at ?? now()]);
    }

    /** @param array<string,mixed> $result */
    public function markDone(array $result): void
    {
        $this->update(['status' => self::STATUS_DONE, 'result' => $result, 'error' => null, 'finished_at' => now()]);
    }

    public function markFailed(string $message): void
    {
        $this->update(['status' => self::STATUS_FAILED, 'error' => $message, 'finished_at' => now()]);
    }

    /**
     * Where this generation's uploaded inputs live until the job has read them.
     *
     * On the private disk: they are the merchant's unpublished product photos.
     */
    public function inputDirectory(): string
    {
        return "ai-inputs/{$this->id}";
    }

    public function deleteInputs(): void
    {
        Storage::disk('local')->deleteDirectory($this->inputDirectory());
    }

    /**
     * Drafts are only useful for the minutes the merchant is on the form. A
     * month is plenty for "what did the AI write for this store?" questions.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(30));
    }

    /** A job that never ran never cleaned up after itself. */
    protected function pruning(): void
    {
        $this->deleteInputs();
    }
}
