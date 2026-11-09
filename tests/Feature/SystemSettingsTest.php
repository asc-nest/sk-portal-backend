<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SystemSetting;

class SystemSettingsTest extends TestCase
{
    public function test_can_fetch_public_system_settings(): void
    {
        $response = $this->getJson('/api/v1/system/settings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'portal_name',
                    'jurisdiction_name',
                    'current_term_label',
                    'term_start_date',
                    'term_end_date',
                    'allow_public_onboarding',
                    'skmt_enforcement',
                    'session_timeout_minutes',
                    'maintenance_mode',
                ],
            ]);
    }

    public function test_super_admin_can_update_system_settings(): void
    {
        $admin = User::where('email', 'admin@skportal.gov.ph')->first();
        $this->assertNotNull($admin);

        $payload = [
            'settings' => [
                ['key' => 'portal_name', 'value' => 'Updated Taguig Portal Test'],
                ['key' => 'session_timeout_minutes', 'value' => '360'],
            ],
        ];

        $response = $this->actingAs($admin)->putJson('/api/v1/admin/settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('Updated Taguig Portal Test', SystemSetting::get('portal_name'));
        $this->assertEquals(360, SystemSetting::get('session_timeout_minutes'));
    }

    public function test_disabling_public_onboarding_blocks_registration(): void
    {
        SystemSetting::set('allow_public_onboarding', false);

        $registrationPayload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'johndoe_test@example.com',
            'username' => 'johndoetest',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'barangay_name' => 'Fort Bonifacio',
            'position' => 'kagawad',
        ];

        $response = $this->postJson('/api/v1/auth/register', $registrationPayload);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ONBOARDING_DISABLED');

        // Restore
        SystemSetting::set('allow_public_onboarding', true);
    }

    public function test_maintenance_mode_blocks_non_admin(): void
    {
        $chairperson = User::where('email', 'chairperson@skportal.gov.ph')->first();
        $admin = User::where('email', 'admin@skportal.gov.ph')->first();

        SystemSetting::set('maintenance_mode', true);

        // Regular user blocked from protected route
        $blockedResponse = $this->actingAs($chairperson)->getJson('/api/v1/profile');
        $blockedResponse->assertStatus(503)
            ->assertJsonPath('error_code', 'MAINTENANCE_MODE_ACTIVE');

        // Admin allowed through
        $adminResponse = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard-stats');
        $adminResponse->assertStatus(200);

        // Public settings still accessible during maintenance
        $settingsResponse = $this->getJson('/api/v1/system/settings');
        $settingsResponse->assertStatus(200);

        // Restore
        SystemSetting::set('maintenance_mode', false);
    }
}

