<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\RunsAiGenerations;
use App\Jobs\RunAiGeneration;
use App\Models\AiGeneration;
use App\Models\Store;
use App\Services\AI\AiFeature;
use App\Services\AI\AiUsageMeter;
use App\Services\AI\StoreBuilder\StorePlanApplier;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Tell us about your shop and we'll set it up."
 *
 * Three steps, each the merchant's own click: describe the shop (store), look
 * at the plan the AI wrote (show, polled), and accept it (apply) — which puts
 * it into the builder as drafts. Publishing stays where it always was, in the
 * builder, one page at a time.
 */
class AiStoreBuilderController extends Controller
{
    use RunsAiGenerations;

    /** The looks a merchant can ask for, in their words. */
    private const MOODS = ['فخم وهادي', 'شبابي ومبهج', 'بسيط ونضيف', 'دافي وطبيعي'];

    /** Footer icons the registry knows, and the field each one is asked under. */
    private const SOCIAL = ['facebook', 'instagram', 'tiktok', 'youtube'];

    public function index(Request $request, ThemeResolver $themes, AiUsageMeter $meter): Response
    {
        $store = $request->user()->currentStore();

        /*
         | Each theme's showroom, the same demo stores the theme picker links
         | to: a theme suggested by name alone is a theme picked blind.
         */
        $showrooms = Store::where('is_demo', true)->get()
            ->mapWithKeys(fn (Store $demo) => [Str::after($demo->slug, 'demo-') => $demo->canonicalUrl()]);

        // The landing page's "بتبيع إيه؟", carried through signup. Pulled, so it
        // fills the form once and a later visit starts from an empty one.
        $start = $request->session()->pull('ai_start');

        return Inertia::render('ai/StoreBuilder', [
            'storeName' => $store->name,
            'prefill' => $start['business'] ?? null,
            'moods' => self::MOODS,
            'themes' => collect($themes->all())
                ->map(fn (array $theme) => [
                    'key' => $theme['key'],
                    'name' => $theme['name'],
                    'description' => $theme['description'],
                    'preview_url' => $showrooms[$theme['key']] ?? null,
                ])
                ->all(),
            'currentTheme' => $themes->forStore($store)['key'],
            'triesLeft' => $meter->remainingToday($store, AiFeature::StoreBuilder),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->user()->currentStore();

        $data = $request->validate([
            'business' => ['required', 'string', 'max:600'],
            'audience' => ['nullable', 'string', 'max:300'],
            'mood' => ['nullable', 'string', 'max:60'],
            'shipping' => ['nullable', 'string', 'max:600'],
            'social' => ['nullable', 'array'],
            ...collect(self::SOCIAL)->mapWithKeys(fn ($p) => ["social.{$p}" => ['nullable', 'url:https', 'max:300']])->all(),
            'social.whatsapp' => ['nullable', 'string', 'regex:/^[0-9+\s-]{8,20}$/'],
        ], [
            'business.required' => 'اكتب في سطرين بتبيع إيه.',
            'business.max' => 'الكلام كتير شوية — اكتب أهم حاجات في كام سطر.',
            'shipping.max' => 'الكلام كتير شوية — اكتب أهم حاجات في كام سطر.',
            'social.*.url' => 'اللينك لازم يبدأ بـ https://',
            'social.whatsapp.regex' => 'اكتب رقم الواتساب أرقام بس، زي 01012345678.',
        ]);

        if ($refusal = $this->refuseToStart($store, AiFeature::StoreBuilder, 'لسه بنجهّز المتجر. استنى ثواني.')) {
            return $refusal;
        }

        $generation = AiGeneration::create([
            'store_id' => $store->id,
            'user_id' => $request->user()->id,
            'feature' => AiFeature::StoreBuilder->value,
            'status' => AiGeneration::STATUS_PENDING,
            'input' => [
                'business' => trim($data['business']),
                'audience' => trim((string) ($data['audience'] ?? '')),
                'mood' => in_array($data['mood'] ?? null, self::MOODS, true) ? $data['mood'] : '',
                'shipping' => trim((string) ($data['shipping'] ?? '')),
                // Never shown to the model — it has no use for them and every
                // reason not to rewrite them. They go straight into the footer.
                'social' => $this->socialLinks((array) ($data['social'] ?? [])),
            ],
        ]);

        RunAiGeneration::dispatch($generation->id);

        return response()->json($this->presentGeneration($generation->refresh()), 202);
    }

    public function show(Request $request, AiGeneration $generation): JsonResponse
    {
        return response()->json($this->presentGeneration(
            $this->ownedGeneration($request, $generation, AiFeature::StoreBuilder),
        ));
    }

    public function apply(Request $request, AiGeneration $generation, StorePlanApplier $applier): RedirectResponse
    {
        $generation = $this->ownedGeneration($request, $generation, AiFeature::StoreBuilder);

        $data = $request->validate([
            'apply_theme' => ['boolean'],
            'categories' => ['nullable', 'array', 'max:8'],
            'categories.*' => ['string', 'max:60'],
        ]);

        abort_unless($generation->status === AiGeneration::STATUS_DONE, 404);

        // Once. A second click would overwrite whatever the merchant has
        // since changed in the builder with the same plan again.
        if (filled($generation->result['applied_at'] ?? null)) {
            return back()->withErrors(['plan' => 'الخطة دي اتحطت في المتجر قبل كده.']);
        }

        $applier->apply(
            $request->user()->currentStore(),
            $generation->result,
            array_map('strval', $data['categories'] ?? []),
            (bool) ($data['apply_theme'] ?? false),
            (array) ($generation->input['social'] ?? []),
        );

        $generation->update(['result' => [...$generation->result, 'applied_at' => now()->toIso8601String()]]);

        return to_route('builder', ['page' => 'home'])->with('status', 'ai-store-drafted');
    }

    /**
     * Platform => link, for the footer. A WhatsApp number becomes a wa.me
     * link; an Egyptian number written the local way (01…) gets its 20.
     *
     * @param  array<string,mixed>  $social
     * @return array<string,string>
     */
    private function socialLinks(array $social): array
    {
        $links = collect(self::SOCIAL)
            ->mapWithKeys(fn ($platform) => [$platform => trim((string) ($social[$platform] ?? ''))])
            ->filter()
            ->all();

        $digits = preg_replace('/\D/', '', (string) ($social['whatsapp'] ?? ''));

        if ($digits !== '') {
            $links['whatsapp'] = 'https://wa.me/' . (str_starts_with($digits, '0') ? '2' . $digits : $digits);
        }

        return $links;
    }
}
