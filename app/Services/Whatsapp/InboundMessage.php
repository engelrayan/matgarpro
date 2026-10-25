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
        /** The payload we attached to the button they tapped, if any. */
        public readonly ?string $payload = null,
        /** The provider id of our message this one replies to, if the gateway says. */
        public readonly ?string $replyTo = null,
        /** The name on the customer's WhatsApp profile, when the gateway sends it. */
        public readonly ?string $pushName = null,
        /** text | vote | media — see WhatsappMessage's KIND_ constants. */
        public readonly ?string $kind = null,
        /**
         * Written by the store itself on the linked phone (WAHA). Not a reply:
         * it goes into the conversation as an outgoing message and decides
         * nothing about any order.
         */
        public readonly bool $fromMe = false,
    ) {}
}
