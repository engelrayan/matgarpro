<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per page a merchant can lay out.
     *
     * The section list is JSON rather than a `store_sections` table with a
     * `sort_order` column, and that is the load-bearing decision here:
     *
     *  1. **Publishing is one write.** Draft and live are two columns on one
     *     row, so "نشر" is a single UPDATE. With rows-per-section it would be a
     *     delete-and-reinsert of an unknown number of rows, and a customer
     *     landing mid-publish would see half a page.
     *  2. **Reverting is free.** Copying `published_sections` back over the
     *     draft undoes an editing session completely, including sections that
     *     were added and deleted along the way.
     *  3. A page is always read whole — never "give me section 3" — so the
     *     query flexibility a table would buy is flexibility nothing uses.
     *
     * `published_sections` NULL means the merchant never published this page,
     * and the storefront falls back to the platform's default layout. That is
     * not the same as an empty array, which means "I published a blank page".
     */
    public function up(): void
    {
        Schema::create('store_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // home | product | category | header | footer
            $table->string('key', 20);

            $table->json('draft_sections')->nullable();
            $table->json('published_sections')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_pages');
    }
};
