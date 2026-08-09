<?php

namespace Database\Seeders;

use App\Models\BillingPlan;
use Illuminate\Database\Seeder;

class BillingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'free',
                'name' => 'مجاني',
                'description' => 'متجر كامل بدون أي رسوم. مناسب للبداية.',
                'price_per_order' => 0,
                'monthly_fee' => 0,
                'included_orders_monthly' => 0,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'growth',
                'name' => 'نمو',
                'description' => 'جنيه واحد على كل طلب — تدفع لما تبيع بس.',
                'price_per_order' => 1.00,
                'monthly_fee' => 0,
                'included_orders_monthly' => 50,
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'code' => 'pro',
                'name' => 'احترافي',
                'description' => 'نص جنيه على الطلب مقابل اشتراك شهري ثابت.',
                'price_per_order' => 0.50,
                'monthly_fee' => 299.00,
                'included_orders_monthly' => 500,
                'is_default' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            BillingPlan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
