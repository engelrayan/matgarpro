<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\SvgSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            /*
             | `image:allow_svg`, not plain `image`.
             |
             | Laravel refuses SVG under `image` by default, and for a good
             | reason — an SVG is a document the browser executes, and this one
             | is served from the dashboard's own hostname. The rule here had
             | listed `svg` under `mimes` for months and it never once worked:
             | `image` failed first, and the merchant got "الملف لازم يكون صورة"
             | with no idea why.
             |
             | Turning it on is only safe because storeLogo() strips the file to
             | an allow-list of drawing elements before it is written. Do not
             | re-enable one without the other.
             */
            'logo' => ['nullable', 'image:allow_svg', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
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
                ? $this->storeLogo($request->file('logo'), $store)
                : null;
        }

        $store->update($attributes);

        return back()->with('status', 'store-updated');
    }

    /**
     * Save the logo, disarming it first when it is an SVG.
     *
     * An SVG is a document the browser executes, and this is the only upload
     * on the platform that accepts one. Stored under `/storage`, it is served
     * from the dashboard's own hostname — so a `<script>` inside a logo runs
     * with the merchant's session, and the platform panel is on that host too.
     *
     * Sanitised on the way in rather than on the way out: the file is written
     * once and read on every page load, and an escape that depends on every
     * future reader remembering to call it is an escape that eventually gets
     * forgotten.
     */
    private function storeLogo(UploadedFile $file, Store $store): string
    {
        $directory = "stores/{$store->id}";

        if (strtolower($file->getClientOriginalExtension()) !== 'svg') {
            return $file->store($directory, 'public');
        }

        $clean = SvgSanitizer::clean((string) file_get_contents($file->getRealPath()));

        // Unparseable means we could not read what we are about to serve, and
        // storing that is the one thing worse than refusing it.
        if ($clean === null) {
            throw ValidationException::withMessages([
                'logo' => 'ملف الـ SVG ده مش سليم. جرّب تصدّره تاني أو ارفع PNG.',
            ]);
        }

        $path = $directory . '/' . Str::random(40) . '.svg';
        Storage::disk('public')->put($path, $clean);

        return $path;
    }
}
