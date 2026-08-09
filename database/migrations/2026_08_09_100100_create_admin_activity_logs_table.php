<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only record of everything an operator did.
     *
     * `created_at` only and no model-level update path: an audit row that can
     * be edited is not an audit row. `admin_name` and `admin_email` are
     * snapshots rather than joins — a deactivated or renamed operator must
     * still read correctly years later, and the FK is nullable so removing an
     * account can never take its history with it.
     */
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_id')->nullable()
                ->constrained('admins')->nullOnDelete();
            $table->string('admin_name');
            $table->string('admin_email');

            // Machine key: `store.suspended`, `wallet.credited`, `plan.updated`.
            $table->string('action', 60)->index();

            // What was acted on. Nullable — a login has no subject.
            $table->nullableMorphs('subject');

            // One Arabic sentence, readable without opening anything else.
            $table->string('summary', 500);

            /*
             | Before/after for the fields that actually changed. Stored as
             | {"field": {"from": …, "to": …}} so a dispute can be answered
             | from this table alone, without replaying application logic.
             */
            $table->json('changes')->nullable();

            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->index();

            $table->index(['admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
