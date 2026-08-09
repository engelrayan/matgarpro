<?php

namespace App\Services\Whatsapp;

/** What a gateway said when we handed it a message. */
class SendResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
        /**
         * Whether trying again could plausibly work. A revoked token or an
         * unapproved template will be refused identically forever, and retrying
         * those just delays the merchant finding out.
         */
        public readonly bool $retryable = false,
    ) {}

    public static function sent(?string $providerMessageId = null): self
    {
        return new self(true, $providerMessageId);
    }

    public static function failed(string $error, bool $retryable = false): self
    {
        return new self(false, null, $error, $retryable);
    }
}
