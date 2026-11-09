<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SkOfficialProfile;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * List all users with search, filtering, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles', 'officialProfile']);

        // Search by keyword
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%")
                  ->orWhereHas('officialProfile', function ($pq) use ($search) {
                      $pq->where('official_id_number', 'like', "%{$search}%")
                         ->orWhere('barangay_name', 'like', "%{$search}%")
                         ->orWhere('committee_assigned', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by role
        if ($request->filled('role') && $request->query('role') !== 'all') {
            $role = $request->query('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Filter by status (active/suspended)
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = $request->query('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'suspended' || $status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by barangay
        if ($request->filled('barangay') && $request->query('barangay') !== 'all') {
            $barangay = $request->query('barangay');
            $query->whereHas('officialProfile', function ($q) use ($barangay) {
                $q->where('barangay_name', $barangay);
            });
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $allowedSorts = ['id', 'name', 'email', 'username', 'created_at', 'is_active'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');

        $perPage = min((int) $request->query('per_page', 15), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Create a new user account with role and official profile.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'mobile_number' => ['nullable', 'string', 'max:25'],
            'role' => ['required', Rule::in(['super_admin', 'sk_chairperson', 'sk_secretary', 'sk_treasurer', 'sk_kagawad'])],
            'barangay_name' => ['required', 'string', 'max:100'],
            'position' => ['required', Rule::in(['chairperson', 'secretary', 'treasurer', 'kagawad', 'admin'])],
            'committee_assigned' => ['nullable', 'string', 'max:150'],
            'educational_attainment' => ['nullable', 'string', 'max:100'],
            'education_degree' => ['nullable', 'string', 'max:150'],
            'education_school' => ['nullable', 'string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'bio_advocacy' => ['nullable', 'string', 'max:1000'],
            'term_start' => ['nullable', 'date'],
            'term_end' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'mobile_number' => $validated['mobile_number'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole($validated['role']);

            $isAdmin = ($validated['role'] === 'super_admin');
            $officialIdNumber = ($isAdmin ? 'ADMIN-' : 'SK-' . date('Y') . '-') . str_pad((string) $user->id, 3, '0', STR_PAD_LEFT);

            $nameParts = explode(' ', trim($validated['name']));
            $firstName = $nameParts[0] ?? $validated['name'];
            $lastName = count($nameParts) > 1 ? end($nameParts) : 'Official';

            $profile = SkOfficialProfile::create([
                'user_id' => $user->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'barangay_name' => $validated['barangay_name'],
                'position' => $validated['position'],
                'committee_assigned' => $validated['committee_assigned'] ?? 'Executive & Council Affairs',
                'term_start' => $validated['term_start'] ?? '2023-11-01',
                'term_end' => $validated['term_end'] ?? '2026-11-01',
                'is_active_term' => !$isAdmin,
                'educational_attainment' => $validated['educational_attainment'] ?? null,
                'education_degree' => $validated['education_degree'] ?? null,
                'education_school' => $validated['education_school'] ?? null,
                'skills' => $validated['skills'] ?? [],
                'bio_advocacy' => $validated['bio_advocacy'] ?? null,
                'official_id_number' => $officialIdNumber,
                'skmt_compliant' => true,
            ]);

            ActivityLogger::log(
                'user_created',
                "Created new {$validated['role']} account for {$user->name} ({$user->email}) in Barangay {$validated['barangay_name']}",
                $user,
                ['role' => $validated['role'], 'barangay' => $validated['barangay_name'], 'position' => $validated['position']]
            );

            return response()->json([
                'success' => true,
                'message' => 'User account and council profile created successfully.',
                'data' => $user->load(['roles', 'officialProfile']),
            ], 201);
        });
    }

    /**
     * Show detailed user profile with roles, permissions, and activity history.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with([
            'roles.permissions',
            'permissions',
            'officialProfile.measures',
            'officialProfile.sessionAttendances.session',
        ])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        // Activity history related to this user
        $activities = ActivityLog::where(function ($q) use ($id) {
            $q->where('target_id', $id)->orWhere('user_id', $id);
        })
        ->orderBy('created_at', 'desc')
        ->take(20)
        ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
                'all_permissions' => $user->getAllPermissions()->pluck('name'),
                'activity_history' => $activities,
            ],
        ]);
    }

    /**
     * Update user details and profile.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::with('officialProfile')->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['sometimes', 'required', 'string', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'mobile_number' => ['nullable', 'string', 'max:25'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'barangay_name' => ['nullable', 'string', 'max:100'],
            'purok_sitio' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', Rule::in(['chairperson', 'secretary', 'treasurer', 'kagawad', 'admin'])],
            'committee_assigned' => ['nullable', 'string', 'max:150'],
            'educational_attainment' => ['nullable', 'string', 'max:100'],
            'education_degree' => ['nullable', 'string', 'max:150'],
            'education_school' => ['nullable', 'string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'bio_advocacy' => ['nullable', 'string', 'max:1000'],
            'skmt_compliant' => ['nullable', 'boolean'],
            'session_attendance_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'passed_measures_count' => ['nullable', 'integer', 'min:0'],
        ]);

        return DB::transaction(function () use ($user, $validated) {
            // Update User fields
            $userFields = array_intersect_key($validated, array_flip(['name', 'email', 'username', 'mobile_number']));
            if (!empty($userFields)) {
                $user->update($userFields);
            }

            // Update Profile fields
            if ($user->officialProfile) {
                $profileFields = array_diff_key($validated, array_flip(['name', 'email', 'username', 'mobile_number']));
                if (!empty($profileFields)) {
                    $user->officialProfile->update($profileFields);
                }
            }

            ActivityLogger::log(
                'user_updated',
                "Updated account and profile records for {$user->name} ({$user->username})",
                $user,
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'User and profile information updated successfully.',
                'data' => $user->fresh(['roles', 'officialProfile']),
            ]);
        });
    }

    /**
     * Activate or suspend a user account.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        // Cannot suspend self
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot suspend your own Super Administrator account.',
                'error_code' => 'CANNOT_SUSPEND_SELF',
            ], 422);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = (bool) $validated['is_active'];
        $user->is_active = $newStatus;
        $user->save();

        // If suspending, revoke all active Sanctum tokens immediately
        if (!$newStatus) {
            $user->tokens()->delete();
        }

        $action = $newStatus ? 'user_activated' : 'user_suspended';
        $desc = $newStatus 
            ? "Activated user account for {$user->name} ({$user->username})"
            : "Suspended user account for {$user->name} ({$user->username}). Reason: " . ($validated['reason'] ?? 'Administrative decision');

        ActivityLogger::log(
            $action,
            $desc,
            $user,
            ['is_active' => $newStatus, 'reason' => $validated['reason'] ?? null]
        );

        return response()->json([
            'success' => true,
            'message' => $newStatus ? 'Account activated successfully.' : 'Account suspended and sessions revoked.',
            'data' => [
                'user_id' => $user->id,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    /**
     * Securely reset user password.
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        // Invalidate previous sessions
        $user->tokens()->delete();

        ActivityLogger::log(
            'password_reset',
            "Administrator reset password for user {$user->name} ({$user->username})",
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Active sessions revoked.',
        ]);
    }

    /**
     * Update/Sync user roles.
     */
    public function updateRoles(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(['super_admin', 'sk_chairperson', 'sk_secretary', 'sk_treasurer', 'sk_kagawad'])],
        ]);

        // Prevent self-demotion
        if ($user->id === auth()->id() && !in_array('super_admin', $validated['roles'])) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot remove the super_admin role from your own account.',
                'error_code' => 'CANNOT_DEMOTE_SELF',
            ], 422);
        }

        $oldRoles = $user->roles->pluck('name')->toArray();
        $user->syncRoles($validated['roles']);
        $newRoles = $validated['roles'];

        ActivityLogger::log(
            'role_changed',
            "Updated roles for {$user->name}: from [" . implode(', ', $oldRoles) . "] to [" . implode(', ', $newRoles) . "]",
            $user,
            ['old_roles' => $oldRoles, 'new_roles' => $newRoles]
        );

        return response()->json([
            'success' => true,
            'message' => 'Roles updated successfully.',
            'data' => [
                'user_id' => $user->id,
                'roles' => $user->roles->pluck('name'),
            ],
        ]);
    }

    /**
     * Delete user account (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own Super Administrator account.',
                'error_code' => 'CANNOT_DELETE_SELF',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            if ($user->officialProfile) {
                $user->officialProfile->delete();
            }
            $user->delete();

            ActivityLogger::log(
                'user_deleted',
                "Deleted account and profile for {$user->name} ({$user->email})",
                $user
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'User account successfully deleted.',
        ]);
    }
}

