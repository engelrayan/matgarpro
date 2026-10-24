<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | One row per request to Claude, written by ClaudeGateway and nothing
         | else — so no feature can forget to meter itself.
         |
         | A call, not a feature run: a copilot answer may take several calls,
         | and each one costs money on its own.
         */
        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('feature', 40);
            // What the API says actually answered — not what was asked for. A
            // policy fallback can hand the request to another model, priced
            // differently.
            $table->string('model', 60);
            $table->string('status', 20); // ok | refused | incomplete | failed

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('cache_read_tokens')->default(0);
            /*
             | Estimated from the token counts and config('ai.pricing') at the
             | moment of the call, and never recomputed — same rule as
             | store_usage_events.unit_price: a price change must not rewrite
             | what yesterday cost.
             */
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);

            // Anthropic's message id, for when we need to ask them about one.
            $table->string('request_id', 100)->nullable();
            $table->string('error', 500)->nullable();

            // What the call was for, e.g. the AiGeneration it produced. A string
            // id, not nullableMorphs(): generations are keyed by ULID, orders
            // and messages by integer, and both have to fit.
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->index(['subject_type', 'subject_id']);

            /*
             | Not charged today. When pricing is decided, UsageBiller writes a
             | store_usage_events row per call (billable = this row, so its
             | idempotency key already holds) and links it back here.
             */
            $table->foreignId('store_usage_event_id')->nullable()
                ->constrained('store_usage_events')->nullOnDelete();

            $table->timestamps();

            // The daily cap reads exactly these.
            $table->index(['store_id', 'created_at']);
            $table->index(['store_id', 'feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_calls');
    }
};
