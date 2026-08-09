<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->string('type', 40)->default('order');

            // The thing being billed for. Part of the idempotency key below.
            $table->string('billable_type')->nullable();
            $table->unsignedBigInteger('billable_id')->nullable();

            $table->unsignedInteger('quantity')->default(1);

            /*
             | The price in force at the moment of the event, recorded here and
             | never recomputed. Re-deriving it from the plan later means a
             | pricing change silently rewrites historical invoices — the exact
             | bug that corrupted merchant statements in the Daman ledger.
             */
            $table->decimal('unit_price', 8, 2);
            $table->decimal('amount', 12, 2);

            // Which plan/override produced `unit_price`, for dispute forensics.
            $table->foreignId('billing_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('price_source', 20)->default('plan'); // override | plan | default

            $table->foreignId('wallet_transaction_id')->nullable()
                ->constrained('store_wallet_transactions')->nullOnDelete();

            $table->timestamp('occurred_at');
            $table->timestamps();

            /*
             | Idempotency. A retried webhook, a double-submitted checkout or a
             | replayed queue job must never charge twice; the database refuses
             | it rather than the application remembering to check.
             */
            $table->unique(['store_id', 'type', 'billable_type', 'billable_id'], 'usage_events_idempotency');
            $table->index(['store_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_usage_events');
    }
};
