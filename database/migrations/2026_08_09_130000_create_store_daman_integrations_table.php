<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_daman_integrations', function (Blueprint $table) {
            $table->id();

            // One Daman account per store. A merchant with two shops links each
            // one separately — otherwise a shipment's reference would not say
            // which storefront it came from.
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();

            /*
             | The merchant's own Daman API key, encrypted at rest.
             |
             | It can create real shipments and read every order on their Daman
             | account, so it is a credential in the strongest sense: never
             | logged, never returned to the browser, never shown again after
             | it is saved. The prefix is kept in clear only so the settings
             | screen can say which key is installed.
             */
            $table->text('api_key');
            $table->string('key_prefix', 24)->nullable();

            // Derived from the key itself (dm_test_ / dm_live_) rather than
            // asked for: a merchant who picks the wrong one here would think
            // they were shipping while nothing left the warehouse.
            $table->enum('environment', ['test', 'live'])->default('live');

            $table->boolean('is_active')->default(true);

            /*
             | Is the amount the courier collects already inclusive of shipping?
             |
             | Has to match the same setting on the merchant's Daman account. If
             | Daman thinks shipping is extra and we send the order total, the
             | courier collects the shipping twice and the customer is asked for
             | more money than the store quoted them.
             */
            $table->boolean('cod_includes_shipping')->default(true);

            /*
             | Daman pushes status changes to one URL per merchant account, so
             | the store is identified by an unguessable token in the path. The
             | signing secret is Daman's `whsec_…`, pasted by the merchant, and
             | is what proves a request really came from Daman.
             */
            $table->string('webhook_token', 40)->unique();
            $table->text('webhook_secret')->nullable();

            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_shipped_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();

            // Last failure, so the settings screen can show the actual reason
            // rather than a generic "not working".
            $table->string('last_error', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_daman_integrations');
    }
};
