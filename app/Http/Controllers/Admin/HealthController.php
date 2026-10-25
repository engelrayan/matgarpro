<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\SystemHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Is everything running — the scheduler, both workers, WAHA, the disk —
 * and the jobs that failed, with a button to try them again.
 */
class HealthController extends Controller
{
    public function __construct(
        private readonly SystemHealth $health,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/Health', [
            'checks' => $this->health->checks(),
            'failed' => $this->health->failedJobList(),
            'checkedAt' => now()->format('H:i:s'),
        ]);
    }

    public function retry(string $uuid): RedirectResponse
    {
        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        abort_unless($job, 404);

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        $this->audit->log(
            action: 'job.retried',
            summary: 'أعاد تشغيل مهمة فاشلة: ' . class_basename((string) data_get(json_decode($job->payload, true), 'displayName')),
        );

        return back()->with('status', 'المهمة رجعت للطابور، والـ worker هيشغّلها تاني.');
    }

    public function forget(string $uuid): RedirectResponse
    {
        $job = DB::table('failed_jobs')->where('uuid', $uuid)->first();
        abort_unless($job, 404);

        Artisan::call('queue:forget', ['id' => $uuid]);

        $this->audit->log(
            action: 'job.forgotten',
            summary: 'مسح مهمة فاشلة من غير ما تتشغّل: ' . class_basename((string) data_get(json_decode($job->payload, true), 'displayName')),
        );

        return back()->with('status', 'المهمة اتمسحت من قايمة الفشل.');
    }
}
