<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            /*
             | A platform-owned showroom, one per theme.
             |
             | Theme previews are real storefronts rather than a rendered mockup:
             | a merchant judging a theme from swatches is judging it blind, and
             | a mockup drifts from the real templates the moment either changes.
             |
             | The flag exists so these never count as merchants — not in
             | billing, not in admin totals, and not in "how many stores do we
             | have" — and so the storefront can show a "this is a demo" ribbon.
             */
            $table->boolean('is_demo')->default(false)->after('status');

            $table->index('is_demo');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn('is_demo');
        });
    }
};
