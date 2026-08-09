<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingPlan;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreUsageEvent;
use App\Models\StoreWalletTransaction;
use App\Services\Admin\AuditLogger;
use App\Services\Billing\WalletService;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Operator view of a store, and the four things an operator may do to one:
 * change its status, change what it pays, move its balance, change its theme.
 *
 * Orders are pointedly absent. The panel reports on them in aggregate and
 * stops there — a merchant's customer names, phone numbers and addresses are
 * their business, and an operator who cannot open them cannot leak them.
 */
class StoreController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly WalletService $wallet,
        private readonly ThemeResolver $themes,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q')),
            'status' => (string) $request->query('status'),
            'billing_status' => (string) $request->query('billing_status'),
            'plan' => (string) $request->query('plan'),
            'theme' => (string) $request->query('theme'),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $stores = Store::query()
            ->with(['user:id,name,email', 'plan:id,name,code'])
            ->withCount(['orders', 'products'])
            // Showrooms are ours, not merchants'. They are listed only when
            // explicitly asked for, so they never pad the store list.
            ->where('is_demo', $filters['status'] === 'demo')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $term = '%' . $filters['q'] . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when(in_array($filters['status'], [Store::STATUS_ACTIVE, Store::STATUS_DRAFT, Store::STATUS_SUSPENDED], true),
                fn ($q) => $q->where('status', $filters['status']))
            ->when(in_array($filters['billing_status'], ['active', 'grace', 'suspended'], true),
                fn ($q) => $q->where('billing_status', $filters['billing_status']))
            ->when($filters['plan'] !== '', fn ($q) => $q->where('billing_plan_id', $filters['plan']))
            ->when($filters['theme'] !== '', function ($q) use ($filters) {
                $default = (string) config('themes.default');

                // A store on the default theme usually has no `theme` key at
                // all, so filtering for the default has to match NULL too or
                // it returns almost nothing.
                $filters['theme'] === $default
                    ? $q->where(function ($inner) use ($default) {
                        $inner->whereNull('settings->theme')->orWhere('settings->theme', $default);
                    })
                    : $q->where('settings->theme', $filters['theme']);
            })
            ->tap(fn ($q) => match ($filters['sort']) {
                'orders' => $q->orderByDesc('orders_count'),
                'balance' => $q->orderBy('balance'),
                'name' => $q->orderBy('name'),
                default => $q->latest('id'),
            })
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'url' => $store->canonicalUrl(),
                'status' => $store->status,
                'billing_status' => $store->billing_status,
                'balance' => (float) $store->balance,
                'price_per_order' => $store->pricePerOrder(),
                'price_source' => $store->priceSource(),
                'plan' => $store->plan?->name,
                'theme' => $this->themes->forStore($store)['name'],
                'orders_count' => $store->orders_count,
                'products_count' => $store->products_count,
                'merchant' => ['id' => $store->user_id, 'name' => $store->user?->name, 'email' => $store->user?->email],
                'created_at' => $store->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('admin/stores/Index', [
            'stores' => $stores,
            'filters' => $filters,
            'plans' => BillingPlan::orderBy('sort_order')->get(['id', 'name']),
            'themeOptions' => collect($this->themes->all())
                ->map(fn (array $t) => ['key' => $t['key'], 'name' => $t['name']])->all(),
            'currency' => config('billing.currency'),
        ]);
    }

    public function show(Request $request, Store $store): Response
    {
        $store->load(['user:id,name,email,created_at', 'plan', 'domains']);

        [$from, $to] = OverviewController::window($request);

        return Inertia::render('admin/stores/Show', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description,
                'logo_url' => $store->logoUrl(),
                'url' => $store->canonicalUrl(),
                'platform_host' => $store->platformHost(),
                'status' => $store->status,
                'suspension_reason' => $store->suspension_reason,
                'billing_status' => $store->billing_status,
                'currency' => $store->currency,
                'balance' => (float) $store->balance,
                'billing_plan_id' => $store->billing_plan_id,
                'price_per_order_override' => $store->price_per_order_override,
                'price_per_order' => $store->pricePerOrder(),
                'price_source' => $store->priceSource(),
                'can_accept_orders' => $store->canAcceptOrders(),
                'is_demo' => $store->is_demo,
                'theme' => $this->themes->forStore($store)['key'],
                'created_at' => $store->created_at->format('Y-m-d'),
            ],
            'merchant' => [
                'id' => $store->user?->id,
                'name' => $store->user?->name,
                'email' => $store->user?->email,
                'joined' => $store->user?->created_at?->format('Y-m-d'),
                'stores_count' => $store->user?->stores()->count(),
            ],
            'stats' => [
                'orders' => $store->orders()->count(),
                'orders_in_window' => $store->orders()->whereBetween('created_at', [$from, $to])->count(),
                'delivered' => $store->orders()->where('status', Order::STATUS_DELIVERED)->count(),
                // Delivered only — the same definition the merchant sees on
                // their own dashboard, so the two screens never disagree.
                'gmv' => (float) $store->orders()->where('status', Order::STATUS_DELIVERED)->sum('total'),
                'products' => $store->products()->count(),
                'billed' => (float) $store->usageEvents()->sum('amount'),
                'billed_in_window' => (float) $store->usageEvents()
                    ->whereBetween('occurred_at', [$from, $to])->sum('amount'),
                'topups' => (float) $store->walletTransactions()
                    ->where('type', StoreWalletTransaction::TYPE_TOPUP)->sum('amount'),
            ],
            'domains' => $store->domains->map(fn ($d) => [
                'id' => $d->id,
                'domain' => $d->domain,
                'status' => $d->status,
                'is_primary' => $d->is_primary,
                'ssl_issued_at' => $d->ssl_issued_at?->format('Y-m-d'),
                'last_error' => $d->last_error,
            ]),
            'wallet' => $store->walletTransactions()->latest('id')->limit(25)->get()
                ->map(fn (StoreWalletTransaction $t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => (float) $t->amount,
                    'balance_after' => (float) $t->balance_after,
                    'description' => $t->description,
                    'by' => data_get($t->meta, 'admin_name'),
                    'created_at' => $t->created_at->format('Y-m-d H:i'),
                ]),
            'usage' => $store->usageEvents()->latest('id')->limit(10)->get()
                ->map(fn (StoreUsageEvent $e) => [
                    'id' => $e->id,
                    'type' => $e->type,
                    'amount' => (float) $e->amount,
                    'price_source' => $e->price_source,
                    'occurred_at' => $e->occurred_at->format('Y-m-d H:i'),
                ]),
            'activity' => $store->activityLogs()->latest('id')->limit(15)->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'admin_name' => $log->admin_name,
                    'summary' => $log->summary,
                    'created_at' => $log->created_at->format('Y-m-d H:i'),
                ]),
            'plans' => BillingPlan::orderBy('sort_order')->get(['id', 'name', 'code', 'price_per_order']),
            'themes' => $this->themes->all(),
            'currency' => config('billing.currency'),
            'defaultPrice' => (float) config('billing.default_price_per_order'),
        ]);
    }

    /** Activate, suspend or send back to draft. */
    public function status(Request $request, Store $store): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([Store::STATUS_ACTIVE, Store::STATUS_DRAFT, Store::STATUS_SUSPENDED])],
            // A suspension with no stated reason is one nobody can answer for
            // later — not to the merchant, and not to the next operator.
            'reason' => ['nullable', 'string', 'max:255', Rule::requiredIf($request->input('status') === Store::STATUS_SUSPENDED)],
        ], [
            'reason.required' => 'اكتب سبب الإيقاف.',
        ]);

        $before = ['status' => $store->status, 'suspension_reason' => $store->suspension_reason];

        $store->update([
            'status' => $validated['status'],
            // Cleared on the way out, so a re-suspension can never inherit a
            // stale reason from months ago.
            'suspension_reason' => $validated['status'] === Store::STATUS_SUSPENDED
                ? $validated['reason']
                : null,
        ]);

        $verb = match ($validated['status']) {
            Store::STATUS_ACTIVE => 'فعّل',
            Store::STATUS_SUSPENDED => 'أوقف',
            default => 'رجّع لمسودة',
        };

        $this->audit->log(
            action: 'store.status_changed',
            summary: "{$verb} متجر «{$store->name}»" . ($validated['reason'] ? " — السبب: {$validated['reason']}" : ''),
            subject: $store,
            changes: $this->audit->diff($before, [
                'status' => $store->status,
                'suspension_reason' => $store->suspension_reason,
            ]),
        );

        return back()->with('status', 'store-status-updated');
    }

    /** Plan, per-store price override and billing state. */
    public function billing(Request $request, Store $store): RedirectResponse
    {
        $validated = $request->validate([
            'billing_plan_id' => ['nullable', Rule::exists('billing_plans', 'id')],
            /*
             | Nullable is meaningful: NULL means "inherit the plan", 0 means
             | "this store pays nothing". The form has to be able to express
             | both, so an empty string is normalised to NULL rather than 0.
             */
            'price_per_order_override' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'billing_status' => ['required', Rule::in(['active', 'grace', 'suspended'])],
        ]);

        $before = [
            'billing_plan_id' => $store->billing_plan_id,
            'price_per_order_override' => $store->price_per_order_override,
            'billing_status' => $store->billing_status,
        ];

        $store->update([
            'billing_plan_id' => $validated['billing_plan_id'] ?: null,
            'price_per_order_override' => $validated['price_per_order_override'] === null
                ? null
                : (float) $validated['price_per_order_override'],
            'billing_status' => $validated['billing_status'],
        ]);

        $this->audit->log(
            action: 'store.billing_updated',
            summary: "عدّل تسعير متجر «{$store->name}» — السعر الحالي {$store->pricePerOrder()} لكل طلب.",
            subject: $store,
            changes: $this->audit->diff($before, [
                'billing_plan_id' => $store->billing_plan_id,
                'price_per_order_override' => $store->price_per_order_override,
                'billing_status' => $store->billing_status,
            ]),
        );

        return back()->with('status', 'store-billing-updated');
    }

    /** Move a store's balance, by hand, with a note. */
    public function wallet(Request $request, Store $store): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            // Every manual movement carries an explanation. A ledger row that
            // says only "adjustment" is the one that starts an argument.
            'note' => ['required', 'string', 'max:255'],
        ], [
            'note.required' => 'اكتب سبب التعديل — القيد بيتسجل للأبد.',
        ]);

        $admin = $request->user('admin');
        $amount = (float) $validated['amount'];

        /*
         | `created_by` stays null: that column is a foreign key to `users`,
         | and an operator id is not a user id — writing one there would either
         | fail on the constraint or, worse, point at an unrelated merchant.
         | Who did it lives in `meta` and in the audit log.
         */
        $meta = ['admin_id' => $admin->id, 'admin_name' => $admin->name];
        $description = $validated['note'];

        $transaction = $validated['direction'] === 'credit'
            ? $this->wallet->credit($store, $amount, StoreWalletTransaction::TYPE_ADJUSTMENT, $description, null, null, $meta)
            : $this->wallet->debit($store, $amount, StoreWalletTransaction::TYPE_ADJUSTMENT, $description, null, null, $meta);

        $sign = $validated['direction'] === 'credit' ? '+' : '−';

        $this->audit->log(
            action: 'store.wallet_adjusted',
            summary: "عدّل رصيد متجر «{$store->name}» بمقدار {$sign}{$amount} — {$description}",
            subject: $store,
            changes: [
                'balance' => ['from' => (float) $transaction->balance_after - (float) $transaction->amount, 'to' => (float) $transaction->balance_after],
            ],
        );

        return back()->with('status', 'store-wallet-updated');
    }

    /** Set a store's theme on its behalf — support, not a preference. */
    public function theme(Request $request, Store $store): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(array_keys((array) config('themes.themes')))],
        ], [
            'theme.in' => 'الثيم ده مش موجود.',
        ]);

        $before = $this->themes->forStore($store)['key'];

        $store->update([
            'settings' => [...(array) $store->settings, 'theme' => $validated['theme']],
        ]);

        $this->audit->log(
            action: 'store.theme_changed',
            summary: "غيّر ثيم متجر «{$store->name}» من {$before} إلى {$validated['theme']}.",
            subject: $store,
            changes: ['theme' => ['from' => $before, 'to' => $validated['theme']]],
        );

        return back()->with('status', 'store-theme-updated');
    }
}
