<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed role & permission dasar ZENTRO. Idempotent (aman dijalankan berulang).
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'manage_users',
        'manage_sellers',
        'approve_sellers',
        'suspend_sellers',
        'manage_products',
        'manage_categories',
        'manage_orders',
        'manage_payments',
        'manage_refunds',
        'manage_commissions',
        'manage_payouts',
        'manage_vouchers',
        'manage_reviews',
        'view_reports',
    ];

    /**
     * @var list<string>
     */
    public const ROLES = [
        'super_admin',
        'admin',
        'customer',
        'seller',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        // Super admin: seluruh permission (juga dilindungi Gate::before).
        Role::findByName('super_admin', 'web')
            ->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Admin: seluruh permission kecuali manage_users.
        Role::findByName('admin', 'web')
            ->syncPermissions(
                Permission::where('guard_name', 'web')
                    ->where('name', '!=', 'manage_users')
                    ->get()
            );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
