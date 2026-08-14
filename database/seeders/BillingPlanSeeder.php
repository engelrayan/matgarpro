<?php

namespace Database\Seeders;

use App\Models\BillingPlan;
use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * The plans we actually sell.
 *
 * There used to be three, and one of them could not be honoured: the landing
 * page promises "half a pound per order, no monthly subscription", while the
 * only half-pound plan carried a 299 EGP monthly fee that nothing ever
 * charged. Two plans now, both real:
 *
 *  - `standard` is the public offer, and every new store lands on it. The
 *    three free months come from the store's own trial clock, not from a
 *    separate free plan, so nobody has to be migrated between plans on the day
 *    their trial ends.
 *
 *  - `founding` is the design partners — the first merchants who onboarded
 *    while the platform was still being proven, and who were promised no
 *    charges for good. It is not offered publicly.
 */
class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'standard',
                'name' => 'أساسي',
                'description' => '٣ شهور مجانية، وبعدها نص جنيه على الطلب. مافيش اشتراك شهري ولا نسبة من مبيعاتك.',
                'price_per_order' => 0.50,
                'is_default' => true,
                'is_public' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'founding',
                'name' => 'شريك تأسيس',
                'description' => 'مجاني بالكامل ومدى الحياة — للتجار اللي بدأوا معانا من الأول.',
                'price_per_order' => 0,
                'is_default' => false,
                'is_public' => false,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $plan) {
            BillingPlan::updateOrCreate(['code' => $plan['code']], $plan);
        }

        $retired = BillingPlan::whereIn('code', ['free', 'growth', 'pro']);

        /*
         | Stores sitting on a retired plan move to the public one.
         |
         | They signed up reading "three months free, then half a pound" — the
         | offer `standard` plus their trial clock reproduces exactly. Leaving
         | them on the old default would quietly build a population of stores
         | that never starts paying, and the trial backfill already guarantees
         | none of them is charged without notice.
         */
        Store::whereIn('billing_plan_id', (clone $retired)->pluck('id'))
            ->update(['billing_plan_id' => BillingPlan::where('code', 'standard')->value('id')]);

        /*
         | Retired rather than deleted: a usage row records the plan it was
         | billed under, and deleting the row would orphan the history that
         | explains an old charge.
         */
        $retired->update(['is_active' => false, 'is_default' => false, 'is_public' => false]);
    }
}
