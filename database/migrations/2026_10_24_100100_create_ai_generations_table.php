<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | A piece of AI work a merchant is waiting for on screen.
         |
         | The browser asks for it, a queued job does it, and the browser polls
         | this row until it is done. Nothing here is ever applied to the store
         | by itself — `result` is a draft the merchant reviews and saves.
         */
        Schema::create('ai_generations', function (Blueprint $table) {
            // A ULID, not an auto-increment: it sits in a URL the browser polls,
            // and a guessable id invites guessing even behind an ownership check.
            $table->ulid('id')->primary();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 40);
            $table->string('status', 20)->default('pending'); // pending | running | done | failed

            $table->json('input')->nullable();
            $table->json('result')->nullable();
            // Written for the merchant, in Arabic — never an exception message.
            $table->string('error', 500)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
