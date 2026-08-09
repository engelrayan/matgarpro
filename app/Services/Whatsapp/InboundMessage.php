<?php

namespace App\Services\Whatsapp;

/**
 * One inbound message, in the shape the rest of the app understands.
 *
 * Every gateway describes a reply differently — Meta nests it four levels deep
 * and distinguishes a tapped button from typed text; an unofficial gateway
 * sends something flatter and less documented. Both are flattened to this so
 * nothing downstream has to know which one is connected.
 */
class InboundMessage
{
    public function __construct(
        public readonly string $phone,
        public readonly string $body,
        public readonly ?string $providerMessageId = null,
        /** True when the customer tapped a quick-reply button rather than typing. */
        public readonly bool $fromButton = false,
    ) {}
}
