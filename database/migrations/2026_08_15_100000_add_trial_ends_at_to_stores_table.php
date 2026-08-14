<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three free months we advertise, made real.
 *
 * Stamped per store rather than derived from `created_at` and a config value,
 * for two reasons: shortening the offer later must not retroactively end a
 * running trial, and an operator needs to be able to extend one store — or end
 * it — without touching anyone else.
 *
 * Null means no trial: demo showrooms and any store an operator has explicitly
 * moved onto paid billing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->timestamp('trial_ends_at')->nullable()->after('billing_status');
        });

        /*
         | Stores that already exist signed up under the same offer, so they get
         | what is left of it counted from the day they registered — not three
         | fresh months, which would hand an early merchant a longer trial than
         | the one they were promised.
         |
         | Floored at two weeks out. A store old enough for the computed date to
         | have already passed would otherwise begin paying the instant this
         | migration lands, with no notice at all — a bill nobody saw coming is
         | how you lose a merchant you already won.
         */
        $months = (int) config('billing.trial_months', 3);
        $floor = now()->addDays(14)->toDateTimeString();

        DB::table('stores')
            ->whereNull('trial_ends_at')
            ->where('is_demo', false)
            ->update([
                'trial_ends_at' => DB::raw(
                    "GREATEST(DATE_ADD(created_at, INTERVAL {$months} MONTH), '{$floor}')"
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn('trial_ends_at');
        });
    }
};
