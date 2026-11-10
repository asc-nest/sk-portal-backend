<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'view officials',
            'manage officials',
            'generate invitations',
            'edit own profile',
            'view own profile',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create Roles with default display attributes
        $rolesWithMeta = [
            'super_admin' => [
                'display_name' => 'Super Administrator',
                'description' => 'Full administrative access across all 38 barangays, user control, system configurations, and security audits.',
            ],
            'sk_chairperson' => [
                'display_name' => 'SK Chairperson',
                'description' => 'Executive council authority, legislative presidency, council membership invitations, and barangay representation.',
            ],
            'sk_secretary' => [
                'display_name' => 'SK Secretary',
                'description' => 'Secretariat operations, Sanggunian session minutes, roll call ledger maintenance, and youth registries.',
            ],
            'sk_treasurer' => [
                'display_name' => 'SK Treasurer',
                'description' => 'Financial operations, youth development investment allocations, treasury reporting, and budget desk.',
            ],
            'sk_kagawad' => [
                'display_name' => 'SK Kagawad (Councilor)',
                'description' => 'Legislative sponsorship, committee chairmanship, youth sports/education advocacy, and community outreach.',
            ],
        ];

        foreach ($rolesWithMeta as $roleName => $meta) {
            Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => config('auth.defaults.guard', 'web')],
                $meta
            );
        }

        $superAdmin = Role::findByName('super_admin', 'web');
        $chairperson = Role::findByName('sk_chairperson', 'web');
        $secretary = Role::findByName('sk_secretary', 'web');
        $treasurer = Role::findByName('sk_treasurer', 'web');
        $kagawad = Role::findByName('sk_kagawad', 'web');

        // Assign Permissions to Roles
        $superAdmin->givePermissionTo(Permission::all());
        $chairperson->givePermissionTo([
            'view officials',
            'manage officials',
            'generate invitations',
            'edit own profile',
            'view own profile',
        ]);
        $secretary->givePermissionTo([
            'view officials',
            'generate invitations',
            'edit own profile',
            'view own profile',
        ]);
        $treasurer->givePermissionTo([
            'view officials',
            'edit own profile',
            'view own profile',
        ]);
        $kagawad->givePermissionTo([
            'view officials',
            'edit own profile',
            'view own profile',
        ]);
    }
}
