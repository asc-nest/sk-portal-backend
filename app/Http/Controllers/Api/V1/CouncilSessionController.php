<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CouncilSession;
use App\Models\SessionAttendance;
use App\Models\SkOfficialProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CouncilSessionController extends Controller
{
    /**
     * Check if user is authorized to manage roll calls (Secretary, Chairperson, Admin).
     */
    protected function authorizeSecretaryOrAdmin(User $user): bool
    {
        return $user->hasRole('super_admin') 
            || $user->hasRole('sk_secretary') 
            || $user->hasRole('sk_chairperson')
            || $user->username === 'superadmin';
    }

    /**
     * List all recorded Sanggunian sessions with summary metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = CouncilSession::with([
            'attendances.official:id,first_name,last_name,position,official_id_number',
            'recorder:id,name,username'
        ])
        ->orderBy('session_date', 'desc')
        ->orderBy('id', 'desc')
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Council sessions and roll call history retrieved.',
            'data' => $sessions,
        ]);
    }

    /**
     * View single session with full roll call detail.
     */
    public function show(int $id): JsonResponse
    {
        $session = CouncilSession::with([
            'attendances.official',
            'recorder:id,name,username'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Session details retrieved.',
            'data' => $session,
        ]);
    }

    /**
     * Record a new Council Session and batch log roll call attendance.
     * Restricted to SK Secretary, SK Chairperson, and Admin.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$this->authorizeSecretaryOrAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only the SK Secretary, Chairperson, or System Admin can record session roll calls.',
            ], 403);
        }

        $validated = $request->validate([
            'session_title' => ['required', 'string', 'max:255'],
            'session_number' => ['required', 'string', 'max:50'],
            'session_type' => ['required', Rule::in(['regular', 'special'])],
            'session_date' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:150'],
            'presided_by' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attendances' => ['required', 'array', 'min:1'],
            'attendances.*.official_profile_id' => ['required', 'exists:sk_officials_profiles,id'],
            'attendances.*.status' => ['required', Rule::in(['present', 'absent', 'excused', 'late'])],
            'attendances.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($validated, $user) {
            $session = CouncilSession::create([
                'barangay_name' => 'Barangay Central',
                'session_title' => $validated['session_title'],
                'session_number' => $validated['session_number'],
                'session_type' => $validated['session_type'],
                'session_date' => $validated['session_date'],
                'venue' => $validated['venue'] ?? 'Barangay Session Hall',
                'presided_by' => $validated['presided_by'] ?? 'Hon. John Patrick Santos',
                'created_by' => $user->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            $profileIds = [];
            foreach ($validated['attendances'] as $att) {
                SessionAttendance::create([
                    'council_session_id' => $session->id,
                    'official_profile_id' => $att['official_profile_id'],
                    'status' => $att['status'],
                    'remarks' => $att['remarks'] ?? null,
                ]);
                $profileIds[] = $att['official_profile_id'];
            }

            // Recalculate attendance rates for all affected officials
            $profiles = SkOfficialProfile::whereIn('id', array_unique($profileIds))->get();
            foreach ($profiles as $prof) {
                $prof->syncSessionAttendanceRate();
            }

            return response()->json([
                'success' => true,
                'message' => 'Council session and roll call ledger recorded successfully.',
                'data' => $session->load('attendances.official'),
            ], 201);
        });
    }

    /**
     * Update session roll call and recalculate rates.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$this->authorizeSecretaryOrAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify council roll calls.',
            ], 403);
        }

        $session = CouncilSession::findOrFail($id);

        $validated = $request->validate([
            'session_title' => ['sometimes', 'required', 'string', 'max:255'],
            'session_number' => ['sometimes', 'required', 'string', 'max:50'],
            'session_type' => ['sometimes', 'required', Rule::in(['regular', 'special'])],
            'session_date' => ['sometimes', 'required', 'date'],
            'venue' => ['nullable', 'string', 'max:150'],
            'presided_by' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attendances' => ['nullable', 'array'],
            'attendances.*.official_profile_id' => ['required_with:attendances', 'exists:sk_officials_profiles,id'],
            'attendances.*.status' => ['required_with:attendances', Rule::in(['present', 'absent', 'excused', 'late'])],
            'attendances.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($session, $validated) {
            $session->update([
                'session_title' => $validated['session_title'] ?? $session->session_title,
                'session_number' => $validated['session_number'] ?? $session->session_number,
                'session_type' => $validated['session_type'] ?? $session->session_type,
                'session_date' => $validated['session_date'] ?? $session->session_date,
                'venue' => $validated['venue'] ?? $session->venue,
                'presided_by' => $validated['presided_by'] ?? $session->presided_by,
                'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $session->notes,
            ]);

            $profileIds = [];
            if (!empty($validated['attendances'])) {
                foreach ($validated['attendances'] as $att) {
                    SessionAttendance::updateOrCreate(
                        [
                            'council_session_id' => $session->id,
                            'official_profile_id' => $att['official_profile_id'],
                        ],
                        [
                            'status' => $att['status'],
                            'remarks' => $att['remarks'] ?? null,
                        ]
                    );
                    $profileIds[] = $att['official_profile_id'];
                }

                $profiles = SkOfficialProfile::whereIn('id', array_unique($profileIds))->get();
                foreach ($profiles as $prof) {
                    $prof->syncSessionAttendanceRate();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Council session roll call updated.',
                'data' => $session->fresh(['attendances.official']),
            ]);
        });
    }

    /**
     * Delete a session and recalculate rates.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$this->authorizeSecretaryOrAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete session records.',
            ], 403);
        }

        $session = CouncilSession::findOrFail($id);
        $profileIds = $session->attendances()->pluck('official_profile_id')->toArray();

        $session->delete();

        // Recalculate attendance rates
        $profiles = SkOfficialProfile::whereIn('id', array_unique($profileIds))->get();
        foreach ($profiles as $prof) {
            $prof->syncSessionAttendanceRate();
        }

        return response()->json([
            'success' => true,
            'message' => 'Session and associated roll call deleted.',
        ]);
    }

    /**
     * Get attendance record history for the currently logged-in official.
     */
    public function officialHistory(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->officialProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'No official profile found for this user.',
            ], 404);
        }

        $attendances = SessionAttendance::where('official_profile_id', $profile->id)
            ->with(['session:id,session_title,session_number,session_type,session_date,venue,presided_by'])
            ->get()
            ->sortByDesc(fn ($a) => $a->session?->session_date ?? '')
            ->values();

        $total = $attendances->count();
        $present = $attendances->whereIn('status', ['present', 'late'])->count();
        $excused = $attendances->where('status', 'excused')->count();
        $absent = $attendances->where('status', 'absent')->count();

        return response()->json([
            'success' => true,
            'message' => 'Official roll call attendance ledger retrieved.',
            'data' => [
                'attendances' => $attendances,
                'summary' => [
                    'total_sessions' => $total,
                    'present_count' => $present,
                    'excused_count' => $excused,
                    'absent_count' => $absent,
                    'attendance_rate' => $profile->session_attendance_rate,
                ],
            ],
        ]);
    }
}

