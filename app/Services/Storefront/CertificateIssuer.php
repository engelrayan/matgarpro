<?php

namespace App\Services\Storefront;

use App\Models\StoreDomain;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Gets a real certificate for a merchant's own domain, without anybody
 * touching the server.
 *
 * Three steps, in this order, and the order matters:
 *
 *  1. **certbot proves control of the hostname** by answering an HTTP-01
 *     challenge from our own webroot. That works only because the storefront
 *     is nginx's `default_server` — a merchant hostname already lands on us
 *     before any certificate exists, which is exactly the window ACME needs.
 *  2. **We write a small vhost** naming the new certificate. nginx cannot pick
 *     a certificate per hostname without a server block, so one file per
 *     certified domain is the price of admission. Generated, never edited by
 *     hand, and deleted when the merchant detaches the domain.
 *  3. **nginx reloads.** Reload, not restart: an open connection mid-checkout
 *     is a real order.
 *
 * Failure is recorded rather than thrown. A merchant whose DNS is half-done
 * should see "we are still trying", not a stack trace — and Let's Encrypt
 * rate-limits failures per hostname, so a tight retry loop locks the domain
 * out for everyone including the merchant who fixes it two minutes later.
 */
class CertificateIssuer
{
    public function __construct(private readonly StoreResolver $resolver) {}

    public function enabled(): bool
    {
        return (bool) config('storefront.ssl.enabled');
    }

    /**
     * Is this domain ready for a certificate attempt right now?
     *
     * Deliberately conservative: an unverified hostname cannot answer a
     * challenge, and a domain inside its backoff window must not be touched.
     */
    public function eligible(StoreDomain $domain): bool
    {
        if (! $this->enabled() || ! $domain->isServing()) {
            return false;
        }

        if ($domain->ssl_status === StoreDomain::SSL_ISSUED) {
            return false;
        }

        if ($domain->ssl_attempts >= (int) config('storefront.ssl.max_attempts')) {
            return false;
        }

        return $domain->ssl_retry_after === null || $domain->ssl_retry_after->isPast();
    }

    public function issue(StoreDomain $domain): bool
    {
        if (! $this->eligible($domain)) {
            return false;
        }

        // The hostname is interpolated into a shell argument and an nginx
        // config. It arrives already normalised, but this is the last gate
        // before both — a hostname that fails here is a bug upstream, not a
        // merchant mistake, so it fails loudly rather than being repaired.
        if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain->domain)) {
            $this->fail($domain, 'اسم الدومين مش صالح للإصدار.');

            return false;
        }

        $domain->forceFill([
            'ssl_status' => StoreDomain::SSL_ISSUING,
            'ssl_attempts' => $domain->ssl_attempts + 1,
        ])->save();

        $result = $this->runCertbot($domain->domain);

        if (! $result['ok']) {
            $this->fail($domain, $result['output']);

            return false;
        }

        try {
            $this->writeVhost($domain->domain);
            $this->reloadNginx();
        } catch (\Throwable $e) {
            // The certificate exists; only the wiring failed. Recorded as a
            // failure so somebody looks, but the retry will not ask the CA for
            // a second certificate — certbot keeps the one it already issued.
            $this->fail($domain, 'الشهادة اتصدرت بس ربطها بالسيرفر فشل: ' . $e->getMessage());

            return false;
        }

        $domain->forceFill([
            'ssl_status' => StoreDomain::SSL_ISSUED,
            'ssl_error' => null,
            'ssl_issued_at' => now(),
            'ssl_expires_at' => now()->addDays(90),
            'ssl_retry_after' => null,
        ])->save();

        // The cached hostname→store mapping carries no TLS state, but the
        // domain row it was built from has changed. Cheap insurance.
        $this->resolver->forget($domain->domain);

        return true;
    }

    /**
     * Remove a domain's certificate wiring. Called when it is detached.
     *
     * The certificate itself is left with certbot — revoking it on every
     * detach would punish a merchant who moves a domain between their own two
     * stores, and an unused certificate expires by itself in 90 days.
     */
    public function forget(string $domain): void
    {
        $path = $this->vhostPath($domain);

        if ($path && is_file($path)) {
            @unlink($path);

            try {
                $this->reloadNginx();
            } catch (\Throwable $e) {
                Log::warning('nginx reload failed after detaching a domain', [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------

    /** @return array{ok: bool, output: string} */
    private function runCertbot(string $domain): array
    {
        $process = new Process([
            /*
             | certbot has to be root: it writes to /etc/letsencrypt and
             | /var/log/letsencrypt, and the web user owns neither. The sudoers
             | rule that permits exactly this one binary is part of the install.
             |
             | `-n` is not optional. Without it, a missing sudoers rule makes
             | sudo sit waiting for a password that no queue worker will ever
             | type, and the job hangs until its timeout instead of failing with
             | a message somebody can act on.
             */
            ...(config('storefront.ssl.sudo') ? ['sudo', '-n'] : []),
            config('storefront.ssl.certbot'),
            'certonly',
            '--non-interactive',
            '--agree-tos',
            '--email', (string) config('storefront.ssl.email'),
            '--webroot',
            '-w', (string) config('storefront.ssl.webroot'),
            // One certificate per hostname, named after it, so the paths this
            // class writes into nginx are predictable.
            '--cert-name', $domain,
            '-d', $domain,
            // Re-running for an already-valid certificate is a no-op rather
            // than a fresh issuance — this is what keeps a retry from spending
            // the merchant's weekly certificate budget.
            '--keep-until-expiring',
        ]);

        $process->setTimeout(180);
        $process->run();

        return [
            'ok' => $process->isSuccessful(),
            'output' => trim($process->getErrorOutput() ?: $process->getOutput()),
        ];
    }

    private function writeVhost(string $domain): void
    {
        $dir = (string) config('storefront.ssl.vhost_dir');

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("مقدرتش أعمل مجلد الإعدادات: {$dir}");
        }

        $include = (string) config('storefront.ssl.vhost_include');
        $live = '/etc/letsencrypt/live/' . $domain;

        $config = <<<NGINX
        # Generated by متجر برو — do not edit by hand.
        # This file is rewritten whenever {$domain}'s certificate is issued,
        # and deleted when the merchant detaches the domain.
        server {
            listen 443 ssl http2;
            listen [::]:443 ssl http2;

            server_name {$domain};

            ssl_certificate     {$live}/fullchain.pem;
            ssl_certificate_key {$live}/privkey.pem;

            include {$include};
        }

        NGINX;

        if (@file_put_contents($this->vhostPath($domain), $config) === false) {
            throw new \RuntimeException('مقدرتش أكتب ملف إعدادات nginx — راجع صلاحيات المجلد.');
        }
    }

    private function reloadNginx(): void
    {
        $process = Process::fromShellCommandline((string) config('storefront.ssl.reload_command'));
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: 'nginx reload failed'));
        }
    }

    private function vhostPath(string $domain): ?string
    {
        $dir = (string) config('storefront.ssl.vhost_dir');

        return $dir === '' ? null : rtrim($dir, '/') . '/' . $domain . '.conf';
    }

    /**
     * Record a failure and decide when — if ever — to try again.
     */
    private function fail(StoreDomain $domain, string $error): void
    {
        $schedule = (array) config('storefront.ssl.retry_hours');
        // Attempts are 1-based by the time this runs; the last delay repeats
        // once the schedule runs out rather than giving up silently.
        $hours = $schedule[min($domain->ssl_attempts, count($schedule)) - 1] ?? end($schedule);

        $domain->forceFill([
            'ssl_status' => StoreDomain::SSL_FAILED,
            // Truncated: certbot is generous with output and this is read back
            // into a merchant-facing screen.
            'ssl_error' => mb_substr($error, 0, 2000),
            'ssl_retry_after' => Carbon::now()->addHours((int) $hours),
        ])->save();

        Log::warning('certificate issuance failed', [
            'domain' => $domain->domain,
            'store_id' => $domain->store_id,
            'attempt' => $domain->ssl_attempts,
            'error' => mb_substr($error, 0, 500),
        ]);
    }
}
