<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide delivery record for a phone number.
     *
     * The single most valuable thing we know that a store-builder cannot: we
     * ship the parcels, so we learn whether each one was accepted or refused.
     * A merchant about to send goods to a repeat refuser is about to pay
     * shipping twice for nothing.
     *
     * Deliberately an aggregate, not a log of who bought what from whom. A
     * merchant sees a signal about a phone number — never another store's
     * customer, order or basket. That boundary is the difference between a
     * useful warning and handing one merchant a competitor's customer list.
     */
    public function up(): void
    {
        Schema::create('customer_reputations', function (Blueprint $table) {
            $table->id();

            /*
             | The phone in normalised local form (01…), which is what every
             | order already stores. Unique: this row IS the number's record.
             |
             | Not hashed. It has to be looked up by the exact value on every
             | checkout, and a hash of an 11-digit number with a known prefix
             | is trivially reversible anyway — the honest protection is the
             | narrow shape of what we keep, not a hash that pretends.
             */
            $table->string('phone', 32)->unique();

            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('refused')->default(0);
            // Shipped but not yet settled. Kept apart so a parcel in transit
            // never counts against a customer.
            $table->unsignedInteger('pending')->default(0);

            // How many distinct stores have shipped to this number. One store
            // reporting three refusals is a dispute; three stores reporting
            // one each is a pattern.
            $table->unsignedInteger('stores_count')->default(0);

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_outcome_at')->nullable();

            $table->timestamps();

            // "Is this number risky" runs on every checkout and every order row.
            $table->index(['refused', 'delivered']);
        });

        /*
         | One row per store per phone, so a store's own repeated refusals
         | count once toward `stores_count` — and so recomputing an aggregate
         | never double-counts after a status is corrected.
         */
        Schema::create('customer_reputation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_reputation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->enum('outcome', ['delivered', 'refused', 'pending']);
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            // An order contributes exactly one outcome, however many times its
            // status is edited.
            $table->unique('order_id');
            $table->index(['customer_reputation_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_reputation_entries');
        Schema::dropIfExists('customer_reputations');
    }
};
