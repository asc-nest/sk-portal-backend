<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OfficialInvitation;
use App\Models\User;
use App\Models\SkOfficialProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    /**
     * Generate an invite link for an SK Official.
     * Accessible by Admin and SK Chairperson.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position' => ['required', Rule::in(['chairperson', 'kagawad', 'secretary', 'treasurer'])],
            'committee_assigned' => ['nullable', 'string', 'max:150'],
        ]);

        $token = Str::random(64);
        $expiresAt = Carbon::now()->addDays(7);

        $invitation = OfficialInvitation::create([
            'email' => $validated['email'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'position' => $validated['position'],
            'committee_assigned' => $validated['committee_assigned'] ?? null,
            'token' => $token,
            'expires_at' => $expiresAt,
            'created_by' => $request->user()->id,
        ]);

        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        $inviteUrl = "{$frontendUrl}/invite/{$token}";

        return response()->json([
            'success' => true,
            'message' => 'Official invitation link generated successfully.',
            'data' => [
                'invitation_id' => $invitation->id,
                'email' => $invitation->email,
                'position' => $invitation->position,
                'committee_assigned' => $invitation->committee_assigned,
                'invite_token' => $token,
                'invite_url' => $inviteUrl,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Verify an invitation token when an official visits /invite/[token].
     */
    public function verify(string $token): JsonResponse
    {
        $invitation = OfficialInvitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation link is invalid or does not exist.',
                'error_code' => 'INVITATION_NOT_FOUND',
            ], 404);
        }

        if ($invitation->isAccepted()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation has already been used.',
                'error_code' => 'INVITATION_ALREADY_ACCEPTED',
            ], 410);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation link has expired. Please contact your SK Chairperson.',
                'error_code' => 'INVITATION_EXPIRED',
            ], 410);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation is valid.',
            'data' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'position' => $invitation->position,
                'committee_assigned' => $invitation->committee_assigned,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Accept the invitation, create account, set password, and log the official in.
     */
    public function accept(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'contact_number' => ['nullable', 'string', 'max:25'],
            'bio_advocacy' => ['nullable', 'string', 'max:1000'],
        ]);

        $invitation = OfficialInvitation::where('token', $validated['token'])->first();

        if (!$invitation || !$invitation->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'This invitation is invalid or has expired.',
                'error_code' => 'INVALID_INVITATION',
            ], 422);
        }

        return DB::transaction(function () use ($invitation, $validated) {
            // 1. Create User Account
            $user = User::create([
                'name' => trim("{$invitation->first_name} {$invitation->last_name}"),
                'username' => $validated['username'],
                'email' => $invitation->email,
                'mobile_number' => $validated['contact_number'] ?? null,
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // 2. Assign Spatie Role based on position
            $roleMap = [
                'chairperson' => 'sk_chairperson',
                'secretary' => 'sk_secretary',
                'treasurer' => 'sk_treasurer',
                'kagawad' => 'sk_kagawad',
            ];
            $role = $roleMap[$invitation->position] ?? 'sk_kagawad';
            $user->assignRole($role);

            // 3. Generate Official ID Number
            $idNumber = sprintf('SK-%s-%03d', date('Y'), $user->id);

            // 4. Create SK Official Profile
            $profile = SkOfficialProfile::create([
                'user_id' => $user->id,
                'first_name' => $invitation->first_name ?? 'Official',
                'last_name' => $invitation->last_name ?? 'Member',
                'position' => $invitation->position,
                'committee_assigned' => $invitation->committee_assigned,
                'contact_number' => $validated['contact_number'] ?? null,
                'bio_advocacy' => $validated['bio_advocacy'] ?? null,
                'official_id_number' => $idNumber,
                'term_start' => '2023-11-01',
                'term_end' => '2026-11-01',
                'is_active_term' => true,
                'barangay_name' => 'Barangay Central',
            ]);

            // 5. Mark Invitation as Accepted
            $invitation->update([
                'accepted_at' => now(),
            ]);

            // 6. Issue Sanctum Bearer Token for immediate auto-login
            $token = $user->createToken('sk_official_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Account setup complete. Welcome to the SK Portal!',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'roles' => $user->roles->pluck('name'),
                        'profile' => $profile,
                    ],
                ],
            ], 201);
        });
    }
}
