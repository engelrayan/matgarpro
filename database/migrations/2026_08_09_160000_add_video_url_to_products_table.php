<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An optional product video.
     *
     * A column rather than a key in the `settings` blob: `settings` holds how
     * the page behaves (button wording, sticky bar), and this is content — it
     * sits next to `description` conceptually and wants to be queryable the
     * day anyone asks "how many products have a video".
     *
     * The raw URL is stored, not the extracted id, so a merchant reopening the
     * form sees the address they pasted rather than eleven characters they do
     * not recognise. The id is extracted at render time by Support\Video, and
     * validation refuses anything it cannot extract one from.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('video_url', 300)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('video_url');
        });
    }
};
