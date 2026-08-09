<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function edit(Request $request): Response
    {
        $store = $request->user()->currentStore();

        return Inertia::render('settings/Store', [
            'store' => [
                'name' => $store->name,
                'description' => $store->description,
                'logo_url' => $store->logoUrl(),
                'platform_host' => $store->platformHost(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $request->user()->currentStore();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['boolean'],
        ], [
            'name.required' => 'اسم المتجر مطلوب.',
            'logo.image' => 'اللوجو لازم يكون صورة.',
            'logo.max' => 'أقصى حجم للوجو ٢ ميجا.',
        ]);

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];

        // Delete the old file whether it is being replaced or just removed —
        // orphaned uploads are storage nobody ever reclaims.
        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            if ($store->logo_path) {
                Storage::disk('public')->delete($store->logo_path);
            }

            $attributes['logo_path'] = $request->hasFile('logo')
                ? $request->file('logo')->store("stores/{$store->id}", 'public')
                : null;
        }

        $store->update($attributes);

        return back()->with('status', 'store-updated');
    }
}
