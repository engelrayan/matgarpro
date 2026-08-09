<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One operator action, as it happened.
 *
 * Append-only: `UPDATED_AT` is off and nothing in the codebase updates or
 * deletes a row. Write it through {@see \App\Services\Admin\AuditLogger}, not
 * with `create()` — the logger is what guarantees the request context
 * (operator, IP, agent) is on every row.
 */
class AdminActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id', 'admin_name', 'admin_email', 'action',
        'subject_type', 'subject_id', 'summary', 'changes', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** The subject's type as an operator would say it, for the log screen. */
    public function subjectLabel(): ?string
    {
        return match ($this->subject_type) {
            Store::class => 'متجر',
            User::class => 'تاجر',
            StoreDomain::class => 'دومين',
            BillingPlan::class => 'خطة',
            Admin::class => 'مشرف',
            default => null,
        };
    }
}
