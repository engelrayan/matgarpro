<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappMessage;

/**
 * The platform's own official WhatsApp number, shared by every store that
 * switches it on.
 *
 * The merchant sets up nothing: no gateway account, no keys, no template to
 * get approved. The price is that the message comes from «متجر برو» rather
 * than from the shop — so the shop's name is in the first line of it, where
 * the customer looks to recognise who is writing.
 */
class PlatformWhatsapp
{
    /**
     * What the customer reads, with our placeholders. It mirrors the template
     * approved by Meta word for word — see `templateDefinition()` — and is what
     * the message log shows, so the merchant sees exactly what was sent.
     */
    public const BODY = "أهلاً {name} 👋\n"
        . "طلبك رقم #{number} من {store} وصلنا:\n"
        . "{items}\n"
        . "الإجمالي: {amount} — الدفع عند الاستلام.\n\n"
        . 'دوس «تأكيد الطلب» عشان نجهّزه ونشحنه، أو «إلغاء الطلب» لو غيّرت رأيك.';

    /** The order the template's {{1}}…{{5}} take. */
    public const VARIABLE_ORDER = ['{name}', '{number}', '{store}', '{items}', '{amount}'];

    public const CONFIRM_LABEL = 'تأكيد الطلب';

    public const CANCEL_LABEL = 'إلغاء الطلب';

    /** Can stores use it right now? Without these the option is not offered. */
    public static function configured(): bool
    {
        return filled(config('services.whatsapp.platform.access_token'))
            && filled(config('services.whatsapp.platform.phone_number_id'))
            && filled(config('services.whatsapp.platform.app_secret'));
    }

    public static function template(): string
    {
        return (string) config('services.whatsapp.platform.template');
    }

    public static function language(): string
    {
        return (string) (config('services.whatsapp.platform.template_language') ?: 'ar');
    }

    /** Sent the moment a customer taps, so they know the tap did something. */
    public static function acknowledgement(string $intent, string $number, string $store): string
    {
        return $intent === WhatsappMessage::INTENT_CONFIRM
            ? "تمام ✅ طلبك رقم #{$number} من {$store} اتأكد، وهيتجهّز للشحن."
            : "تم إلغاء طلبك رقم #{$number} من {$store}. لو ده حصل بالغلط، اطلب تاني من المتجر في أي وقت.";
    }

    /**
     * The template as Meta wants it created — UTILITY, Arabic, two
     * quick-reply buttons. Used once, by `whatsapp:platform-template`.
     *
     * @return array<string,mixed>
     */
    public static function templateDefinition(): array
    {
        $text = strtr(self::BODY, array_combine(
            self::VARIABLE_ORDER,
            array_map(fn (int $i) => '{{' . $i . '}}', range(1, count(self::VARIABLE_ORDER))),
        ));

        return [
            'name' => self::template(),
            'language' => self::language(),
            // Utility, not marketing: it is about an order the customer just
            // placed, which is what Meta's utility category is for — and what
            // keeps it deliverable to people who never opted into promotions.
            'category' => 'UTILITY',
            'components' => [
                [
                    'type' => 'BODY',
                    'text' => $text,
                    // Meta reviews a template against an example of each value.
                    'example' => ['body_text' => [['محمود', '1024', 'متجر الأناقة', '• 1× قميص قطن (L)', '450.00 EGP']]],
                ],
                ['type' => 'FOOTER', 'text' => 'رسالة آلية من متجر برو نيابة عن المتجر'],
                [
                    'type' => 'BUTTONS',
                    'buttons' => [
                        ['type' => 'QUICK_REPLY', 'text' => self::CONFIRM_LABEL],
                        ['type' => 'QUICK_REPLY', 'text' => self::CANCEL_LABEL],
                    ],
                ],
            ],
        ];
    }
}
