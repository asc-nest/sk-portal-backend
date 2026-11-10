<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminRoleTest extends TestCase
{
    public function test_super_admin_can_list_roles_and_permissions(): void
    {
        $admin = User::where('email', 'admin@skportal.gov.ph')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'roles',
                    'all_permissions',
                ],
            ]);
    }

    public function test_super_admin_can_create_new_role(): void
    {
        $admin = User::where('email', 'admin@skportal.gov.ph')->first();
        $this->assertNotNull($admin);

        // Delete test role if previously created
        Role::where('name', 'test_role')->delete();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/roles', [
            'name' => 'test_role',
            'display_name' => 'Test Role Officer',
            'description' => 'Test role created for verification testing.',
            'permissions' => ['view officials'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'test_role')
            ->assertJsonPath('data.display_name', 'Test Role Officer');

        $this->assertDatabaseHas('roles', [
            'name' => 'test_role',
            'display_name' => 'Test Role Officer',
        ]);
    }

    public function test_super_admin_can_update_role(): void
    {
        $admin = User::where('email', 'admin@skportal.gov.ph')->first();
        $this->assertNotNull($admin);

        $role = Role::where('name', 'sk_kagawad')->first();
        $this->assertNotNull($role);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/roles/{$role->id}", [
            'display_name' => 'SK Kagawad (Councilor)',
            'description' => 'Updated description for SK Kagawad.',
            'permissions' => ['view officials', 'edit own profile', 'view own profile'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.display_name', 'SK Kagawad (Councilor)')
            ->assertJsonPath('data.description', 'Updated description for SK Kagawad.');
    }
}
