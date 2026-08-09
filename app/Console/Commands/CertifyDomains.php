<?php

namespace App\Console\Commands;

use App\Jobs\IssueStoreDomainCertificate;
use App\Models\StoreDomain;
use App\Services\Storefront\CertificateIssuer;
use Illuminate\Console\Command;

/**
 * The safety net under the automatic path.
 *
 * A certificate is normally requested the moment a domain verifies. This
 * sweeps up everything that fell through: a job lost to a worker restart, a
 * domain that failed and has now served its backoff, and the first run after
 * this feature is switched on for shops that were already live.
 *
 * Meant for the scheduler, hourly. It is idempotent — a domain already holding
 * a certificate is skipped without touching the CA.
 */
class CertifyDomains extends Command
{
    protected $signature = 'domains:certify
        {domain? : دومين واحد بالاسم، بدل ما يمشي على الكل}
        {--sync : نفّذ فورًا بدل ما يتحط في الطابور}
        {--force : جرّب حتى لو الدومين مستنّي فترة الانتظار}';

    protected $description = 'إصدار شهادات الأمان لدومينات التجار اللي محتاجاها';

    public function handle(CertificateIssuer $issuer): int
    {
        if (! $issuer->enabled()) {
            $this->warn('إصدار الشهادات مقفول (STOREFRONT_SSL_ENABLED=false).');

            return self::SUCCESS;
        }

        $domains = StoreDomain::query()
            ->where('status', StoreDomain::STATUS_ACTIVE)
            ->where('ssl_status', '!=', StoreDomain::SSL_ISSUED)
            ->when($this->argument('domain'), fn ($q, $name) => $q->where('domain', $name))
            ->when(! $this->option('force'), fn ($q) => $q->where(
                fn ($inner) => $inner->whereNull('ssl_retry_after')->orWhere('ssl_retry_after', '<=', now()),
            ))
            ->get();

        if ($domains->isEmpty()) {
            $this->info('مفيش دومين محتاج شهادة دلوقتي.');

            return self::SUCCESS;
        }

        foreach ($domains as $domain) {
            if ($this->option('force')) {
                $domain->forceFill(['ssl_retry_after' => null])->save();
            }

            if ($this->option('sync')) {
                $ok = $issuer->issue($domain);
                $this->line(($ok ? '  ✓ ' : '  ✗ ') . $domain->domain . ($ok ? '' : ' — ' . $domain->fresh()->ssl_error));

                continue;
            }

            IssueStoreDomainCertificate::dispatch($domain->id);
            $this->line('  → ' . $domain->domain . ' اتحط في الطابور');
        }

        $this->info($domains->count() . ' دومين.');

        return self::SUCCESS;
    }
}
