<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappMessage;
use App\Support\ArabicNumerals;

/**
 * Reads what the customer actually wrote.
 *
 * People do not answer «١». They answer «تمام يا فندم»، «اه اكد»، «لا مش عايز»,
 * or the same words with an emoji and no dots on the yaa. So this normalises
 * the way Arabic is really typed and then matches against a dictionary — no
 * model, no external call, and every decision is one somebody can read and
 * argue with. See the customer-sheet mapper for the same approach.
 *
 * The rule that matters most is the last one: anything it is not sure about
 * comes back `unknown`, and an unknown reply never moves an order. A wrongly
 * confirmed order ships to somebody who said no, and the merchant pays the
 * shipping twice to find out.
 */
class ReplyInterpreter
{
    /**
     * Phrases first, because they overrule the words inside them: «مش عايز»
     * contains «عايز», and reading that as a yes is the expensive mistake.
     */
    private const CANCEL_PHRASES = [
        'مش عايز', 'مش عاوز', 'مش محتاج', 'مش هاخد', 'مش هاخده', 'لغي الطلب',
        'الغي الطلب', 'عايز الغي', 'عاوز الغي', 'غيرت رايي', 'i changed my mind',
        'dont want', "don't want", 'not interested',
    ];

    private const CONFIRM_PHRASES = [
        'اكد الطلب', 'اكدت الطلب', 'عايز اكد', 'عاوز اكد', 'موافق علي الطلب',
        'go ahead',
    ];

    /** Single words, compared against whole tokens rather than substrings. */
    private const CANCEL_WORDS = [
        // `الغ` is what «ألغِ» becomes once the kasra is stripped — which is
        // exactly the word on Meta's own quick-reply button.
        '2', 'لا', 'لأ', 'الغاء', 'الغا', 'الغي', 'الغ', 'ملغي', 'كنسل', 'ارفض',
        'رفض', 'مرفوض', 'no', 'nope', 'n', 'cancel', 'canceled', 'cancelled', 'stop',
    ];

    private const CONFIRM_WORDS = [
        '1', 'تم', 'تمام', 'اكد', 'اكيد', 'تاكيد', 'موافق', 'ماشي', 'حاضر',
        'ايوه', 'ايوا', 'ايه', 'اه', 'نعم', 'اوك', 'اوكي', 'ok', 'okey', 'okay',
        'yes', 'yep', 'y', 'sure', 'confirm', 'confirmed',
    ];

    /** confirm | cancel | unknown */
    public function read(string $reply): string
    {
        $text = $this->normalise($reply);

        if ($text === '') {
            return WhatsappMessage::INTENT_UNKNOWN;
        }

        // A phrase decides on its own — it is longer and less ambiguous than any
        // single word inside it.
        if ($this->containsAny($text, self::CANCEL_PHRASES)) {
            return WhatsappMessage::INTENT_CANCEL;
        }

        if ($this->containsAny($text, self::CONFIRM_PHRASES)) {
            return WhatsappMessage::INTENT_CONFIRM;
        }

        $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $cancel = $this->matchesAny($tokens, self::CANCEL_WORDS);
        $confirm = $this->matchesAny($tokens, self::CONFIRM_WORDS);

        // «تمام بس لا» says both. Somebody has to read that one.
        if ($cancel === $confirm) {
            return WhatsappMessage::INTENT_UNKNOWN;
        }

        return $cancel ? WhatsappMessage::INTENT_CANCEL : WhatsappMessage::INTENT_CONFIRM;
    }

    /**
     * One spelling for text that has many.
     *
     * Arabic is typed with and without hamza, with ة or ه, with diacritics
     * pasted in from a keyboard that adds them, and with Arabic-Indic digits.
     * All of those are the same answer, so all of them normalise to one string.
     */
    private function normalise(string $value): string
    {
        $text = ArabicNumerals::toLatin(mb_strtolower(trim($value)));

        $text = strtr($text, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ة' => 'ه',
        ]);

        // Diacritics (U+064B–U+0652) and tatweel — decoration, never meaning.
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text) ?? $text;

        // Emoji, punctuation and anything else that is not a letter or a digit.
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /** @param array<int,string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int,string>  $tokens
     * @param  array<int,string>  $words
     */
    private function matchesAny(array $tokens, array $words): bool
    {
        return array_intersect($tokens, $words) !== [];
    }
}
