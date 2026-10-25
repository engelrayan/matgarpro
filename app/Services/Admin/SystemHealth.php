<?php

namespace App\Services\Admin;

use App\Services\Whatsapp\PlatformWhatsapp;
use App\Services\Whatsapp\Waha\WahaClient;
use App\Support\Heartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Is the platform running, part by part, in words an operator can act on.
 *
 * Every check answers ok, warn or fail with one sentence saying why, and —
 * when it is not ok — what usually fixes it. Read on every page load, so
 * every check is cheap: a few counts, a cache read, one WAHA call with a
 * short timeout.
 */
class SystemHealth
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /** Off — not set up here, which is a choice rather than a fault. */
    public const OFF = 'off';

    /** A heartbeat older than this means whatever sends it has stopped. */
    private const STALE_AFTER_MINUTES = 3;

    /** @return array<int,array{key: string, label: string, status: string, detail: string, fix?: string}> */
    public function checks(): array
    {
        return [
            $this->scheduler(),
            $this->queue('default', 'الـ worker الأساسي', 'الطلبات ورسايل الواتساب والإيميلات'),
            $this->queue((string) config('ai.queue'), 'worker الذكاء الاصطناعي', 'كتابة المنتجات وصفحات الهبوط والملخصات'),
            $this->failedJobs(),
            $this->disk(),
            $this->waha(),
            $this->metaWhatsapp(),
            $this->debugMode(),
            $this->ai(),
        ];
    }

    /** @return array<int,array<string,mixed>> the latest failures, newest first */
    public function failedJobList(int $limit = 15): array
    {
        return DB::table('failed_jobs')->latest('failed_at')->limit($limit)->get()
            ->map(fn ($job) => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'queue' => $job->queue,
                'job' => class_basename((string) data_get(json_decode($job->payload, true), 'displayName', '?')),
                // The first line is the message; the rest is a stack trace
                // nobody reads on this screen.
                'error' => Str::limit(strtok((string) $job->exception, "\n") ?: '', 300),
                'failed_at' => CarbonImmutable::parse($job->failed_at)->diffForHumans(),
            ])
            ->all();
    }

    // ── Checks ────────────────────────────────────────────────────────────

    private function scheduler(): array
    {
        $last = Heartbeat::last(Heartbeat::SCHEDULER);

        return $this->beat('scheduler', 'الـ scheduler', $last, 'التذكيرات وفحص الدومينات والمهام الدورية',
            'sudo systemctl enable --now matgarpro-scheduler.timer');
    }

    private function queue(string $queue, string $label, string $does): array
    {
        $last = Heartbeat::last(Heartbeat::queue($queue));
        $check = $this->beat("queue:{$queue}", $label, $last, $does, 'sudo systemctl restart ' . ($queue === 'default' ? 'matgarpro-worker' : 'matgarpro-ai-worker'));

        $waiting = DB::table('jobs')->where('queue', $queue);
        $count = (clone $waiting)->count();
        $oldest = (clone $waiting)->min('created_at');

        if ($count > 0) {
            $age = CarbonImmutable::createFromTimestamp((int) $oldest)->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE);
            $check['detail'] .= " · {$count} مهمة مستنية، أقدمها من {$age}";
        }

        return $check;
    }

    private function failedJobs(): array
    {
        $day = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        $all = DB::table('failed_jobs')->count();

        return [
            'key' => 'failed_jobs',
            'label' => 'المهام اللي فشلت',
            'status' => match (true) {
                $day >= 10 => self::FAIL,
                $day > 0 => self::WARN,
                default => self::OK,
            },
            'detail' => $day > 0
                ? "{$day} في آخر ٢٤ ساعة ({$all} إجمالي). تفاصيلها تحت."
                : ($all > 0 ? "مفيش في آخر ٢٤ ساعة ({$all} قديمة)." : 'مفيش.'),
        ];
    }

    private function disk(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if (! $free || ! $total) {
            return ['key' => 'disk', 'label' => 'مساحة السيرفر', 'status' => self::WARN, 'detail' => 'مقدرناش نقرا المساحة.'];
        }

        $percent = (int) round($free / $total * 100);
        $gb = round($free / 1024 ** 3, 1);

        return [
            'key' => 'disk',
            'label' => 'مساحة السيرفر',
            'status' => match (true) {
                $percent < 5 => self::FAIL,
                $percent < 15 => self::WARN,
                default => self::OK,
            },
            'detail' => "فاضي {$gb} جيجا ({$percent}٪).",
            'fix' => $percent < 15 ? 'امسح اللوجات القديمة وصور الـ Docker اللي مش مستخدمة: sudo docker image prune -a' : null,
        ];
    }

    private function waha(): array
    {
        $base = ['key' => 'waha', 'label' => 'سيرفر واتساب (WAHA)'];

        if (! WahaClient::configured()) {
            return $base + ['status' => self::OFF, 'detail' => 'مش متظبط — ربط رقم التاجر مقفول.'];
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.whatsapp.waha.url'), '/'))
                ->withHeaders(['X-Api-Key' => (string) config('services.whatsapp.waha.api_key')])
                ->acceptJson()
                ->timeout(3)
                ->get('/api/sessions', ['all' => 'true']);
        } catch (\Throwable) {
            return $base + [
                'status' => self::FAIL,
                'detail' => 'مش بيرد — رسايل التأكيد من أرقام التجار واقفة.',
                'fix' => 'cd /var/www/matgarpro/deploy/waha && sudo docker compose up -d',
            ];
        }

        if (! $response->successful()) {
            return $base + [
                'status' => self::FAIL,
                'detail' => "رد بخطأ {$response->status()}" . ($response->status() === 401 ? ' — المفتاح في .env مش هو اللي في deploy/waha/.env.' : '.'),
            ];
        }

        $sessions = collect((array) $response->json());
        $working = $sessions->where('status', WahaClient::WORKING)->count();

        return $base + [
            'status' => self::OK,
            'detail' => "شغّال · {$working} من {$sessions->count()} رقم متوصّل.",
        ];
    }

    private function metaWhatsapp(): array
    {
        return [
            'key' => 'meta',
            'label' => 'رقم واتساب الرسمي (ميتا)',
            'status' => PlatformWhatsapp::configured() ? self::OK : self::OFF,
            'detail' => PlatformWhatsapp::configured()
                ? 'متظبط. حالة القالب في صفحة «واتساب».'
                : 'مش متظبط — التجار شايفينه «قريبًا».',
        ];
    }

    private function debugMode(): array
    {
        $debug = (bool) config('app.debug');
        $production = app()->environment('production');

        return [
            'key' => 'debug',
            'label' => 'وضع التصحيح',
            'status' => $debug && $production ? self::FAIL : self::OK,
            'detail' => match (true) {
                $debug && $production => 'شغّال على السيرفر الحقيقي — أي خطأ بيعرض للزوار تفاصيل الكود والإعدادات.',
                $debug => 'شغّال (بيئة ' . app()->environment() . ').',
                default => 'مقفول.',
            },
            'fix' => $debug && $production ? 'حط APP_DEBUG=false في .env وانشر تاني.' : null,
        ];
    }

    private function ai(): array
    {
        $configured = filled(config('services.anthropic.key'));

        return [
            'key' => 'ai',
            'label' => 'الذكاء الاصطناعي (Claude)',
            'status' => $configured ? self::OK : self::WARN,
            'detail' => $configured ? 'المفتاح موجود.' : 'مفيش مفتاح — كتابة المنتجات وتجهيز المتجر والملخصات مش هتشتغل.',
            'fix' => $configured ? null : 'حط ANTHROPIC_API_KEY في .env وانشر تاني.',
        ];
    }

    /** A heartbeat check: ok while fresh, failed once it goes quiet. */
    private function beat(string $key, string $label, ?CarbonImmutable $last, string $does, string $fix): array
    {
        $fresh = $last && $last->gt(now()->subMinutes(self::STALE_AFTER_MINUTES));

        return [
            'key' => $key,
            'label' => $label,
            'status' => $fresh ? self::OK : self::FAIL,
            'detail' => match (true) {
                $fresh => 'شغّال · آخر نبضة ' . $last->diffForHumans() . '.',
                $last !== null => 'واقف من ' . $last->diffForHumans() . " — {$does} مش هتتنفذ.",
                default => "مفيش أي نبضة لسه — لو لسه ناشر، استنى دقيقتين. غير كده {$does} مش هتتنفذ.",
            },
            'fix' => $fresh ? null : $fix,
        ];
    }
}
