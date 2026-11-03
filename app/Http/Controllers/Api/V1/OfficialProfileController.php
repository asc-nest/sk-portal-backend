<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SkOfficialProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OfficialProfileController extends Controller
{
    /**
     * Public roster of active SK Officials for youth constituent general information.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SkOfficialProfile::query()
            ->where('is_active_term', true)
            ->with(['user:id,email,username', 'measures']);

        // Filter by position
        if ($request->filled('position')) {
            $query->where('position', $request->query('position'));
        }

        // Filter by committee
        if ($request->filled('committee')) {
            $query->where('committee_assigned', 'like', '%' . $request->query('committee') . '%');
        }

        // Search by name
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('committee_assigned', 'like', "%{$search}%");
            });
        }

        // Order by executive hierarchy: chairperson first, then secretary, treasurer, kagawad
        $officials = $query->get()->sortBy(function ($official) {
            $order = [
                'chairperson' => 1,
                'secretary' => 2,
                'treasurer' => 3,
                'kagawad' => 4,
            ];
            return $order[$official->position] ?? 5;
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Official council roster retrieved.',
            'data' => $officials,
            'meta' => [
                'total_officials' => $officials->count(),
                'barangay' => 'Barangay Central',
            ],
        ]);
    }

    /**
     * Public profile view of a specific official (by numeric ID or official_id_number).
     */
    public function show(string $id): JsonResponse
    {
        $query = SkOfficialProfile::with(['user:id,email,username', 'measures']);
        
        $official = is_numeric($id)
            ? $query->find($id)
            : $query->where('official_id_number', $id)->first();

        if (!$official) {
            // Also try fallback lookup on user_id
            if (is_numeric($id)) {
                $official = SkOfficialProfile::with(['user:id,email,username', 'measures'])->where('user_id', $id)->first();
            }
        }

        if (!$official) {
            return response()->json([
                'success' => false,
                'message' => 'Official not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Official profile details.',
            'data' => $official,
        ]);
    }

    /**
     * Helper to get or auto-initialize an official's profile.
     */
    protected function getOrCreateProfile(User $user): SkOfficialProfile
    {
        if ($user->officialProfile) {
            return $user->officialProfile;
        }

        $nameParts = explode(' ', trim($user->name ?: 'System Official'));
        $firstName = $nameParts[0] ?? 'System';
        $lastName = count($nameParts) > 1 ? end($nameParts) : 'Official';

        $position = 'kagawad';
        if ($user->hasRole('super_admin')) {
            $position = 'chairperson';
        } elseif ($user->hasRole('sk_chairperson')) {
            $position = 'chairperson';
        } elseif ($user->hasRole('sk_secretary')) {
            $position = 'secretary';
        } elseif ($user->hasRole('sk_treasurer')) {
            $position = 'treasurer';
        }

        $profile = SkOfficialProfile::create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'position' => $position,
            'committee_assigned' => 'Executive & Council Affairs',
            'term_start' => '2023-11-01',
            'term_end' => '2026-11-01',
            'is_active_term' => true,
            'barangay_name' => 'Barangay Central',
            'official_id_number' => 'SK-' . date('Y') . '-' . str_pad((string)$user->id, 3, '0', STR_PAD_LEFT),
        ]);

        $user->setRelation('officialProfile', $profile);
        return $profile;
    }

    /**
     * Get the logged-in official's own full profile.
     */
    public function current(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['officialProfile', 'roles']);
        $profile = $this->getOrCreateProfile($user);
        $profile->load(['measures', 'sessionAttendances']);

        return response()->json([
            'success' => true,
            'message' => 'Current profile retrieved.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'username' => $user->username,
                    'roles' => $user->roles->pluck('name'),
                ],
                'profile' => $profile,
            ],
        ]);
    }

    /**
     * Update the authenticated official's own profile.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);

        $isAdmin = $user->hasRole('super_admin') || $user->username === 'superadmin';

        // Non-admins are prohibited from mutating legal demographic profile records
        if (!$isAdmin) {
            $request->request->remove('first_name');
            $request->request->remove('middle_name');
            $request->request->remove('last_name');
            $request->request->remove('suffix');
            $request->request->remove('gender');
            $request->request->remove('birthdate');
        }

        $validated = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['sometimes', Rule::in(['male', 'female', 'other'])],
            'birthdate' => ['nullable', 'date'],
            'contact_number' => ['nullable', 'string', 'max:25'],
            'purok_sitio' => ['nullable', 'string', 'max:100'],
            'bio_advocacy' => ['nullable', 'string', 'max:1000'],
            'passed_measures_count' => ['nullable', 'integer', 'min:0'],
            'session_attendance_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'skmt_compliant' => ['nullable', 'boolean'],
        ]);

        $profile->update($validated);

        // Update user display name as well
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $user->update([
                'name' => $profile->full_name,
                'mobile_number' => $validated['contact_number'] ?? $user->mobile_number,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $profile->fresh(),
        ]);
    }

    /**
     * Upload profile avatar image.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp,jpg', 'max:2048'],
        ]);

        $profile = $this->getOrCreateProfile($request->user());

        // Delete old avatar if exists
        if ($profile->avatar_path && Storage::disk('public')->exists($profile->avatar_path)) {
            Storage::disk('public')->delete($profile->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $profile->update(['avatar_path' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar updated successfully.',
            'data' => [
                'avatar_path' => $path,
                'avatar_url' => asset('storage/' . $path),
            ],
        ]);
    }
}
