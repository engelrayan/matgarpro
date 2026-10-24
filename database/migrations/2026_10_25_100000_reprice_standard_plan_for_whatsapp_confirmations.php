<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The public plan moves from 0.50 to 0.75 an order.
 *
 * The free months became cheaper months (0.50 during them, via
 * billing.intro_discount) and a WhatsApp confirmation now adds 0.50, so the
 * plan's own price — the one after the introductory months — goes up a
 * quarter. Data, not schema; and only if the plan still has the old price, so
 * a price an operator has since set by hand is left alone.
 */
return new class extends Migration
{
    private const DESCRIPTION = 'أول ٣ شهور: الطلب بنص جنيه، وبتأكيد واتساب بجنيه. بعدها ٧٥ قرش وجنيه وربع. مافيش اشتراك شهري ولا نسبة من مبيعاتك.';

    public function up(): void
    {
        DB::table('billing_plans')
            ->where('code', 'standard')
            ->where('price_per_order', 0.50)
            ->update(['price_per_order' => 0.75, 'description' => self::DESCRIPTION, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('billing_plans')
            ->where('code', 'standard')
            ->where('price_per_order', 0.75)
            ->update([
                'price_per_order' => 0.50,
                'description' => '٣ شهور مجانية، وبعدها نص جنيه على الطلب. مافيش اشتراك شهري ولا نسبة من مبيعاتك.',
                'updated_at' => now(),
            ]);
    }
};
