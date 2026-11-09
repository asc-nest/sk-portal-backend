<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SkOfficialProfile;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SuperAdminDashboardTest extends TestCase
{
    public function test_super_admin_can_access_dashboard_stats(): void
    {
        $admin = User::where('email', 'admin@skportal.gov.ph')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard-stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_users',
                    'active_users',
                    'suspended_users',
                    'new_registrations_30d',
                    'total_officials',
                    'barangays_covered',
                    'role_breakdown',
                    'recent_activities',
                    'registration_trends',
                ],
            ]);
    }

    public function test_non_admin_cannot_access_super_admin_routes(): void
    {
        $chairperson = User::where('email', 'chairperson@skportal.gov.ph')->first();
        $this->assertNotNull($chairperson);

        $response = $this->actingAs($chairperson)->getJson('/api/v1/admin/dashboard-stats');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'FORBIDDEN_SUPER_ADMIN_ONLY');
    }

    public function test_super_admin_can_paginate_and_search_users(): void
    {
        $admin = User::where('email', 'admin@skportal.gov.ph')->first();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/users?search=Santos&per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_super_admin_can_toggle_user_status_and_activity_is_logged(): void
    {
        $admin = User::where('email', 'admin@skportal.gov.ph')->first();
        $chairperson = User::where('email', 'chairperson@skportal.gov.ph')->first();

        // Suspend
        $response = $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$chairperson->id}/status", [
            'is_active' => false,
            'reason' => 'Audit suspension test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $chairperson->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user_suspended',
            'target_id' => $chairperson->id,
        ]);

        // Restore
        $restoreResponse = $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$chairperson->id}/status", [
            'is_active' => true,
        ]);

        $restoreResponse->assertStatus(200)
            ->assertJsonPath('data.is_active', true);
    }
}

