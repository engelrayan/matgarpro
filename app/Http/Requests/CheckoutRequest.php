<?php

namespace App\Http\Requests;

use App\Models\Store;
use App\Support\ArabicNumerals;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Numbers are normalised BEFORE validation, not after.
     *
     * Validating the typed value and normalising afterwards let an order
     * through whose phone reduced to an empty string — a cash-on-delivery
     * order nobody can ever call. The rules below now see exactly what will
     * be stored.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_phone' => $this->normalisePhone((string) $this->input('customer_phone')),
            'customer_phone_alt' => $this->filled('customer_phone_alt')
                ? $this->normalisePhone((string) $this->input('customer_phone_alt'))
                : null,
        ]);
    }

    public function rules(): array
    {
        $rules = [
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];

        /*
         | Customer fields come from the store's own form settings. Rules are
         | built from the same source the storefront renders from, so a field
         | the merchant switched off cannot be smuggled in by posting it, and
         | one they marked required cannot be skipped by editing the HTML.
         */
        foreach ($this->store()->enabledCheckoutFields() as $key => $field) {
            $rules[$key] = array_merge(
                [$field['required'] ? 'required' : 'nullable'],
                $this->typeRulesFor($key),
            );
        }

        return $rules;
    }

    /** @return array<int,string> */
    private function typeRulesFor(string $key): array
    {
        return match ($key) {
            // Digits only by this point. 8–15 covers Egyptian mobiles and
            // landlines without rejecting a buyer over formatting.
            'customer_phone', 'customer_phone_alt' => ['string', 'regex:/^\d{8,15}$/'],
            'customer_email' => ['email', 'max:255'],
            'address', 'note' => ['string', 'max:1000'],
            default => ['string', 'max:255'],
        };
    }

    public function messages(): array
    {
        $messages = [
            'customer_phone.regex' => 'رقم التليفون مش مظبوط. اكتبه بالأرقام — مثال: 01006262330',
            'customer_phone_alt.regex' => 'الرقم البديل مش مظبوط.',
            'quantity.min' => 'الكمية لازم تكون ١ على الأقل.',
        ];

        // "اكتب اسمك" reads better than "حقل اسمك مطلوب", and the merchant may
        // have renamed the field, so the label has to come from their settings.
        foreach ($this->store()->enabledCheckoutFields() as $key => $field) {
            $messages["{$key}.required"] = "اكتب {$field['label']}.";
        }

        return $messages;
    }

    private function store(): Store
    {
        return $this->attributes->get('store') ?? app(Store::class);
    }

    /**
     * Reduce a typed phone number to digits.
     *
     * Arabic-Indic digits (٠١٢…) are converted first: phone keyboards in Arabic
     * locales produce them, and they would otherwise be stripped as non-digits,
     * leaving nothing behind.
     */
    private function normalisePhone(string $phone): string
    {
        $digits = ArabicNumerals::digitsOnly($phone);

        // 00201… and 201… both mean the same local 01… number.
        return preg_replace('/^(00)?20(1\d{9})$/', '0$2', $digits) ?? $digits;
    }
}
