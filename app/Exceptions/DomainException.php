<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A custom-domain operation the merchant can fix themselves. The message is
 * Arabic and actionable, because it is rendered straight into the settings UI.
 */
class DomainException extends RuntimeException
{
    public static function invalidFormat(string $domain): self
    {
        return new self("«{$domain}» مش دومين صحيح. اكتبه من غير http ومن غير أي مسار — مثال: mahmoud.com");
    }

    public static function blocked(string $domain): self
    {
        return new self("«{$domain}» دومين محجوز ومينفعش يتربط.");
    }

    public static function platformDomain(): self
    {
        return new self('ده دومين المنصة نفسها — متجرك عليه بالفعل من غير ما تعمل حاجة.');
    }

    public static function alreadyTaken(string $domain): self
    {
        return new self("«{$domain}» مربوط بمتجر تاني بالفعل. لو الدومين بتاعك، كلّم الدعم.");
    }

    public static function lastDomain(): self
    {
        return new self('مينفعش تشيل آخر دومين مربوط بالمتجر.');
    }

    public static function notServing(): self
    {
        return new self('مينفعش تخلّيه الدومين الأساسي قبل ما الـ DNS يبقى مظبوط.');
    }
}
