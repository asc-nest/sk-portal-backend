<?php

namespace Database\Seeders;

use App\Models\CouncilSession;
use App\Models\OfficialMeasure;
use App\Models\SessionAttendance;
use App\Models\SkOfficialProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class GovernanceRecordsSeeder extends Seeder
{
    public function run(): void
    {
        $officials = SkOfficialProfile::all();
        if ($officials->isEmpty()) {
            return;
        }

        $adminUser = User::where('username', 'superadmin')->first();
        $secretaryUser = User::where('username', 'alyssacruz')->first() ?? $adminUser;

        // 1. Seed Sample Measures for Officials
        $sampleMeasures = [
            [
                'resolution_number' => 'RES-2024-001',
                'title' => 'Resolution Authorizing the Annual Barangay Youth Investment Program (ABYIP) Allocation for FY 2024',
                'category' => 'Youth Empowerment',
                'authorship_role' => 'principal_author',
                'status' => 'passed',
                'date_approved' => '2024-01-15',
                'summary' => 'Formally approving the prioritized development programs and youth activities under the 10% SK fund.',
            ],
            [
                'resolution_number' => 'RES-2024-004',
                'title' => 'Taguig Youth Disaster Resilience and First Responder Training Workshop',
                'category' => 'Disaster Risk Reduction',
                'authorship_role' => 'principal_author',
                'status' => 'passed',
                'date_approved' => '2024-03-10',
                'summary' => 'Mandating quarterly emergency response drills and basic life support certification for youth leaders.',
            ],
            [
                'resolution_number' => 'RES-2024-008',
                'title' => 'Resolution Establishing the Barangay Digital Youth Hub and Co-Working Space',
                'category' => 'Education & Culture',
                'authorship_role' => 'co_author',
                'status' => 'passed',
                'date_approved' => '2024-05-20',
                'summary' => 'Providing high-speed internet, research devices, and study workstations for students in Barangay Central.',
            ],
            [
                'resolution_number' => 'RES-2024-012',
                'title' => 'Barangay Inter-Purok Youth Sports and Wellness League 2024',
                'category' => 'Sports & Wellness',
                'authorship_role' => 'sponsor',
                'status' => 'passed',
                'date_approved' => '2024-07-08',
                'summary' => 'Promoting drug abuse resistance through organized basketball, volleyball, and mental wellness clinics.',
            ],
            [
                'resolution_number' => 'RES-2024-015',
                'title' => 'Youth Anti-Drug and Substance Abuse Campaign & Seminar Series',
                'category' => 'Health & Anti-Drug Abuse',
                'authorship_role' => 'principal_author',
                'status' => 'passed',
                'date_approved' => '2024-08-19',
                'summary' => 'Institutionalizing youth-led community counseling and rehabilitation support in partnership with BADAC.',
            ],
            [
                'resolution_number' => 'RES-2024-019',
                'title' => 'Katipunan ng Kabataan Green Initiative: Urban Tree Planting and Recycling Hub',
                'category' => 'Environment & Climate Action',
                'authorship_role' => 'co_author',
                'status' => 'under_review',
                'date_approved' => null,
                'summary' => 'Community zero-waste drives and pocket mini-forest maintenance across purok corridors.',
            ],
        ];

        // Distribute measures to officials
        foreach ($officials as $index => $official) {
            // Assign 2 to 4 measures per official
            $assigned = array_slice($sampleMeasures, $index % 3, 3);
            foreach ($assigned as $m) {
                OfficialMeasure::firstOrCreate(
                    [
                        'official_profile_id' => $official->id,
                        'resolution_number' => $m['resolution_number'] . '-' . $official->id,
                    ],
                    array_merge($m, ['official_profile_id' => $official->id])
                );
            }
            $official->syncPassedMeasuresCount();
        }

        // 2. Seed Council Sessions
        $sessionsData = [
            [
                'barangay_name' => 'Barangay Central',
                'session_number' => 'RS-2024-01',
                'session_title' => '1st Regular Sanggunian Session - ABYIP Strategic Realignment',
                'session_type' => 'regular',
                'session_date' => '2024-02-14',
                'venue' => 'Barangay Central Session Hall',
                'presided_by' => 'Hon. John Patrick Santos',
                'created_by' => $secretaryUser ? $secretaryUser->id : null,
                'notes' => 'Quorum declared with 8 members present. ABYIP line-item allocations reviewed.',
            ],
            [
                'barangay_name' => 'Barangay Central',
                'session_number' => 'RS-2024-02',
                'session_title' => '2nd Regular Sanggunian Session - Mid-Year Youth Sports & DRRM Program',
                'session_type' => 'regular',
                'session_date' => '2024-05-18',
                'venue' => 'Barangay Central Session Hall',
                'presided_by' => 'Hon. John Patrick Santos',
                'created_by' => $secretaryUser ? $secretaryUser->id : null,
                'notes' => 'All committee chairpersons presented progress updates. Resolutions 004 and 008 passed unanimously.',
            ],
            [
                'barangay_name' => 'Barangay Central',
                'session_number' => 'SS-2024-01',
                'session_title' => 'Special Session - SKMT Mandatory Training and DILG Compliance Audit',
                'session_type' => 'special',
                'session_date' => '2024-08-25',
                'venue' => 'Taguig Civic Center / Barangay Hall',
                'presided_by' => 'Hon. John Patrick Santos',
                'created_by' => $secretaryUser ? $secretaryUser->id : null,
                'notes' => 'Urgent review of DILG submission guidelines. 100% compliance verified.',
            ],
        ];

        foreach ($sessionsData as $sess) {
            $session = CouncilSession::firstOrCreate(
                ['session_number' => $sess['session_number'], 'barangay_name' => $sess['barangay_name']],
                $sess
            );

            // Create roll call attendance for all officials
            foreach ($officials as $idx => $official) {
                $status = 'present';
                $remarks = null;

                // Create slight authentic variation (e.g. 1 official excused once)
                if ($sess['session_number'] === 'RS-2024-02' && $idx === 3) {
                    $status = 'excused';
                    $remarks = 'Official business - LGU Youth Leaders Convergence';
                }

                SessionAttendance::firstOrCreate(
                    [
                        'council_session_id' => $session->id,
                        'official_profile_id' => $official->id,
                    ],
                    [
                        'status' => $status,
                        'remarks' => $remarks,
                    ]
                );
            }
        }

        // Recalculate attendance rates for all officials
        foreach ($officials as $official) {
            $official->syncSessionAttendanceRate();
        }
    }
}

