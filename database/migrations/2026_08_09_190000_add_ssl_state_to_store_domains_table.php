<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Certificate state, tracked separately from DNS state.
     *
     * `status` already answers "does this hostname point at us". It cannot
     * also answer "can a browser open it without a warning" — a domain can be
     * verified for hours while its certificate is still queued, and until now
     * the merchant had no way to tell those two apart. They watched a green
     * "شغّال" badge and a red "Not secure" in the address bar at the same time,
     * which is worse than no badge at all.
     *
     * `ssl_issued_at` already existed and was never written by anything.
     */
    public function up(): void
    {
        Schema::table('store_domains', function (Blueprint $table) {
            /*
             | pending  nothing tried yet — the domain is not verified, or is
             |          waiting in the queue
             | issuing  a job is talking to Let's Encrypt right now
             | issued   a live certificate exists and nginx is serving it
             | failed   the CA refused, and `ssl_error` says why
             */
            $table->string('ssl_status', 12)->default('pending')->after('ssl_issued_at');

            // The CA's own words, kept verbatim. A paraphrase of an ACME error
            // is a paraphrase of the only sentence that explains the failure.
            $table->text('ssl_error')->nullable()->after('ssl_status');

            // Let's Encrypt issues for 90 days. Certbot renews on its own
            // timer; this is here so the panel can see a renewal that stopped
            // happening BEFORE a customer sees a browser warning.
            $table->timestamp('ssl_expires_at')->nullable()->after('ssl_error');

            $table->unsignedSmallInteger('ssl_attempts')->default(0)->after('ssl_expires_at');

            // Let's Encrypt rate-limits hard (5 failures per account per
            // hostname per hour). Retrying on a fixed schedule after a failure
            // burns that budget and locks the domain out for everyone.
            $table->timestamp('ssl_retry_after')->nullable()->after('ssl_attempts');

            $table->index(['ssl_status', 'ssl_retry_after']);
        });
    }

    public function down(): void
    {
        Schema::table('store_domains', function (Blueprint $table) {
            $table->dropIndex(['ssl_status', 'ssl_retry_after']);
            $table->dropColumn(['ssl_status', 'ssl_error', 'ssl_expires_at', 'ssl_attempts', 'ssl_retry_after']);
        });
    }
};
