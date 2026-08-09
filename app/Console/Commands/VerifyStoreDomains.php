<?php

namespace App\Console\Commands;

use App\Models\StoreDomain;
use App\Services\Storefront\StoreDomainService;
use App\Services\Storefront\StoreResolver;
use Illuminate\Console\Command;

/**
 * Re-checks domains whose DNS has not landed yet, so a merchant who pastes the
 * records and walks away comes back to a working store without pressing
 * anything. Scheduled every minute; each domain is only looked up once its
 * recheck interval has elapsed.
 */
class VerifyStoreDomains extends Command
{
    protected $signature = 'domains:verify
                            {--domain= : Check one hostname now, ignoring its interval}';

    protected $description = 'Re-check pending custom domains and activate the ones now pointing at us';

    public function handle(StoreDomainService $domains, StoreResolver $resolver): int
    {
        $query = StoreDomain::query();

        if ($host = $this->option('domain')) {
            $query->where('domain', $domains->normalize($host));
        } else {
            $interval = (int) config('storefront.verification.recheck_minutes');

            $query->whereIn('status', [StoreDomain::STATUS_PENDING, StoreDomain::STATUS_FAILED])
                ->where(fn ($q) => $q
                    ->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<=', now()->subMinutes($interval)));
        }

        $activated = 0;
        $checked = 0;

        foreach ($query->cursor() as $domain) {
            $before = $domain->status;
            $domain = $domains->verify($domain);
            $checked++;

            if ($domain->status === StoreDomain::STATUS_ACTIVE && $before !== StoreDomain::STATUS_ACTIVE) {
                // The resolver caches "this host belongs to nobody"; without
                // clearing it the store stays 404 until the TTL expires.
                $resolver->forget($domain->domain);

                $activated++;
                $this->info("✔ {$domain->domain} بقى شغّال");
            }
        }

        $this->line("تم فحص {$checked} دومين، اتفعّل منهم {$activated}.");

        return self::SUCCESS;
    }
}
