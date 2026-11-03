<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OfficialMeasure;
use App\Models\SkOfficialProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class OfficialMeasureController extends Controller
{
    /**
     * Get measures list for the current official (or specific profile for admins).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isAdmin = $user->hasRole('super_admin') || $user->username === 'superadmin';

        $profileId = $request->query('official_profile_id');
        if ($isAdmin && $profileId) {
            $profile = SkOfficialProfile::find($profileId);
        } else {
            $profile = $user->officialProfile;
        }

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Official profile not found.',
                'error_code' => 'PROFILE_NOT_FOUND',
            ], 404);
        }

        $measures = OfficialMeasure::where('official_profile_id', $profile->id)
            ->orderBy('date_approved', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Official measures retrieved.',
            'data' => [
                'measures' => $measures,
                'total_count' => $measures->count(),
                'passed_count' => $profile->passed_measures_count,
            ],
        ]);
    }

    /**
     * Create a new authored measure/resolution.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isAdmin = $user->hasRole('super_admin') || $user->username === 'superadmin';

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'resolution_number' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:100'],
            'authorship_role' => ['required', Rule::in(['principal_author', 'co_author', 'sponsor', 'co_sponsor'])],
            'status' => ['required', Rule::in(['passed', 'adopted', 'enacted', 'under_review', 'tabled'])],
            'date_approved' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'official_profile_id' => ['nullable', 'exists:sk_officials_profiles,id'],
        ]);

        if ($isAdmin && !empty($validated['official_profile_id'])) {
            $profile = SkOfficialProfile::findOrFail($validated['official_profile_id']);
        } else {
            $profile = $user->officialProfile;
        }

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No active official profile associated with this account.',
            ], 422);
        }

        $validated['official_profile_id'] = $profile->id;
        $measure = OfficialMeasure::create($validated);

        // Auto-recalculate passed measures count
        $newCount = $profile->syncPassedMeasuresCount();

        return response()->json([
            'success' => true,
            'message' => 'Legislative measure recorded successfully.',
            'data' => [
                'measure' => $measure,
                'passed_measures_count' => $newCount,
            ],
        ], 201);
    }

    /**
     * Update an authored measure/resolution.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isAdmin = $user->hasRole('super_admin') || $user->username === 'superadmin';

        $measure = OfficialMeasure::findOrFail($id);

        // Authorization check
        if (!$isAdmin && (!$user->officialProfile || $measure->official_profile_id !== $user->officialProfile->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this legislative record.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'resolution_number' => ['nullable', 'string', 'max:50'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'authorship_role' => ['sometimes', 'required', Rule::in(['principal_author', 'co_author', 'sponsor', 'co_sponsor'])],
            'status' => ['sometimes', 'required', Rule::in(['passed', 'adopted', 'enacted', 'under_review', 'tabled'])],
            'date_approved' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:1000'],
        ]);

        $measure->update($validated);

        $profile = $measure->officialProfile;
        if ($profile) {
            $profile->syncPassedMeasuresCount();
        }

        return response()->json([
            'success' => true,
            'message' => 'Legislative measure updated successfully.',
            'data' => [
                'measure' => $measure->fresh(),
                'passed_measures_count' => $profile ? $profile->passed_measures_count : 0,
            ],
        ]);
    }

    /**
     * Delete an authored measure.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $isAdmin = $user->hasRole('super_admin') || $user->username === 'superadmin';

        $measure = OfficialMeasure::findOrFail($id);

        if (!$isAdmin && (!$user->officialProfile || $measure->official_profile_id !== $user->officialProfile->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to remove this legislative record.',
            ], 403);
        }

        $profile = $measure->officialProfile;
        $measure->delete();

        if ($profile) {
            $profile->syncPassedMeasuresCount();
        }

        return response()->json([
            'success' => true,
            'message' => 'Legislative measure deleted.',
            'data' => [
                'passed_measures_count' => $profile ? $profile->fresh()->passed_measures_count : 0,
            ],
        ]);
    }
}

