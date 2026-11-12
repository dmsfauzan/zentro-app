<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * Membuat akun demo untuk pengembangan lokal (tanpa kredensial Firebase nyata).
 * Pasangkan firebase_uid ini dengan akun Firebase Anda memakai FakeFirebaseTokenVerifier.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['uid' => 'demo-super-admin', 'name' => 'Super Admin', 'email' => 'superadmin@zentro.test', 'role' => 'super_admin'],
            ['uid' => 'demo-admin', 'name' => 'Admin Zentro', 'email' => 'admin@zentro.test', 'role' => 'admin'],
            ['uid' => 'demo-customer', 'name' => 'Customer Demo', 'email' => 'customer@zentro.test', 'role' => 'customer'],
            ['uid' => 'demo-seller', 'name' => 'Seller Demo', 'email' => 'seller@zentro.test', 'role' => 'seller'],
        ];

        foreach ($accounts as $account) {
            $user = User::query()->firstOrCreate(
                ['firebase_uid' => $account['uid']],
                [
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                ],
            );

            $user->syncRoles([$account['role']]);
        }
    }
}
