<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_pixels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Room for tiktok/snap later without another table; only `meta`
            // is wired up today.
            $table->enum('provider', ['meta'])->default('meta');

            $table->string('pixel_id', 32);

            /*
             | The merchant's own Conversions API token, encrypted at rest.
             | It can post conversions to their ad account, so it is a
             | credential in every sense — never logged, never sent to the
             | browser, never returned by an API read.
             */
            $table->text('access_token')->nullable();

            // Meta's Events Manager "Test Events" code. Present only while a
            // merchant is verifying the setup; events carrying it do not count
            // as real conversions.
            $table->string('test_event_code', 32)->nullable();

            $table->boolean('is_active')->default(true);

            // Last server-side send outcome, so the settings screen can show
            // "working" or the actual reason it is not.
            $table->timestamp('last_event_at')->nullable();
            $table->string('last_error', 500)->nullable();

            $table->timestamps();

            // One pixel id per store per provider — adding the same pixel twice
            // would double every conversion the merchant reports.
            $table->unique(['store_id', 'provider', 'pixel_id']);
            $table->index(['store_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_pixels');
    }
};
