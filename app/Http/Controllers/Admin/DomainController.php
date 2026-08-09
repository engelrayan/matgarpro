<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\StoreDomain;
use App\Services\Admin\AuditLogger;
use App\Services\Storefront\StoreDomainService;
use App\Services\Storefront\StoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every custom domain on the platform, in one list.
 *
 * The operator jobs this exists for are the ones a merchant cannot do for
 * themselves: seeing that thirty domains failed this morning (which means our
 * DNS targets moved, not that thirty merchants made the same mistake), forcing
 * a re-check, and detaching a hostname that should never have been attached.
 *
 * The rules live in {@see StoreDomainService}, exactly as they do for the
 * merchant screen — an operator re-checking a domain must get the same verdict
 * the merchant would, or the two screens start telling different stories.
 */
class DomainController extends Controller
{
    public function __construct(
        private readonly StoreDomainService $domains,
        private readonly StoreResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $term = trim((string) $request->query('q'));
        $status = (string) $request->query('status');

        $domains = StoreDomain::query()
            ->with('store:id,name,slug')
            ->when($term !== '', fn ($q) => $q->where('domain', 'like', '%' . $term . '%'))
            ->when(
                in_array($status, [StoreDomain::STATUS_ACTIVE, StoreDomain::STATUS_PENDING, StoreDomain::STATUS_FAILED], true),
                fn ($q) => $q->where('status', $status),
            )
            // Broken first: this screen is a work queue, not a directory.
            ->orderByRaw("FIELD(status, ?, ?, ?)", [
                StoreDomain::STATUS_FAILED, StoreDomain::STATUS_PENDING, StoreDomain::STATUS_ACTIVE,
            ])
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (StoreDomain $d) => [
                'id' => $d->id,
                'domain' => $d->domain,
                'status' => $d->status,
                'is_primary' => $d->is_primary,
                'is_apex' => $d->isApex(),
                'store' => ['id' => $d->store_id, 'name' => $d->store?->name],
                'ssl_issued_at' => $d->ssl_issued_at?->format('Y-m-d'),
                'last_error' => $d->last_error,
                'last_checked_at' => $d->last_checked_at?->diffForHumans(),
                'check_attempts' => $d->check_attempts,
                'created_at' => $d->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('admin/Domains', [
            'domains' => $domains,
            'filters' => ['q' => $term, 'status' => $status],
            'counts' => [
                'total' => StoreDomain::count(),
                'active' => StoreDomain::where('status', StoreDomain::STATUS_ACTIVE)->count(),
                'pending' => StoreDomain::where('status', StoreDomain::STATUS_PENDING)->count(),
                'failed' => StoreDomain::where('status', StoreDomain::STATUS_FAILED)->count(),
            ],
            // What merchants are told to point at. On this screen because a
            // wave of failures is far more often our config than their typing.
            'targets' => [
                'a' => config('storefront.dns.a'),
                'cname' => config('storefront.dns.cname'),
            ],
        ]);
    }

    public function verify(StoreDomain $domain): RedirectResponse
    {
        $before = $domain->status;

        $this->domains->verify($domain);
        // Drop the cached hostname→store mapping, or a domain that just went
        // active keeps 404ing until the cache expires on its own.
        $this->resolver->forget($domain->domain);

        // Only worth a log line when the verdict actually moved. A re-check
        // that confirms what we already knew is noise in the audit trail.
        if ($domain->status !== $before) {
            $this->audit->log(
                action: 'domain.rechecked',
                summary: "أعاد فحص {$domain->domain} — الحالة اتغيّرت من {$before} إلى {$domain->status}.",
                subject: $domain,
                changes: ['status' => ['from' => $before, 'to' => $domain->status]],
            );
        }

        return back()->with('status', 'domain-checked');
    }

    public function primary(StoreDomain $domain): RedirectResponse
    {
        // Named in the log line below; lazy loading is disabled outside
        // production, so the relation has to be asked for explicitly.
        $domain->loadMissing('store');

        try {
            $this->domains->makePrimary($domain);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['domain' => $e->getMessage()]);
        }

        $this->audit->log(
            action: 'domain.made_primary',
            summary: "خلّى {$domain->domain} الدومين الأساسي لمتجر «{$domain->store?->name}».",
            subject: $domain,
        );

        return back()->with('status', 'domain-primary');
    }

    public function destroy(StoreDomain $domain): RedirectResponse
    {
        $domain->loadMissing('store');

        $host = $domain->domain;
        $storeName = $domain->store?->name;

        /*
         | Logged before the delete, not after: once the row is gone its id is
         | gone with it, and an audit line that cannot name what it acted on is
         | worth very little.
         */
        $this->audit->log(
            action: 'domain.detached',
            summary: "شال الدومين {$host} من متجر «{$storeName}».",
            subject: $domain,
            changes: ['domain' => ['from' => $host, 'to' => null]],
        );

        $this->domains->detach($domain);
        $this->resolver->forget($host);

        return back()->with('status', 'domain-removed');
    }
}
