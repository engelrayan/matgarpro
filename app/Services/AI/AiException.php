<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Anything that stops an AI feature from answering.
 *
 * The message is always written for the merchant — plain Egyptian Arabic,
 * no status codes — because it is shown on their screen as-is. What went
 * wrong technically is in ai_calls.error and the log, not here.
 */
class AiException extends RuntimeException
{
    public function __construct(
        string $message,
        /** Worth trying again in a moment: the API was busy or unreachable. */
        public readonly bool $retryable = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('الخدمة دي مش متاحة دلوقتي. جرّب تاني بعدين.');
    }

    public static function dailyLimit(): self
    {
        return new self('خلصت المحاولات بتاعة النهارده. تقدر تجرّب تاني بكرة.');
    }

    public static function busy(?\Throwable $previous = null): self
    {
        return new self('الخدمة عليها ضغط دلوقتي. جرّب تاني كمان شوية.', true, $previous);
    }

    public static function failed(?\Throwable $previous = null): self
    {
        return new self('حصلت مشكلة وإحنا بنكتب. جرّب تاني.', false, $previous);
    }

    public static function refused(): self
    {
        return new self('مقدرناش نعمل الطلب ده. جرّب تاني بصور أو كلام مختلف.');
    }
}
