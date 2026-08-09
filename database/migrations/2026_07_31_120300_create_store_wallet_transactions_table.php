<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            /*
             | topup            merchant added credit
             | order_fee        per-order charge
             | subscription_fee monthly plan fee
             | refund           we gave money back (cancelled order, our fault)
             | adjustment       manual correction by an operator, always with a note
             */
            $table->enum('type', [
                'topup', 'order_fee', 'subscription_fee', 'refund', 'adjustment',
            ]);

            // Signed: credits are positive, charges are negative. Storing the
            // sign here means SUM(amount) is the balance — no CASE expressions,
            // and no chance of a debit being summed as a credit.
            $table->decimal('amount', 12, 2);

            // Balance immediately after this row was applied. Written inside the
            // same locked transaction, so the ledger can be audited row by row
            // without replaying every prior entry.
            $table->decimal('balance_after', 12, 2);

            $table->string('description')->nullable();

            // What caused this entry (an order, an invoice, a payment).
            $table->nullableMorphs('source');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'created_at']);
            $table->index(['store_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_wallet_transactions');
    }
};
