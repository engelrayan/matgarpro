<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;

/**
 * What every WhatsApp gateway has to be able to do.
 *
 * Kept to four things on purpose. Anything a particular gateway can do beyond
 * this — media, groups, read receipts — is not something the confirmation flow
 * asks for, and putting it here would make the next driver harder to write than
 * it needs to be.
 */
interface WhatsappDriver
{
    /**
     * Send one message to one customer.
     *
     * `$phone` arrives as E.164 digits with no plus; each driver formats it the
     * way its own API wants.
     *
     * `$variables` carries the same values already interpolated into `$body`,
     * in order — Meta's templates take parameters rather than a finished string,
     * so a driver that needs them has them, and one that does not ignores them.
     *
     * @param  array<int,string>  $variables
     */
    public function send(string $phone, string $body, array $variables = []): SendResult;

    /**
     * Check the credentials against the gateway.
     *
     * A real round-trip, not a format check: a token can be perfectly
     * well-formed and still revoked, and that is the failure merchants hit.
     *
     * @return array{ok: bool, message: string}
     */
    public function verify(): array;

    /**
     * The gateway's subscription handshake, if it has one.
     *
     * Meta subscribes to a webhook by calling it with a challenge it expects
     * echoed back. Gateways without such a step return null and the request is
     * treated as an ordinary delivery.
     */
    public function webhookChallenge(Request $request): ?string;

    /**
     * Pull the customer replies out of a webhook body.
     *
     * @return array<int,InboundMessage>
     */
    public function parseWebhook(Request $request): array;

    /**
     * Is this webhook really from the gateway?
     *
     * A driver with no signature scheme returns true — the unguessable token in
     * the URL is then the only thing standing between a stranger and a
     * cancelled order, which is exactly what the settings screen warns about.
     */
    public function verifyWebhookSignature(Request $request): bool;
}
