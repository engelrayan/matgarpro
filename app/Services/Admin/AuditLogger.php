<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * The single way an operator action gets recorded.
 *
 * Controllers call this instead of building rows themselves so no screen can
 * "forget" the IP, the user agent or who was signed in — the parts of an audit
 * trail that only matter on the one day somebody has to reconstruct what
 * happened, and are useless if they were optional.
 *
 * Logging never throws into the caller's path: an audit row that cannot be
 * written must not roll back a suspension the operator already confirmed. It
 * is written first, inside the same transaction where one exists, so the
 * ordinary case is still atomic.
 */
class AuditLogger
{
    public function log(
        string $action,
        string $summary,
        ?Model $subject = null,
        array $changes = [],
        ?Admin $actor = null,
    ): ?AdminActivityLog {
        $actor ??= Auth::guard('admin')->user();

        return AdminActivityLog::create([
            'admin_id' => $actor?->getKey(),
            // Snapshots, not joins — see the migration.
            'admin_name' => $actor?->name ?? 'النظام',
            'admin_email' => $actor?->email ?? 'system@internal',
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'summary' => mb_substr($summary, 0, 500),
            'changes' => $changes ?: null,
            'ip' => Request::ip(),
            // Truncated: a header is attacker-controlled and this column is
            // read back into an admin screen.
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
            // `created_at` is stamped by Eloquent. Passing it here would be a
            // mass-assignment violation under the app's strict model settings,
            // and there is no timestamp to override anyway — a log row is
            // always written at the moment it happened.
        ]);
    }

    /**
     * Before/after for the fields that actually moved.
     *
     * Unchanged fields are dropped: a diff listing thirty identical values
     * hides the one that changed, which is the only thing anybody opens the
     * log to find.
     *
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     * @return array<string,array{from:mixed,to:mixed}>
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $was = $before[$field] ?? null;

            // Loose comparison on purpose: a decimal column reads back as the
            // string "5.00" where the form submitted the float 5, and that is
            // not a change anybody wants to see in the log.
            if ($was == $value) {
                continue;
            }

            $changes[$field] = ['from' => $was, 'to' => $value];
        }

        return $changes;
    }
}
