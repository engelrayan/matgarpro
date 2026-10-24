<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | A merchant saying "I am adding money to my balance".
         |
         | Until there is online payment the money moves outside the platform
         | (an InstaPay transfer), so this row is a claim an operator checks
         | against their bank app and then confirms — possibly for a different
         | amount than was picked, because a transfer is what it is. Only a
         | confirmation moves the wallet, through WalletService, with this row
         | as the ledger entry's source.
         */
        Schema::create('store_topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // What the merchant picked from config('billing.topup.amounts').
            $table->decimal('amount', 12, 2);
            // What actually arrived, set by the operator on confirming.
            $table->decimal('confirmed_amount', 12, 2)->nullable();

            $table->string('method', 20)->default('instapay');
            $table->string('status', 20)->default('pending'); // pending | confirmed | rejected | cancelled

            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            // Shown to the merchant on a rejection, so it is written for them.
            $table->string('review_note', 255)->nullable();

            $table->foreignId('wallet_transaction_id')->nullable()
                ->constrained('store_wallet_transactions')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_topup_requests');
    }
};
