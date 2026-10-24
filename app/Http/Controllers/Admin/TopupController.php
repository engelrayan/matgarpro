<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreTopupRequest;
use App\Services\Billing\TopupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Merchants' top-up requests, for an operator to match against InstaPay.
 *
 * Open to every active operator, like a manual wallet adjustment: checking a
 * transfer arrived is day-to-day work, not a platform rule. Every confirmation
 * and rejection is in the audit trail with who did it.
 */
class TopupController extends Controller
{
    public function __construct(private readonly TopupService $topups) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: StoreTopupRequest::STATUS_PENDING;

        return Inertia::render('admin/Topups', [
            'status' => $status,
            'pendingCount' => StoreTopupRequest::where('status', StoreTopupRequest::STATUS_PENDING)->count(),
            'currency' => (string) config('billing.currency'),
            'requests' => StoreTopupRequest::with(['store:id,name,slug,balance', 'user:id,name', 'reviewer:id,name'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                // Oldest first while waiting — the merchant who asked first has
                // waited longest. Newest first once it is history.
                ->orderBy('id', $status === StoreTopupRequest::STATUS_PENDING ? 'asc' : 'desc')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (StoreTopupRequest $r) => [
                    'id' => $r->id,
                    'amount' => (float) $r->amount,
                    'confirmed_amount' => $r->confirmed_amount !== null ? (float) $r->confirmed_amount : null,
                    'status' => $r->status,
                    'status_label' => StoreTopupRequest::statusLabel($r->status),
                    'review_note' => $r->review_note,
                    'reviewer' => $r->reviewer?->name,
                    'created_at' => $r->created_at->format('Y-m-d H:i'),
                    'reviewed_at' => $r->reviewed_at?->format('Y-m-d H:i'),
                    'store' => [
                        'id' => $r->store->id,
                        'name' => $r->store->name,
                        'slug' => $r->store->slug,
                        'balance' => (float) $r->store->balance,
                    ],
                    'merchant' => $r->user?->name,
                ]),
        ]);
    }

    public function confirm(Request $request, StoreTopupRequest $topup): RedirectResponse
    {
        $validated = $request->validate([
            // What actually arrived — the operator may correct the merchant's pick.
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
        ], [
            'amount.required' => 'اكتب المبلغ اللي وصل فعلاً.',
        ]);

        $this->topups->confirm($topup, $request->user('admin'), round((float) $validated['amount'], 2));

        return back()->with('status', 'topup-confirmed');
    }

    public function reject(Request $request, StoreTopupRequest $topup): RedirectResponse
    {
        $validated = $request->validate([
            // Shown to the merchant, so it has to say what went wrong.
            'note' => ['required', 'string', 'max:255'],
        ], [
            'note.required' => 'اكتب سبب الرفض — التاجر هيشوفه.',
        ]);

        $this->topups->reject($topup, $request->user('admin'), $validated['note']);

        return back()->with('status', 'topup-rejected');
    }
}
