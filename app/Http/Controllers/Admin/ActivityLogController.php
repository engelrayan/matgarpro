<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The audit trail, browsable.
 *
 * Read-only by construction — there is no update or delete route here and no
 * model path to write one, because a log an operator can edit answers nothing
 * on the day it is needed. Every operator can read the whole trail, including
 * their own actions and their colleagues': the deterrent only works if it is
 * known to be visible.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q')),
            'admin' => (string) $request->query('admin'),
            'action' => (string) $request->query('action'),
        ];

        $logs = AdminActivityLog::query()
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $like = '%' . $filters['q'] . '%';

                $query->where(fn ($q) => $q->where('summary', 'like', $like)->orWhere('admin_name', 'like', $like));
            })
            ->when($filters['admin'] !== '', fn ($q) => $q->where('admin_id', $filters['admin']))
            ->when($filters['action'] !== '', fn ($q) => $q->where('action', $filters['action']))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (AdminActivityLog $log) => [
                'id' => $log->id,
                'admin_name' => $log->admin_name,
                'admin_email' => $log->admin_email,
                'action' => $log->action,
                'summary' => $log->summary,
                'subject_label' => $log->subjectLabel(),
                'subject_id' => $log->subject_id,
                // Only stores have a page worth linking to from here.
                'subject_url' => $log->subject_type === \App\Models\Store::class && $log->subject_id
                    ? route('admin.stores.show', $log->subject_id)
                    : null,
                'changes' => $log->changes,
                'ip' => $log->ip,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'created_ago' => $log->created_at->diffForHumans(),
            ]);

        return Inertia::render('admin/ActivityLog', [
            'logs' => $logs,
            'filters' => $filters,
            'admins' => Admin::orderBy('name')->get(['id', 'name']),
            // Built from what has actually been logged, not a hardcoded list —
            // a new action type shows up in the filter the first time it fires.
            'actions' => AdminActivityLog::query()
                ->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
