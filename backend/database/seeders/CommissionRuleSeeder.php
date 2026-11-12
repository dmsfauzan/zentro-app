<?php

namespace Database\Seeders;

use App\Modules\Finance\Models\CommissionRule;
use Illuminate\Database\Seeder;

class CommissionRuleSeeder extends Seeder
{
    public function run(): void
    {
        CommissionRule::query()->updateOrCreate(
            ['name' => 'Komisi Platform Default'],
            [
                'scope' => 'global',
                'category_id' => null,
                'seller_id' => null,
                'rate_bp' => (int) config('zentro.commission.default_rate_bp', 500),
                'includes_shipping' => false,
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'priority' => 0,
                'is_active' => true,
            ],
        );
    }
}
