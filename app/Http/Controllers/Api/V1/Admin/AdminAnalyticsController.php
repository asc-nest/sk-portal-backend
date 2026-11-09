<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SkOfficialProfile;
use App\Models\CouncilSession;
use App\Models\OfficialMeasure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    /**
     * Get comprehensive analytics, distributions, and demographic reports.
     */
    public function index(): JsonResponse
    {
        $officials = SkOfficialProfile::where('is_active_term', true)
            ->whereDoesntHave('user.roles', function ($q) {
                $q->where('name', 'super_admin');
            })
            ->get();

        $totalOfficials = $officials->count();

        // 1. Roles Distribution
        $rolesDistribution = [
            'chairperson' => $officials->where('position', 'chairperson')->count(),
            'secretary' => $officials->where('position', 'secretary')->count(),
            'treasurer' => $officials->where('position', 'treasurer')->count(),
            'kagawad' => $officials->where('position', 'kagawad')->count(),
        ];

        // 2. Skills Distribution (Top 20)
        $skillsTally = [];
        foreach ($officials as $official) {
            $skills = $official->skills;
            if (is_array($skills)) {
                foreach ($skills as $skill) {
                    $trimmed = trim($skill);
                    if (!empty($trimmed)) {
                        $skillsTally[$trimmed] = ($skillsTally[$trimmed] ?? 0) + 1;
                    }
                }
            }
        }
        arsort($skillsTally);
        $topSkills = array_slice($skillsTally, 0, 20, true);

        // 3. Educational Attainment Distribution
        $educationTally = [];
        foreach ($officials as $official) {
            $level = $official->educational_attainment ?: 'Not Specified';
            $educationTally[$level] = ($educationTally[$level] ?? 0) + 1;
        }
        arsort($educationTally);

        // 4. Barangay Coverage and Roster Counts
        $barangayCoverage = [];
        $groupedByBarangay = $officials->groupBy('barangay_name');
        foreach ($groupedByBarangay as $brgyName => $council) {
            $chairperson = $council->firstWhere('position', 'chairperson');
            $barangayCoverage[] = [
                'barangay' => $brgyName,
                'total_officials' => $council->count(),
                'has_chairperson' => !is_null($chairperson),
                'chairperson_name' => $chairperson ? $chairperson->full_name : 'Vacant',
                'skmt_certified_count' => $council->where('skmt_compliant', true)->count(),
            ];
        }
        usort($barangayCoverage, fn($a, $b) => strcmp($a['barangay'], $b['barangay']));

        // 5. Monthly Registration Trend (last 6 months)
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            $count = User::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            $monthlyTrends[] = [
                'month' => $monthStart->format('M Y'),
                'count' => $count,
            ];
        }

        // 6. Legislative & Session Governance
        $totalMeasures = OfficialMeasure::count();
        $passedMeasures = OfficialMeasure::whereIn('status', ['passed', 'adopted', 'enacted'])->count();
        $totalSessions = CouncilSession::count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_officials' => $totalOfficials,
                'roles_distribution' => $rolesDistribution,
                'skills_distribution' => $topSkills,
                'education_distribution' => $educationTally,
                'barangay_coverage' => $barangayCoverage,
                'monthly_trends' => $monthlyTrends,
                'governance' => [
                    'total_measures' => $totalMeasures,
                    'passed_measures' => $passedMeasures,
                    'total_sessions' => $totalSessions,
                ],
            ],
        ]);
    }
}

