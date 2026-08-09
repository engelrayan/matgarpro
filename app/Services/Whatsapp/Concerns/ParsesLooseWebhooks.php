<?php

namespace App\Services\Whatsapp\Concerns;

use App\Services\Whatsapp\InboundMessage;
use App\Support\Phone;
use Illuminate\Http\Request;

/**
 * Reading replies out of an undocumented webhook.
 *
 * The unofficial gateways all drive a real WhatsApp session and all describe an
 * incoming message a little differently — `from` or `chat_id` or `author`,
 * `body` or `text` or `message`, wrapped in `data` or not wrapped at all — and
 * none of them publish a schema that can be relied on between versions.
 *
 * So this reads the shapes rather than insisting on one, and the controller
 * logs anything it could not read. An unfamiliar payload is then something we
 * can see and add a key for, instead of a customer's answer disappearing.
 */
trait ParsesLooseWebhooks
{
    /** @return array<int,InboundMessage> */
    public function parseWebhook(Request $request): array
    {
        $payload = $request->all();

        $rows = data_get($payload, 'data.messages')
            ?? data_get($payload, 'messages')
            ?? data_get($payload, 'message')
            ?? data_get($payload, 'data')
            ?? [$payload];

        if (! is_array($rows)) {
            return [];
        }

        // A single message object rather than a list of them.
        if (! array_is_list($rows)) {
            $rows = [$rows];
        }

        $messages = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            /*
             | Our own outgoing messages come back on the same webhook. Echoing
             | them into the reply reader would confirm orders nobody answered —
             | the message we send ends with «ردّ بـ ١ … أو ٢», and «١» is in it.
             */
            if (filter_var(
                data_get($row, 'from_me') ?? data_get($row, 'fromMe') ?? data_get($row, 'self'),
                FILTER_VALIDATE_BOOL,
            )) {
                continue;
            }

            $from = (string) (data_get($row, 'from')
                ?? data_get($row, 'jid')
                ?? data_get($row, 'chat_id')
                ?? data_get($row, 'chatId')
                ?? data_get($row, 'sender')
                ?? data_get($row, 'author')
                ?? '');

            $body = data_get($row, 'body')
                ?? data_get($row, 'text')
                ?? data_get($row, 'message')
                ?? data_get($row, 'content');

            if ($from === '' || ! is_string($body) || trim($body) === '') {
                continue;
            }

            $messages[] = new InboundMessage(
                // `201006262330@c.us` or `…@s.whatsapp.net` — the digits are
                // all we ever match on.
                phone: Phone::e164(explode('@', $from)[0]),
                body: trim($body),
                providerMessageId: (string) (data_get($row, 'id')
                    ?? data_get($row, 'message_id')
                    ?? data_get($row, 'messageId')
                    ?? '') ?: null,
            );
        }

        return $messages;
    }
}
