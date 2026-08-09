<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Categories are managed on one screen rather than a list plus a create page
 * plus an edit page: a store has a handful of them, and three navigations to
 * rename "رجالي" is three too many.
 */
class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $store = $this->currentStore($request);

        return Inertia::render('categories/Index', [
            'categories' => $store->categories()
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'description' => $c->description,
                    'image_url' => $c->imageUrl(),
                    'is_active' => $c->is_active,
                    'sort_order' => $c->sort_order,
                    'products_count' => $c->products_count,
                    'url' => $store->canonicalUrl() . '/c/' . $c->slug,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $data = $this->validated($request);

        /*
         | Columns listed explicitly rather than spreading the validated data:
         | it carries `image` and `remove_image`, which are inputs and not
         | columns, and strict Eloquent throws on any non-fillable key instead
         | of quietly dropping it.
         */
        $store->categories()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            // `slug` is optional, so it is absent from validated() rather than
            // empty when the merchant leaves it blank.
            'slug' => Category::uniqueSlug($store->id, $data['slug'] ?? '' ?: $data['name']),
            'image_path' => $this->storeImage($request, $store),
            // New categories go last, so adding one never reorders the menu
            // a merchant already arranged.
            'sort_order' => (int) $store->categories()->max('sort_order') + 1,
        ]);

        return back()->with('status', 'category-created');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeCategory($request, $category);

        $store = $this->currentStore($request);
        $data = $this->validated($request);

        $attributes = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? $category->is_active,
            'slug' => Category::uniqueSlug($store->id, $data['slug'] ?? '' ?: $data['name'], $category->id),
        ];

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }

            $attributes['image_path'] = $this->storeImage($request, $store);
        }

        $category->update($attributes);

        return back()->with('status', 'category-updated');
    }

    /**
     * Reorder from a list of ids. Sent whole rather than as a pair of swaps so
     * the stored order always matches exactly what the merchant is looking at.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);

        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($request->input('ids') as $position => $id) {
            $store->categories()->whereKey($id)->update(['sort_order' => $position]);
        }

        return back()->with('status', 'categories-reordered');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorizeCategory($request, $category);

        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }

        // Products are only detached — deleting a section must never delete
        // the things that were filed under it.
        $category->products()->detach();
        $category->delete();

        return back()->with('status', 'category-deleted');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[\p{Arabic}a-z0-9\-]+$/iu'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'name.required' => 'اسم القسم مطلوب.',
            'slug.regex' => 'الرابط يقبل حروف وأرقام وشرطة (-) بس.',
            'image.max' => 'أقصى حجم للصورة ٢ ميجا.',
        ]);
    }

    private function storeImage(Request $request, Store $store): ?string
    {
        return $request->hasFile('image')
            ? $request->file('image')->store("stores/{$store->id}/categories", 'public')
            : null;
    }

    private function currentStore(Request $request): Store
    {
        return $request->user()->currentStore();
    }

    private function authorizeCategory(Request $request, Category $category): void
    {
        abort_unless(
            $request->user()->stores()->whereKey($category->store_id)->exists(),
            403,
        );
    }
}
