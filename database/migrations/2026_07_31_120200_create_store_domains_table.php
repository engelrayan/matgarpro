<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            /*
             | Punycode, lower-cased, no scheme/port/trailing dot. Globally
             | unique: a hostname resolves to exactly one store, and the unique
             | index is what stops one merchant from hijacking another's domain
             | even if two requests race.
             */
            $table->string('domain')->unique();

            // The domain used to build canonical URLs and redirects for the
            // store. Exactly one per store; enforced in the application layer.
            $table->boolean('is_primary')->default(false);

            $table->enum('status', ['pending', 'active', 'failed'])->default('pending');

            // Handed to the merchant for the optional TXT proof, used when the
            // domain is still live elsewhere and cannot be pointed at us yet.
            $table->string('verification_token', 64);

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('check_attempts')->default(0);
            $table->string('last_error')->nullable();

            // Set once a certificate has actually been issued for this hostname,
            // so the UI can distinguish "DNS is right" from "HTTPS works".
            $table->timestamp('ssl_issued_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'last_checked_at']);
            $table->index(['store_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_domains');
    }
};
