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
     * Run the database seeds for all 38 Barangays of Taguig City.
     */
    public function run(): void
    {
        // 1. Super Administrator (System Admin only - Excluded from SK Officials list)
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
        $admin->syncRoles(['super_admin']);

        // 38 Official Barangays of Taguig City (1st & 2nd Districts + 10 Embo Barangays)
        $barangays = [
            'Bagumbayan',
            'Bambang',
            'Calzada-Tipas',
            'Cembo',
            'Central Bicutan',
            'Central Signal Village',
            'Comembo',
            'East Rembo',
            'Fort Bonifacio',
            'Hagonoy',
            'Ibayo-Tipas',
            'Katuparan',
            'Ligid-Tipas',
            'Lower Bicutan',
            'Maharlika Village',
            'Napindan',
            'New Lower Bicutan',
            'North Daang Hari',
            'North Signal Village',
            'Palingon-Tipas',
            'Pembo',
            'Pinagsama',
            'Pitogo',
            'Post Proper Northside',
            'Post Proper Southside',
            'Rizal',
            'San Miguel',
            'Santa Ana',
            'South Cembo',
            'South Daang Hari',
            'South Signal Village',
            'Tanyag',
            'Tuktukan',
            'Upper Bicutan',
            'Ususan',
            'Wawa',
            'West Rembo',
            'Western Bicutan',
        ];

        $skillsBank = [
            'Project Management', 'Public Speaking', 'Graphic Design', 'Financial Management',
            'Youth Leadership', 'First Aid & DRRM', 'Event Organizing', 'Digital Marketing',
            'Social Media Management', 'Web Development', 'Community Organizing', 'Sports Coaching',
            'Videography', 'Creative Writing', 'Data Analysis', 'Conflict Resolution'
        ];

        $schools = [
            'Taguig City University',
            'Polytechnic University of the Philippines',
            'University of the Philippines Diliman',
            'University of Santo Tomas',
            'De La Salle University',
            'Technological University of the Philippines',
            'Emilio Aguinaldo College',
            'Taguig National High School',
        ];

        $degrees = [
            'BS Information Technology',
            'BS Business Administration',
            'AB Political Science',
            'BS Civil Engineering',
            'BS Nursing',
            'BS Accountancy',
            'BS Criminology',
            'BS Hospitality Management',
            'Senior High - STEM',
            'Senior High - HUMSS',
            'Senior High - ABM',
        ];

        $firstNamesMale = ['John Patrick', 'Marcus Daniel', 'Rafael Mateo', 'Christian', 'Joshua', 'Kevin', 'Adrian', 'Angelo', 'Gabriel', 'Justin', 'Mark Anthony', 'Carl Vincent', 'Paolo'];
        $firstNamesFemale = ['Alyssa Marie', 'Bea Katherine', 'Angelica', 'Camille', 'Nicole Joy', 'Princess Sarah', 'Rica Mae', 'Patricia', 'Andrea', 'Marielle', 'Jasmine', 'Kristine'];
        $lastNames = ['Santos', 'Cruz', 'Tan', 'Reyes', 'Mendoza', 'Delos Reyes', 'Villanueva', 'Fernandez', 'Bautista', 'Aquino', 'Garcia', 'Torres', 'Ramos', 'Flores', 'Castillo', 'Navarro', 'Morales'];

        $defaultPassword = Hash::make('CouncilPass123!');
        $chairpersonUser = null;

        foreach ($barangays as $bIndex => $brgy) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $brgy));

            // Roles per council: 1 Chairperson, 1 Secretary, 1 Treasurer, 2-3 Kagawads
            $positions = [
                ['pos' => 'chairperson', 'role' => 'sk_chairperson', 'comm' => 'Executive & Overall Council Leadership'],
                ['pos' => 'secretary', 'role' => 'sk_secretary', 'comm' => 'Council Secretariat & Youth Registry Records'],
                ['pos' => 'treasurer', 'role' => 'sk_treasurer', 'comm' => 'Ways and Means & Financial Transparency'],
                ['pos' => 'kagawad', 'role' => 'sk_kagawad', 'comm' => 'Committee on Youth & Sports Development'],
                ['pos' => 'kagawad', 'role' => 'sk_kagawad', 'comm' => 'Committee on Education, Arts & Culture'],
                ['pos' => 'kagawad', 'role' => 'sk_kagawad', 'comm' => 'Committee on Health & Environmental Protection'],
            ];

            foreach ($positions as $pIndex => $posInfo) {
                // If Fort Bonifacio, keep standard test accounts intact
                $isFortBonifacio = ($brgy === 'Fort Bonifacio');
                $isDefaultChair = $isFortBonifacio && $posInfo['pos'] === 'chairperson';
                $isDefaultSec = $isFortBonifacio && $posInfo['pos'] === 'secretary';
                $isDefaultTreas = $isFortBonifacio && $posInfo['pos'] === 'treasurer';
                $isDefaultKagawadSports = $isFortBonifacio && $posInfo['pos'] === 'kagawad' && $pIndex === 3;
                $isDefaultKagawadEdu = $isFortBonifacio && $posInfo['pos'] === 'kagawad' && $pIndex === 4;

                if ($isDefaultChair) {
                    $email = 'chairperson@skportal.gov.ph';
                    $username = 'jpsantos';
                    $name = 'Hon. John Patrick Santos';
                    $fn = 'John Patrick'; $mn = 'Ramos'; $ln = 'Santos'; $suf = null; $nick = 'JP'; $gender = 'male';
                    $pwd = Hash::make('ChairPass123!');
                } elseif ($isDefaultSec) {
                    $email = 'secretary@skportal.gov.ph';
                    $username = 'alyssacruz';
                    $name = 'Hon. Alyssa Marie Cruz';
                    $fn = 'Alyssa Marie'; $mn = 'Delos Reyes'; $ln = 'Cruz'; $suf = null; $nick = 'Aly'; $gender = 'female';
                    $pwd = Hash::make('SecretaryPass123!');
                } elseif ($isDefaultTreas) {
                    $email = 'treasurer@skportal.gov.ph';
                    $username = 'rafatan';
                    $name = 'Hon. Rafael Mateo Tan';
                    $fn = 'Rafael Mateo'; $mn = 'Gomez'; $ln = 'Tan'; $suf = null; $nick = 'Rafa'; $gender = 'male';
                    $pwd = Hash::make('TreasurerPass123!');
                } elseif ($isDefaultKagawadSports) {
                    $email = 'kagawad.sports@skportal.gov.ph';
                    $username = 'marcusreyes';
                    $name = 'Hon. Marcus Daniel Reyes';
                    $fn = 'Marcus Daniel'; $mn = 'Villanueva'; $ln = 'Reyes'; $suf = 'Jr.'; $nick = 'Marc'; $gender = 'male';
                    $pwd = Hash::make('KagawadPass123!');
                } elseif ($isDefaultKagawadEdu) {
                    $email = 'kagawad.education@skportal.gov.ph';
                    $username = 'beamendoza';
                    $name = 'Hon. Bea Katherine Mendoza';
                    $fn = 'Bea Katherine'; $mn = 'Fernandez'; $ln = 'Mendoza'; $suf = null; $nick = 'Bea'; $gender = 'female';
                    $pwd = Hash::make('KagawadPass123!');
                } else {
                    $isMale = ($pIndex % 2 === 0);
                    $gender = $isMale ? 'male' : 'female';
                    $fn = $isMale ? $firstNamesMale[($bIndex + $pIndex) % count($firstNamesMale)] : $firstNamesFemale[($bIndex + $pIndex) % count($firstNamesFemale)];
                    $mn = $lastNames[($bIndex * 2 + $pIndex) % count($lastNames)];
                    $ln = $lastNames[($bIndex * 3 + $pIndex + 1) % count($lastNames)];
                    $suf = ($isMale && $pIndex === 5) ? 'III' : null;
                    $nick = explode(' ', $fn)[0];
                    $username = strtolower(substr($nick, 0, 4) . $slug . ($pIndex + 1));
                    $email = "{$posInfo['pos']}.{$slug}{$pIndex}@skportal.gov.ph";
                    $name = "Hon. {$fn} {$ln}" . ($suf ? " {$suf}" : "");
                    $pwd = $defaultPassword;
                }

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'username' => $username,
                        'mobile_number' => '09' . str_pad((string)(170000000 + ($bIndex * 10) + $pIndex), 9, '0', STR_PAD_LEFT),
                        'password' => $pwd,
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]
                );
                $user->syncRoles([$posInfo['role']]);

                if ($isDefaultChair) {
                    $chairpersonUser = $user;
                }

                // Assigned skills (3-5 per official)
                $selectedSkills = [];
                $skillOffset = ($bIndex * 3 + $pIndex) % count($skillsBank);
                for ($s = 0; $s < 4; $s++) {
                    $selectedSkills[] = $skillsBank[($skillOffset + $s) % count($skillsBank)];
                }

                // Educational attainment
                $eduLevel = ($pIndex === 0 || $pIndex === 2) ? "Bachelor's Degree" : (($pIndex === 1) ? "College Graduate" : "College Undergraduate");
                $eduDegree = $degrees[($bIndex + $pIndex) % count($degrees)];
                $eduSchool = $schools[($bIndex * 2 + $pIndex) % count($schools)];

                $idNumber = sprintf('SK-%02d-%03d', ($bIndex + 1), $user->id);

                SkOfficialProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $fn,
                        'middle_name' => $mn,
                        'last_name' => $ln,
                        'suffix' => $suf,
                        'nickname' => $nick,
                        'gender' => $gender,
                        'birthdate' => Carbon::createFromDate(2001, 1, 1)->addMonths($bIndex + $pIndex)->format('Y-m-d'),
                        'contact_number' => $user->mobile_number,
                        'position' => $posInfo['pos'],
                        'committee_assigned' => $posInfo['comm'],
                        'term_start' => '2023-11-01',
                        'term_end' => '2026-11-01',
                        'is_active_term' => true,
                        'bio_advocacy' => "Advancing youth empowerment, transparent public service, and inclusive programs for all youth constituents of Barangay {$brgy}.",
                        'official_id_number' => $idNumber,
                        'barangay_name' => $brgy,
                        'purok_sitio' => "Purok " . (($pIndex % 4) + 1),
                        'passed_measures_count' => rand(4, 18),
                        'session_attendance_rate' => round(90 + (rand(0, 100) / 10), 1),
                        'skmt_compliant' => true,
                        'educational_attainment' => $eduLevel,
                        'education_degree' => $eduDegree,
                        'education_school' => $eduSchool,
                        'skills' => array_values(array_unique($selectedSkills)),
                    ]
                );
            }
        }

        // Active Sample Invitation for Testing Invite Flow
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
                'created_by' => $chairpersonUser ? $chairpersonUser->id : $admin->id,
            ]
        );
    }
}
