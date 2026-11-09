<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SkOfficialProfile;
use App\Models\ActivityLog;
use App\Models\CouncilSession;
use App\Models\OfficialMeasure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Retrieve centralized Super Admin dashboard metrics and recent activities.
     */
    public function stats(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $sevenDaysAgo = $now->copy()->subDays(7);

        // User stats
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $suspendedUsers = User::where('is_active', false)->count();
        $newRegistrations30d = User::where('created_at', '>=', $thirtyDaysAgo)->count();
        $newRegistrations7d = User::where('created_at', '>=', $sevenDaysAgo)->count();

        // Official Profiles
        $totalOfficials = SkOfficialProfile::where('is_active_term', true)
            ->whereDoesntHave('user.roles', function ($q) {
                $q->where('name', 'super_admin');
            })->count();

        $skmtCertified = SkOfficialProfile::where('is_active_term', true)
            ->where('skmt_compliant', true)
            ->whereDoesntHave('user.roles', function ($q) {
                $q->where('name', 'super_admin');
            })->count();

        $skmtPercentage = $totalOfficials > 0 ? round(($skmtCertified / $totalOfficials) * 100, 1) : 100;

        // Barangay coverage
        $uniqueBarangays = SkOfficialProfile::where('is_active_term', true)
            ->whereDoesntHave('user.roles', function ($q) {
                $q->where('name', 'super_admin');
            })
            ->pluck('barangay_name')
            ->filter()
            ->unique()
            ->values();

        $barangaysCount = $uniqueBarangays->count();

        // Role Breakdown
        $roleBreakdown = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', User::class)
            ->select('roles.name as role_name', DB::raw('count(*) as count'))
            ->groupBy('roles.name')
            ->pluck('count', 'role_name');

        // Recent Activity Logs (latest 10)
        $recentActivities = ActivityLog::with('user:id,name,email,username')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Registration timeline (last 7 days by day)
        $registrationTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $count = User::whereDate('created_at', $date)->count();
            $registrationTrends[] = [
                'date' => $date,
                'label' => Carbon::now()->subDays($i)->format('M d'),
                'count' => $count,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'suspended_users' => $suspendedUsers,
                'new_registrations_30d' => $newRegistrations30d,
                'new_registrations_7d' => $newRegistrations7d,
                'total_officials' => $totalOfficials,
                'skmt_certified_count' => $skmtCertified,
                'skmt_percentage' => $skmtPercentage,
                'barangays_covered' => $barangaysCount,
                'total_jurisdiction_barangays' => 38,
                'role_breakdown' => $roleBreakdown,
                'recent_activities' => $recentActivities,
                'registration_trends' => $registrationTrends,
            ],
        ]);
    }
}

