<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CouncilSession;
use App\Models\OfficialMeasure;
use App\Models\SkOfficialProfile;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * Provide real-time data-driven metrics for the SK Officials Portal.
     */
    public function summary(): JsonResponse
    {
        $totalJurisdictionBarangays = 38;

        $activeProfiles = SkOfficialProfile::where('is_active_term', true)
            ->whereDoesntHave('user.roles', function ($q) {
                $q->where('name', 'super_admin');
            })
            ->get();
        $totalOfficials = $activeProfiles->count();

        // Unique onboarded barangays
        $onboardedBarangays = $activeProfiles->pluck('barangay_name')->filter()->unique()->values()->all();
        $onboardedCount = count($onboardedBarangays);

        // Skills distribution summary
        $skillsTally = [];
        foreach ($activeProfiles as $profile) {
            $skills = $profile->skills;
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

        // Educational attainment summary
        $educationTally = [];
        foreach ($activeProfiles as $profile) {
            $level = $profile->educational_attainment ?: 'Not Specified';
            $educationTally[$level] = ($educationTally[$level] ?? 0) + 1;
        }

        // SKMT Certified
        $skmtCertifiedCount = $activeProfiles->where('skmt_compliant', true)->count();
        $skmtPercentage = $totalOfficials > 0 
            ? round(($skmtCertifiedCount / $totalOfficials) * 100, 1) 
            : 0;

        // Role counts
        $chairpersonsCount = $activeProfiles->where('position', 'chairperson')->count();
        $kagawadsCount = $activeProfiles->where('position', 'kagawad')->count();
        $secretariesCount = $activeProfiles->where('position', 'secretary')->count();
        $treasurersCount = $activeProfiles->where('position', 'treasurer')->count();

        // Passed measures count
        $passedMeasuresCount = OfficialMeasure::whereIn('status', ['passed', 'adopted', 'enacted'])->count();

        // Council sessions
        $totalSessions = CouncilSession::count();

        // Average attendance
        $avgAttendance = $totalOfficials > 0 
            ? round($activeProfiles->avg('session_attendance_rate') ?? 0, 1) 
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_jurisdiction_barangays' => $totalJurisdictionBarangays,
                'onboarded_councils_count' => $onboardedCount,
                'total_registered_officials' => $totalOfficials,
                'chairpersons_count' => $chairpersonsCount,
                'kagawads_count' => $kagawadsCount,
                'secretaries_count' => $secretariesCount,
                'treasurers_count' => $treasurersCount,
                'skmt_certified_count' => $skmtCertifiedCount,
                'skmt_certified_percentage' => $skmtPercentage,
                'total_measures_passed' => $passedMeasuresCount,
                'total_council_sessions' => $totalSessions,
                'avg_session_attendance_rate' => $avgAttendance,
                'onboarded_barangays' => $onboardedBarangays,
                'skills_distribution' => $skillsTally,
                'education_distribution' => $educationTally,
            ],
        ]);
    }
}

