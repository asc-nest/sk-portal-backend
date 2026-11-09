<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register / Onboard a new SK Official with comprehensive profile data.
     */
    public function register(Request $request): JsonResponse
    {
        $allowPublic = (bool) \App\Models\SystemSetting::get('allow_public_onboarding', true);
        if (!$allowPublic) {
            return response()->json([
                'success' => false,
                'message' => 'Online official onboarding is currently disabled by the Super Administrator. Please request an official invitation from your SK Chairperson.',
                'error_code' => 'ONBOARDING_DISABLED',
            ], 403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string'],
            'birthdate' => ['nullable', 'date'],
            'contact_number' => ['nullable', 'string', 'max:25'],
            'barangay_name' => ['required', 'string', 'max:100'],
            'purok_sitio' => ['nullable', 'string', 'max:100'],
            'position' => ['required', 'in:chairperson,kagawad,secretary,treasurer'],
            'committee_assigned' => ['nullable', 'string', 'max:150'],
            'term_start' => ['nullable', 'date'],
            'term_end' => ['nullable', 'date'],
            'educational_attainment' => ['nullable', 'string', 'max:100'],
            'education_degree' => ['nullable', 'string', 'max:150'],
            'education_school' => ['nullable', 'string', 'max:200'],
            'skills' => ['nullable', 'array'],
            'bio_advocacy' => ['nullable', 'string', 'max:1000'],
        ]);

        $middleInitial = !empty($validated['middle_name']) ? substr($validated['middle_name'], 0, 1) . '. ' : '';
        $suffixPart = !empty($validated['suffix']) ? ' ' . $validated['suffix'] : '';
        $fullName = trim("{$validated['first_name']} {$middleInitial}{$validated['last_name']}{$suffixPart}");

        $user = User::create([
            'name' => $fullName,
            'email' => $validated['email'],
            'username' => $validated['username'],
            'mobile_number' => $validated['contact_number'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $roleMap = [
            'chairperson' => 'sk_chairperson',
            'secretary' => 'sk_secretary',
            'treasurer' => 'sk_treasurer',
            'kagawad' => 'sk_kagawad',
        ];
        $role = $roleMap[$validated['position']] ?? 'sk_kagawad';
        $user->assignRole($role);

        $officialIdNumber = sprintf('SK-%s-%03d', date('Y'), $user->id);

        $profile = \App\Models\SkOfficialProfile::create([
            'user_id' => $user->id,
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'nickname' => $validated['nickname'] ?? null,
            'gender' => $validated['gender'] ?? 'male',
            'birthdate' => $validated['birthdate'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'barangay_name' => $validated['barangay_name'],
            'purok_sitio' => $validated['purok_sitio'] ?? null,
            'position' => $validated['position'],
            'committee_assigned' => $validated['committee_assigned'] ?? 'Youth Empowerment & Sports',
            'term_start' => $validated['term_start'] ?? \App\Models\SystemSetting::get('term_start_date', '2023-11-01'),
            'term_end' => $validated['term_end'] ?? \App\Models\SystemSetting::get('term_end_date', '2026-11-01'),
            'is_active_term' => true,
            'educational_attainment' => $validated['educational_attainment'] ?? null,
            'education_degree' => $validated['education_degree'] ?? null,
            'education_school' => $validated['education_school'] ?? null,
            'skills' => $validated['skills'] ?? [],
            'bio_advocacy' => $validated['bio_advocacy'] ?? null,
            'official_id_number' => $officialIdNumber,
            'skmt_compliant' => true,
        ]);

        $token = $user->createToken('sk_official_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Onboarding and registration completed successfully.',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'mobile_number' => $user->mobile_number,
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'profile' => $profile,
                ],
            ],
        ], 201);
    }

    /**
     * Authenticate an official or admin with email or username.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $identifier = $validated['identifier'];
        $fieldType = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::with(['roles', 'officialProfile'])
            ->where($fieldType, $identifier)
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials do not match our records.',
                'error_code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact your SK Chairperson.',
                'error_code' => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        // Enforce Maintenance Mode: only super_admin can login when maintenance is active
        $isMaintenance = (bool) \App\Models\SystemSetting::get('maintenance_mode', false);
        $isSuperAdmin = $user->hasRole('super_admin') || $user->username === 'superadmin';

        if ($isMaintenance && !$isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'The SK Portal is currently under maintenance. Only Super Administrators can log in at this time.',
                'error_code' => 'MAINTENANCE_MODE_ACTIVE',
            ], 503);
        }

        $sessionTimeout = (int) \App\Models\SystemSetting::get('session_timeout_minutes', 480);

        // Revoke previous tokens if needed or issue a new access token
        $token = $user->createToken('sk_official_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Authentication successful.',
            'data' => [
                'token' => $token,
                'session_timeout_minutes' => $sessionTimeout,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'mobile_number' => $user->mobile_number,
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'profile' => $user->officialProfile,
                ],
            ],
        ]);
    }

    /**
     * Get the authenticated user's profile and roles.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['roles', 'officialProfile']);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated profile retrieved.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'mobile_number' => $user->mobile_number,
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                    'profile' => $user->officialProfile,
                ],
            ],
        ]);
    }

    /**
     * Revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
