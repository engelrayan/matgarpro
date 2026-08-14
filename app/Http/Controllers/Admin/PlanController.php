<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingPlan;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pricing plans. Super-admin only — this screen decides what every merchant on
 * the platform pays.
 *
 * Re-pricing a plan is safe with respect to history: `store_usage_events` keeps
 * the price it charged on each row, so nothing already billed is rewritten.
 * What it does change is every future order on every store carrying the plan,
 * which is why the screen says how many stores that is before you save.
 */
class PlanController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('admin/Plans', [
            'plans' => BillingPlan::withCount('stores')
                ->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (BillingPlan $plan) => [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price_per_order' => (float) $plan->price_per_order,
                    'billable_event' => $plan->billable_event,
                    'is_default' => $plan->is_default,
                    'is_public' => $plan->is_public,
                    'is_active' => $plan->is_active,
                    'sort_order' => $plan->sort_order,
                    'stores_count' => $plan->stores_count,
                ]),
            'currency' => config('billing.currency'),
            'defaultPrice' => (float) config('billing.default_price_per_order'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $plan = DB::transaction(function () use ($validated) {
            $plan = BillingPlan::create($validated);
            $this->enforceSingleDefault($plan);

            return $plan;
        });

        $this->audit->log(
            action: 'plan.created',
            summary: "أنشأ خطة «{$plan->name}» بسعر {$plan->price_per_order} لكل طلب.",
            subject: $plan,
            changes: array_map(fn ($v) => ['from' => null, 'to' => $v], $validated),
        );

        return back()->with('status', 'plan-created');
    }

    public function update(Request $request, BillingPlan $plan): RedirectResponse
    {
        $validated = $this->validated($request, $plan);
        $before = $plan->only(array_keys($validated));

        DB::transaction(function () use ($plan, $validated) {
            $plan->update($validated);
            $this->enforceSingleDefault($plan);
        });

        $this->audit->log(
            action: 'plan.updated',
            summary: "عدّل خطة «{$plan->name}» — بتأثر على {$plan->stores()->count()} متجر.",
            subject: $plan,
            changes: $this->audit->diff($before, $plan->only(array_keys($validated))),
        );

        return back()->with('status', 'plan-updated');
    }

    public function destroy(BillingPlan $plan): RedirectResponse
    {
        /*
         | Two refusals rather than a cascade.
         |
         | `billing_plan_id` is nullOnDelete, so deleting a plan in use would
         | quietly drop those stores onto the config fallback price — a pricing
         | change nobody decided and nobody would see. Deactivate instead: it
         | hides the plan from signup while everyone on it keeps their price.
         */
        if ($plan->stores()->exists()) {
            throw ValidationException::withMessages([
                'plan' => 'فيه متاجر على الخطة دي. عطّلها بدل ما تمسحها عشان أسعارهم ما تتغيّرش من ورا ظهرهم.',
            ]);
        }

        if ($plan->is_default) {
            throw ValidationException::withMessages([
                'plan' => 'دي الخطة الافتراضية — أي متجر جديد بيتفتح عليها. حدّد خطة افتراضية تانية الأول.',
            ]);
        }

        $this->audit->log(
            action: 'plan.deleted',
            summary: "مسح خطة «{$plan->name}».",
            subject: $plan,
            changes: ['code' => ['from' => $plan->code, 'to' => null]],
        );

        $plan->delete();

        return back()->with('status', 'plan-deleted');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?BillingPlan $plan = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/', Rule::unique('billing_plans', 'code')->ignore($plan)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_per_order' => ['required', 'numeric', 'min:0', 'max:9999'],
            'billable_event' => ['required', Rule::in(['created', 'confirmed', 'delivered'])],
            'is_default' => ['boolean'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ], [
            'code.regex' => 'الكود يبقى حروف إنجليزي صغيرة وأرقام وشرطات بس.',
        ]);
    }

    /**
     * Exactly one default, always.
     *
     * `Store::booted()` reads `BillingPlan::default()` when a store is created,
     * and that method takes the first match — two defaults would make new
     * stores land on whichever the database happened to return, and none would
     * drop them onto the config fallback.
     */
    private function enforceSingleDefault(BillingPlan $plan): void
    {
        if ($plan->is_default) {
            BillingPlan::where('id', '!=', $plan->id)->update(['is_default' => false]);

            return;
        }

        if (! BillingPlan::where('is_default', true)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'is_default' => 'لازم تفضل فيه خطة افتراضية واحدة شغّالة — المتاجر الجديدة بتتفتح عليها.',
            ]);
        }
    }
}
