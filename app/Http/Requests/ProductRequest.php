<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Optional: blank means "derive it from the name".
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[\p{Arabic}a-z0-9\-]+$/iu'],
            'description' => ['nullable', 'string', 'max:20000'],

            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'gt:price'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'sku' => ['nullable', 'string', 'max:100'],

            'track_stock' => ['boolean'],
            'stock' => ['nullable', 'integer', 'min:0'],

            'status' => ['required', Rule::in([Product::STATUS_DRAFT, Product::STATUS_ACTIVE])],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],

            'options' => ['nullable', 'array', 'max:3'],
            'options.*.name' => ['required_with:options', 'string', 'max:60'],
            'options.*.values' => ['required_with:options', 'array', 'min:1', 'max:30'],
            'options.*.values.*' => ['string', 'max:60'],

            'variants' => ['nullable', 'array', 'max:200'],
            'variants.*.options' => ['required_with:variants', 'array'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],

            /*
             | Page settings. Each key is listed explicitly rather than taking
             | the whole array: an un-enumerated JSON blob from the client ends
             | up stored verbatim, and the storefront then renders whatever was
             | posted into it.
             */
            'settings' => ['nullable', 'array'],
            'settings.buy_button_text' => ['nullable', 'string', 'max:40'],
            'settings.sticky_buy_bar' => ['boolean'],
            'settings.form_before_description' => ['boolean'],
            'settings.hide_header' => ['boolean'],
            'settings.free_shipping' => ['boolean'],
            'settings.hide_when_out_of_stock' => ['boolean'],

            // Ownership is re-checked when saving; this only bounds the shape.
            'categories' => ['nullable', 'array', 'max:20'],
            'categories.*' => ['integer'],

            'kept_images' => ['nullable', 'array'],
            'kept_images.*' => ['integer'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المنتج مطلوب.',
            'price.required' => 'سعر المنتج مطلوب.',
            'price.numeric' => 'السعر لازم يكون رقم.',
            'compare_at_price.gt' => 'سعر المقارنة لازم يكون أكبر من السعر الحالي، وإلا الخصم مش هيبان.',
            'images.*.image' => 'الملف لازم يكون صورة.',
            'images.*.max' => 'أقصى حجم للصورة ٥ ميجا.',
            'images.max' => 'أقصى عدد صور للمنتج ١٠.',
            'options.max' => 'أقصى عدد خواص للمنتج ٣ (زي اللون والمقاس).',
            'slug.regex' => 'رابط المنتج يقبل حروف وأرقام وشرطة (-) بس.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // The form posts as multipart so it can carry files; every scalar
        // arrives as a string, and "false" is truthy.
        $this->merge([
            'track_stock' => filter_var($this->input('track_stock', true), FILTER_VALIDATE_BOOLEAN),
            'slug' => trim((string) $this->input('slug')),
        ]);
    }
}
