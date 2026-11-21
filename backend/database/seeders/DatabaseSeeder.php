<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(RegionSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(CommissionRuleSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoUsersSeeder::class);
        }
    }
}
