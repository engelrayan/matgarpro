<?php

namespace App\Jobs;

use App\Support\Heartbeat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Proves a worker is taking jobs off one queue — see Heartbeat.
 *
 * Unique per queue, so a worker that is down for a day finds one of these
 * waiting when it comes back, not fourteen hundred.
 */
class QueueHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public readonly string $queueName)
    {
        $this->onQueue($queueName);
    }

    public function uniqueId(): string
    {
        return $this->queueName;
    }

    public function handle(): void
    {
        Heartbeat::beat(Heartbeat::queue($this->queueName));
    }
}
