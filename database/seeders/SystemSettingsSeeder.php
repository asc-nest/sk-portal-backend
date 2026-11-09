<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'portal_name',
                'value' => 'Taguig City SK Officials Portal',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Portal Name',
                'description' => 'Official name of this civic administration platform.',
            ],
            [
                'key' => 'jurisdiction_name',
                'value' => 'City of Taguig - Sangguniang Kabataan Federation',
                'type' => 'string',
                'group' => 'general',
                'label' => 'Jurisdiction Authority',
                'description' => 'Institutional body overseeing all 38 barangays.',
            ],
            [
                'key' => 'current_term_label',
                'value' => 'Term 2023 – 2026',
                'type' => 'string',
                'group' => 'governance',
                'label' => 'Current Council Term',
                'description' => 'Active constitutional term of elected SK officials.',
            ],
            [
                'key' => 'term_start_date',
                'value' => '2023-11-01',
                'type' => 'string',
                'group' => 'governance',
                'label' => 'Term Start Date',
                'description' => 'Official start date of the current term.',
            ],
            [
                'key' => 'term_end_date',
                'value' => '2026-11-01',
                'type' => 'string',
                'group' => 'governance',
                'label' => 'Term End Date',
                'description' => 'Official end date of the current term.',
            ],
            [
                'key' => 'allow_public_onboarding',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'registration',
                'label' => 'Allow Online Official Onboarding',
                'description' => 'Enable or disable the /onboarding registration route for new SK officials.',
            ],
            [
                'key' => 'skmt_enforcement',
                'value' => '1',
                'type' => 'boolean',
                'group' => 'governance',
                'label' => 'DILG SKMT Compliance Enforcement',
                'description' => 'Enforce mandatory completion of Sangguniang Kabataan Mandatory Training (RA 10742).',
            ],
            [
                'key' => 'session_timeout_minutes',
                'value' => '480',
                'type' => 'integer',
                'group' => 'security',
                'label' => 'Session Timeout (Minutes)',
                'description' => 'Duration before inactive official sessions expire (default: 480 mins / 8 hours).',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'group' => 'security',
                'label' => 'System Maintenance Mode',
                'description' => 'Temporarily restrict portal access to Super Administrators only.',
            ],
        ];

        foreach ($settings as $item) {
            SystemSetting::updateOrCreate(['key' => $item['key']], $item);
        }
    }
}

