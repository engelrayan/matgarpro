<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets a merchant shape their own order form.
 *
 * Only the parts a merchant may change are read from the request. `locked` is
 * never taken from input — it is a platform rule, and accepting it from the
 * client would let anyone unlock the name and phone fields with a crafted POST.
 */
class CheckoutFieldsController extends Controller
{
    public function edit(Request $request): Response
    {
        $store = $request->user()->currentStore();

        return Inertia::render('settings/Checkout', [
            'fields' => collect($store->checkoutFields())
                ->map(fn ($field, $key) => [...$field, 'key' => $key])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $request->user()->currentStore();

        $validated = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.key' => ['required', 'string'],
            'fields.*.label' => ['required', 'string', 'max:60'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:120'],
            'fields.*.enabled' => ['boolean'],
            'fields.*.required' => ['boolean'],
            'fields.*.order' => ['required', 'integer', 'min:1', 'max:99'],
        ], [
            'fields.*.label.required' => 'كل حقل لازم يكون ليه اسم يظهر للعميل.',
        ]);

        $defaults = (array) config('checkout.fields');
        $saved = [];

        foreach ($validated['fields'] as $field) {
            $key = $field['key'];

            // Ignore anything that is not a field we ship. A stored key with no
            // default would be invisible in the UI but still validated against.
            if (! isset($defaults[$key])) {
                continue;
            }

            $locked = $defaults[$key]['locked'];

            $saved[$key] = [
                'label' => $field['label'],
                'placeholder' => $field['placeholder'] ?? '',
                'enabled' => $locked ? true : (bool) ($field['enabled'] ?? false),
                'required' => $locked ? true : (bool) ($field['required'] ?? false),
                'order' => $field['order'],
            ];
        }

        $store->update([
            'settings' => [...(array) $store->settings, 'checkout_fields' => $saved],
        ]);

        return back()->with('status', 'checkout-updated');
    }
}
