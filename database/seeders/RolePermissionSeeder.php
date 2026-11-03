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

        // Create Roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $chairperson = Role::firstOrCreate(['name' => 'sk_chairperson']);
        $secretary = Role::firstOrCreate(['name' => 'sk_secretary']);
        $treasurer = Role::firstOrCreate(['name' => 'sk_treasurer']);
        $kagawad = Role::firstOrCreate(['name' => 'sk_kagawad']);

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
