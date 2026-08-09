<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Services\Storefront\StoreDomainService;
use App\Services\Storefront\StoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Merchant-facing custom domain management.
 *
 * Thin on purpose: everything it knows how to do lives in StoreDomainService,
 * so the mobile API can expose the same operations without restating a rule.
 */
class DomainController extends Controller
{
    public function __construct(
        private readonly StoreDomainService $domains,
        private readonly StoreResolver $resolver,
    ) {}

    public function edit(Request $request): Response
    {
        $store = $this->currentStore($request);

        return Inertia::render('settings/Domains', [
            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
                'platform_host' => $store->platformHost(),
                'canonical_url' => $store->canonicalUrl(),
            ],
            'domains' => $store->domains()->orderByDesc('is_primary')->orderBy('id')->get()
                ->map(fn (StoreDomain $d) => [
                    'id' => $d->id,
                    'domain' => $d->domain,
                    'status' => $d->status,
                    'is_primary' => $d->is_primary,
                    'is_apex' => $d->isApex(),
                    'last_error' => $d->last_error,
                    // The padlock is its own state. A hostname can be serving
                    // the shop while its certificate is still queued, and one
                    // combined badge would have to lie about one of them.
                    'ssl_status' => $d->ssl_status,
                    'ssl_message' => $d->sslMessage(),
                    'is_secure' => $d->isSecure(),
                    'last_checked_at' => $d->last_checked_at?->diffForHumans(),
                    'verified_at' => $d->verified_at?->toDateTimeString(),
                    'instructions' => $d->dnsInstructions(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['domain' => ['required', 'string', 'max:253']]);

        $store = $this->currentStore($request);

        try {
            $domain = $this->domains->attach($store, $request->string('domain')->toString());
        } catch (DomainException $e) {
            // Surface it on the field the merchant typed into, not as a toast.
            throw ValidationException::withMessages(['domain' => $e->getMessage()]);
        }

        // Check immediately: merchants who set DNS before adding the domain here
        // (a common order of operations) get a working store on the spot rather
        // than waiting for the next scheduled sweep.
        $this->domains->verify($domain);
        $this->resolver->forget($domain->domain);

        return back()->with('status', 'domain-added');
    }

    public function verify(Request $request, StoreDomain $domain): RedirectResponse
    {
        $this->authorizeDomain($request, $domain);

        $this->domains->verify($domain);
        $this->resolver->forget($domain->domain);

        return back()->with('status', 'domain-checked');
    }

    public function primary(Request $request, StoreDomain $domain): RedirectResponse
    {
        $this->authorizeDomain($request, $domain);

        try {
            $this->domains->makePrimary($domain);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['domain' => $e->getMessage()]);
        }

        return back()->with('status', 'domain-primary');
    }

    public function destroy(Request $request, StoreDomain $domain): RedirectResponse
    {
        $this->authorizeDomain($request, $domain);

        $host = $domain->domain;
        $this->domains->detach($domain);
        $this->resolver->forget($host);

        return back()->with('status', 'domain-removed');
    }

    private function currentStore(Request $request): Store
    {
        return $request->user()->currentStore();
    }

    /** A merchant may only touch domains on a store they own. */
    private function authorizeDomain(Request $request, StoreDomain $domain): void
    {
        abort_unless(
            $request->user()->stores()->whereKey($domain->store_id)->exists(),
            403,
        );
    }
}
