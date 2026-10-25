<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * "When did this last do anything?" for the parts of the app that run
 * without anybody watching: the scheduler and each queue's worker.
 *
 * A stopped worker otherwise has no symptom of its own — orders still come
 * in, the WhatsApp confirmations quietly stop going out, and the first sign
 * is a merchant asking why. The scheduler beats every minute and sends each
 * queue a job that beats when a worker picks it up; the health page reads
 * how long ago each one last did.
 */
class Heartbeat
{
    public const SCHEDULER = 'scheduler';

    public static function beat(string $name): void
    {
        Cache::forever(self::key($name), now()->getTimestamp());
    }

    public static function last(string $name): ?CarbonImmutable
    {
        $at = Cache::get(self::key($name));

        return is_numeric($at) ? CarbonImmutable::createFromTimestamp((int) $at) : null;
    }

    public static function queue(string $queue): string
    {
        return "queue:{$queue}";
    }

    private static function key(string $name): string
    {
        return "heartbeat:{$name}";
    }
}
