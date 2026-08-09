<?php

namespace App\Jobs;

use App\Models\StoreDomain;
use App\Services\Storefront\CertificateIssuer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gets a certificate for one merchant domain, off the request.
 *
 * Queued because talking to Let's Encrypt takes seconds and can hang: a
 * merchant pressing "check again" must get their page back immediately, not
 * watch a spinner while an ACME server decides.
 *
 * `tries = 1` on purpose. The retry policy lives in the database
 * (`ssl_retry_after`), not in the queue, because Let's Encrypt rate-limits
 * failures per hostname per hour — a queue that retries in ninety seconds
 * spends the merchant's whole hourly budget before anyone has fixed anything,
 * and then locks out the fix too.
 */
class IssueStoreDomainCertificate implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly int $domainId) {}

    public function handle(CertificateIssuer $issuer): void
    {
        $domain = StoreDomain::find($this->domainId);

        // Detached between queueing and running — a real race on a screen with
        // a delete button next to a re-check button.
        if (! $domain) {
            return;
        }

        $issuer->issue($domain);
    }

    /**
     * One certificate attempt per domain in flight at a time.
     *
     * Without this, a merchant pressing "check again" three times queues three
     * ACME orders for the same hostname — which is how a domain hits the rate
     * limit through nothing but impatience.
     */
    public function uniqueId(): string
    {
        return 'cert:' . $this->domainId;
    }
}
