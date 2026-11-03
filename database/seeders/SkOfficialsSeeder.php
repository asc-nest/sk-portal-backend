<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SkOfficialProfile;
use App\Models\OfficialInvitation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class SkOfficialsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Super Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@skportal.gov.ph'],
            [
                'name' => 'System Administrator',
                'username' => 'superadmin',
                'password' => Hash::make('AdminPass123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole('super_admin');

        // 2. SK Chairperson
        $chairpersonUser = User::firstOrCreate(
            ['email' => 'chairperson@skportal.gov.ph'],
            [
                'name' => 'Hon. John Patrick Santos',
                'username' => 'jpsantos',
                'mobile_number' => '09171234567',
                'password' => Hash::make('ChairPass123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $chairpersonUser->assignRole('sk_chairperson');

        SkOfficialProfile::firstOrCreate(
            ['user_id' => $chairpersonUser->id],
            [
                'first_name' => 'John Patrick',
                'middle_name' => 'Ramos',
                'last_name' => 'Santos',
                'suffix' => null,
                'nickname' => 'JP',
                'gender' => 'male',
                'birthdate' => '2000-05-15',
                'contact_number' => '09171234567',
                'position' => 'chairperson',
                'committee_assigned' => 'Executive & Overall Council Leadership',
                'term_start' => '2023-11-01',
                'term_end' => '2026-11-01',
                'is_active_term' => true,
                'bio_advocacy' => 'Championing youth digital empowerment, accessible sports facilities, and transparent leadership for all youth constituents in Barangay Central.',
                'official_id_number' => 'SK-2023-001',
                'barangay_name' => 'Barangay Central',
                'purok_sitio' => 'Purok 1 - Maharlika',
            ]
        );

        // 3. SK Secretary
        $secretaryUser = User::firstOrCreate(
            ['email' => 'secretary@skportal.gov.ph'],
            [
                'name' => 'Hon. Alyssa Marie Cruz',
                'username' => 'alyssacruz',
                'mobile_number' => '09181234568',
                'password' => Hash::make('SecretaryPass123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $secretaryUser->assignRole('sk_secretary');

        SkOfficialProfile::firstOrCreate(
            ['user_id' => $secretaryUser->id],
            [
                'first_name' => 'Alyssa Marie',
                'middle_name' => 'Delos Reyes',
                'last_name' => 'Cruz',
                'suffix' => null,
                'nickname' => 'Aly',
                'gender' => 'female',
                'birthdate' => '2001-08-22',
                'contact_number' => '09181234568',
                'position' => 'secretary',
                'committee_assigned' => 'Council Secretariat & Youth Registry Records',
                'term_start' => '2023-11-01',
                'term_end' => '2026-11-01',
                'is_active_term' => true,
                'bio_advocacy' => 'Ensuring meticulous documentation, open access to youth council resolutions, and fair registration for every barangay youth.',
                'official_id_number' => 'SK-2023-002',
                'barangay_name' => 'Barangay Central',
                'purok_sitio' => 'Purok 3 - Sampaguita',
            ]
        );

        // 4. SK Treasurer
        $treasurerUser = User::firstOrCreate(
            ['email' => 'treasurer@skportal.gov.ph'],
            [
                'name' => 'Hon. Rafael Mateo Tan',
                'username' => 'rafatan',
                'mobile_number' => '09191234569',
                'password' => Hash::make('TreasurerPass123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $treasurerUser->assignRole('sk_treasurer');

        SkOfficialProfile::firstOrCreate(
            ['user_id' => $treasurerUser->id],
            [
                'first_name' => 'Rafael Mateo',
                'middle_name' => 'Gomez',
                'last_name' => 'Tan',
                'suffix' => null,
                'nickname' => 'Rafa',
                'gender' => 'male',
                'birthdate' => '1999-12-04',
                'contact_number' => '09191234569',
                'position' => 'treasurer',
                'committee_assigned' => 'Ways and Means & Financial Transparency',
                'term_start' => '2023-11-01',
                'term_end' => '2026-11-01',
                'is_active_term' => true,
                'bio_advocacy' => 'Upholding strict fiscal accountability and ensuring 100% of youth funds directly impact grassroots community projects.',
                'official_id_number' => 'SK-2023-003',
                'barangay_name' => 'Barangay Central',
                'purok_sitio' => 'Purok 2 - Bagong Pag-asa',
            ]
        );

        // 5. SK Kagawad (Committee on Sports)
        $kagawadSports = User::firstOrCreate(
            ['email' => 'kagawad.sports@skportal.gov.ph'],
            [
                'name' => 'Hon. Marcus Daniel Reyes',
                'username' => 'marcusreyes',
                'mobile_number' => '09201234570',
                'password' => Hash::make('KagawadPass123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $kagawadSports->assignRole('sk_kagawad');

        SkOfficialProfile::firstOrCreate(
            ['user_id' => $kagawadSports->id],
            [
                'first_name' => 'Marcus Daniel',
                'middle_name' => 'Villanueva',
                'last_name' => 'Reyes',
                'suffix' => 'Jr.',
                'nickname' => 'Marc',
                'gender' => 'male',
                'birthdate' => '2002-03-10',
                'contact_number' => '09201234570',
                'position' => 'kagawad',
                'committee_assigned' => 'Committee on Youth & Sports Development',
                'term_start' => '2023-11-01',
                'term_end' => '2026-11-01',
                'is_active_term' => true,
                'bio_advocacy' => 'Promoting healthy active lifestyles, anti-drug awareness, and inter-purok athletic tournaments for grassroots talent discovery.',
                'official_id_number' => 'SK-2023-004',
                'barangay_name' => 'Barangay Central',
                'purok_sitio' => 'Purok 4 - Pagkakaisa',
            ]
        );

        // 6. SK Kagawad (Committee on Education & Culture)
        $kagawadEducation = User::firstOrCreate(
            ['email' => 'kagawad.education@skportal.gov.ph'],
            [
                'name' => 'Hon. Bea Katherine Mendoza',
                'username' => 'beamendoza',
                'mobile_number' => '09211234571',
                'password' => Hash::make('KagawadPass123!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $kagawadEducation->assignRole('sk_kagawad');

        SkOfficialProfile::firstOrCreate(
            ['user_id' => $kagawadEducation->id],
            [
                'first_name' => 'Bea Katherine',
                'middle_name' => 'Fernandez',
                'last_name' => 'Mendoza',
                'suffix' => null,
                'nickname' => 'Bea',
                'gender' => 'female',
                'birthdate' => '2003-09-18',
                'contact_number' => '09211234571',
                'position' => 'kagawad',
                'committee_assigned' => 'Committee on Education, Arts & Culture',
                'term_start' => '2023-11-01',
                'term_end' => '2026-11-01',
                'is_active_term' => true,
                'bio_advocacy' => 'Expanding free college entrance review programs, community study hubs, and scholarships for deserving in-school youth.',
                'official_id_number' => 'SK-2023-005',
                'barangay_name' => 'Barangay Central',
                'purok_sitio' => 'Purok 5 - Silangan',
            ]
        );

        // 7. Active Sample Invitation for Testing Invite Flow
        OfficialInvitation::firstOrCreate(
            ['token' => 'sample-invite-token-kagawad-health-2026'],
            [
                'email' => 'new.kagawad@skportal.gov.ph',
                'first_name' => 'Gabriel',
                'last_name' => 'Aquino',
                'position' => 'kagawad',
                'committee_assigned' => 'Committee on Health & Environmental Protection',
                'expires_at' => Carbon::now()->addDays(7),
                'accepted_at' => null,
                'created_by' => $chairpersonUser->id,
            ]
        );
    }
}
