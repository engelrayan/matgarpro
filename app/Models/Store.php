<?php

namespace App\Models;

use App\Services\Shipping\ShippingRates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'logo_path',
        'currency',
        'status',
        'suspension_reason',
        'billing_plan_id',
        'price_per_order_override',
        'billing_status',
        'trial_ends_at',
        'settings',
        'is_demo',
    ];

    /**
     * `balance` is deliberately not fillable — it may only change through
     * WalletService, inside the same transaction that writes a ledger row.
     */
    protected $casts = [
        'price_per_order_override' => 'decimal:2',
        'balance' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'settings' => 'array',
        'is_demo' => 'boolean',
    ];

    /**
     * Every store carries an explicit plan, even the free one.
     *
     * Leaving it null would still bill correctly today — the price falls back to
     * config — but usage rows would record no plan, so the day pricing turns on
     * there would be no way to tell what a historical charge was based on.
     */
    protected static function booted(): void
    {
        static::creating(function (self $store) {
            $store->billing_plan_id ??= BillingPlan::default()?->id;

            /*
             | The introductory months start the moment the store exists, and
             | the end date is written down rather than worked out later — see
             | the note on `billing.intro_months`.
             |
             | A showroom never gets one: it cannot take a real order, so a
             | countdown on it means nothing.
             */
            $given = array_key_exists('trial_ends_at', $store->getAttributes());

            if (! $store->is_demo && ! $given) {
                $months = (int) config('billing.intro_months', 0);

                $store->trial_ends_at = $months > 0 ? now()->addMonths($months) : null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(BillingPlan::class, 'billing_plan_id');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(StoreDomain::class)->where('is_primary', true);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function pixels(): HasMany
    {
        return $this->hasMany(StorePixel::class);
    }

    /** Pixels that should render in the browser. */
    public function activePixels()
    {
        return $this->pixels()->where('is_active', true)->get();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function abandonedCarts(): HasMany
    {
        return $this->hasMany(AbandonedCart::class);
    }

    /** Requests to add money to the balance, oldest first when read in order. */
    public function topupRequests(): HasMany
    {
        return $this->hasMany(StoreTopupRequest::class);
    }

    /** The store's link to Daman, if the merchant has connected one. */
    public function damanIntegration(): HasOne
    {
        return $this->hasOne(StoreDamanIntegration::class);
    }

    /** The store's own WhatsApp line, if the merchant has connected one. */
    public function whatsappIntegration(): HasOne
    {
        return $this->hasOne(StoreWhatsappIntegration::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(StoreWalletTransaction::class);
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(StoreUsageEvent::class);
    }

    /**
     * Page layouts from the builder. A store with no rows here is not broken —
     * it renders the platform's default layout. See PageBuilder::defaults().
     */
    public function pages(): HasMany
    {
        return $this->hasMany(StorePage::class);
    }

    /**
     * Operator actions taken on this store, newest first when queried.
     *
     * Lives on the model rather than being looked up by type and id in the
     * panel so "what has anyone done to this shop" is one call, and stays one
     * call if the log table is ever partitioned.
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject');
    }

    /**
     * The order form as this store has configured it, ordered for rendering.
     *
     * Merged over the config defaults rather than stored whole: a store saved
     * last month must still pick up a field added to the platform this month,
     * and a merchant who never opened the settings screen has no stored config
     * at all.
     *
     * `locked` fields are re-forced from config on every read, so a stale or
     * hand-edited settings blob can never switch off the name or the phone.
     *
     * @return array<string,array<string,mixed>>
     */
    public function checkoutFields(): array
    {
        $defaults = (array) config('checkout.fields');
        $saved = (array) data_get($this->settings, 'checkout_fields', []);

        $fields = [];

        foreach ($defaults as $key => $default) {
            $field = array_merge($default, (array) ($saved[$key] ?? []));

            if ($default['locked']) {
                $field['enabled'] = true;
                $field['required'] = true;
            }

            $field['locked'] = $default['locked'];

            /*
             | With shipping priced by governorate, an order without one has
             | no shipping price — so the field is on and required for as long
             | as prices are, whatever the merchant set on the form screen.
             | Their own choice comes back the moment prices are switched off.
             */
            if ($key === 'governorate' && ShippingRates::for($this)->enabled()) {
                $field['enabled'] = true;
                $field['required'] = true;
                $field['locked'] = true;
            }

            $fields[$key] = $field;
        }

        uasort($fields, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $fields;
    }

    /** Just the fields a customer actually sees, in order. */
    public function enabledCheckoutFields(): array
    {
        return array_filter($this->checkoutFields(), fn ($f) => $f['enabled']);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path)
            : null;
    }

    /** The free sub-domain, which keeps working after a custom domain is added. */
    public function platformHost(): string
    {
        return $this->slug . '.' . config('storefront.domain');
    }

    /**
     * The hostname customers should see. Falls back to the platform sub-domain
     * whenever the custom domain is not actually serving yet — never advertise
     * a host that would fail to resolve.
     */
    public function canonicalHost(): string
    {
        $primary = $this->relationLoaded('domains')
            ? $this->domains->firstWhere(fn (StoreDomain $d) => $d->is_primary && $d->isServing())
            : $this->domains()->where('is_primary', true)->where('status', StoreDomain::STATUS_ACTIVE)->first();

        return $primary?->domain ?? $this->platformHost();
    }

    /**
     * A browsable URL for this store.
     *
     * The port lives here rather than in the hostname: the tenant resolver
     * matches on `Host` with the port already stripped, so a port inside
     * `storefront.domain` would make every storefront 404.
     */
    public function canonicalUrl(): string
    {
        $port = config('storefront.port');

        return config('storefront.scheme') . '://' . $this->canonicalHost()
            . ($port ? ':' . $port : '');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Is this store still inside its introductory months?
     *
     * Kept on `trial_ends_at`, the column from when these months were free.
     */
    public function inIntroPeriod(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /** Whole days left at the introductory price, or null once over or absent. */
    public function introDaysLeft(): ?int
    {
        return $this->inIntroPeriod()
            ? (int) ceil(now()->diffInDays($this->trial_ends_at, absolute: true))
            : null;
    }

    /**
     * Price of an order today, before any WhatsApp confirmation:
     * (override → plan → config), less the introductory discount while it lasts.
     *
     * The discount applies to an operator's override too. The introductory
     * months are a promise made at signup; ending them early is an explicit
     * act, not a side effect of repricing a store.
     */
    public function pricePerOrder(): float
    {
        $base = $this->basePricePerOrder();

        return $this->inIntroPeriod()
            ? max(0.0, round($base - (float) config('billing.intro_discount'), 2))
            : $base;
    }

    /**
     * What a WhatsApp confirmation adds to an order.
     *
     * Nothing for a store whose orders are free — the founding plan, or an
     * operator's zero override. Charging those for the message alone would
     * break the promise their plan is named after.
     */
    public function confirmationPrice(): float
    {
        return $this->basePricePerOrder() > 0 ? (float) config('billing.whatsapp_confirmation_fee') : 0.0;
    }

    /**
     * An order's price once the introductory months are over.
     *
     * Shown to the merchant during those months so the later price is a number
     * they have already seen, and used as the real price the moment they end.
     */
    public function basePricePerOrder(): float
    {
        if ($this->price_per_order_override !== null) {
            return (float) $this->price_per_order_override;
        }

        if ($this->plan) {
            return (float) $this->plan->price_per_order;
        }

        return (float) config('billing.default_price_per_order');
    }

    /** Which of the four pricing sources produced pricePerOrder(). */
    public function priceSource(): string
    {
        if ($this->inIntroPeriod()) {
            return 'intro';
        }

        if ($this->price_per_order_override !== null) {
            return 'override';
        }

        return $this->plan ? 'plan' : 'default';
    }

    /** Can this store still take an order right now? */
    public function canAcceptOrders(): bool
    {
        // A showroom looks exactly like a real shop, which is the point — so
        // somebody eventually fills in the form. Those must never become real
        // orders sitting in the platform's own account.
        if ($this->is_demo) {
            return false;
        }

        if (! $this->isActive() || $this->billing_status === 'suspended') {
            return false;
        }

        // A free store is never blocked by its balance.
        if ($this->pricePerOrder() <= 0) {
            return true;
        }

        return (float) $this->balance > -1 * (float) config('billing.overdraft_limit');
    }
}
