<?php

namespace Tests\Feature;

use Tests\TestCase;

class SkOfficialsApiTest extends TestCase
{
    /**
     * Test public roster of officials returns active officials.
     */
    public function test_public_roster_returns_officials(): void
    {
        $response = $this->getJson('/api/v1/officials');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'position',
                        'committee_assigned',
                    ],
                ],
            ]);
    }

    /**
     * Test login failure with bad credentials.
     */
    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'nonexistent@skportal.gov.ph',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
    }

    /**
     * Test login success with seeded Chairperson account.
     */
    public function test_chairperson_can_login_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'chairperson@skportal.gov.ph',
            'password' => 'ChairPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'email',
                        'roles',
                        'profile',
                    ],
                ],
            ]);
    }

    /**
     * Test verifying seeded invitation token.
     */
    public function test_can_verify_valid_invitation_token(): void
    {
        $response = $this->getJson('/api/v1/invitations/verify/sample-invite-token-kagawad-health-2026');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.position', 'kagawad')
            ->assertJsonPath('data.email', 'new.kagawad@skportal.gov.ph');
    }
}
