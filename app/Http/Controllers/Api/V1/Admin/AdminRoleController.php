<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminRoleController extends Controller
{
    /**
     * List all roles with permission mappings and assigned user counts.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            $userCount = DB::table('model_has_roles')->where('role_id', $role->id)->count();
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? match ($role->name) {
                    'super_admin' => 'Super Administrator',
                    'sk_chairperson' => 'SK Chairperson',
                    'sk_secretary' => 'SK Secretary',
                    'sk_treasurer' => 'SK Treasurer',
                    'sk_kagawad' => 'SK Kagawad (Councilor)',
                    default => ucfirst(str_replace(['sk_', '_'], ['', ' '], $role->name)),
                },
                'description' => $role->description ?? match ($role->name) {
                    'super_admin' => 'Full administrative access across all 38 barangays, user control, system configurations, and security audits.',
                    'sk_chairperson' => 'Executive council authority, legislative presidency, council membership invitations, and barangay representation.',
                    'sk_secretary' => 'Secretariat operations, Sanggunian session minutes, roll call ledger maintenance, and youth registries.',
                    'sk_treasurer' => 'Financial operations, youth development investment allocations, treasury reporting, and budget desk.',
                    'sk_kagawad' => 'Legislative sponsorship, committee chairmanship, youth sports/education advocacy, and community outreach.',
                    default => 'Official role within the Sangguniang Kabataan Federation.',
                },
                'permissions' => $role->permissions->pluck('name'),
                'user_count' => $userCount,
            ];
        });

        $permissions = Permission::all()->map(function ($perm) {
            return [
                'id' => $perm->id,
                'name' => $perm->name,
                'label' => ucwords(str_replace('_', ' ', $perm->name)),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'roles' => $roles,
                'all_permissions' => $permissions,
            ],
        ]);
    }

    /**
     * Create a new role with optional permission assignments.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => config('auth.defaults.guard', 'web'),
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (!empty($validated['permissions'])) {
            $perms = Permission::whereIn('name', $validated['permissions'])
                ->where('guard_name', $role->guard_name)
                ->get();
            $role->syncPermissions($perms);
        }

        ActivityLogger::log(
            'role_created',
            "Created new RBAC role '{$role->name}' ({$validated['display_name']})",
            null,
            ['role' => $role->name, 'permissions' => $validated['permissions'] ?? []]
        );

        return response()->json([
            'success' => true,
            'message' => "Role '{$validated['display_name']}' created successfully.",
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('name'),
                'user_count' => 0,
            ],
        ], 201);
    }

    /**
     * Update role details and permissions assigned to a role.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if (isset($validated['display_name'])) {
            $role->display_name = $validated['display_name'];
        }
        if (array_key_exists('description', $validated)) {
            $role->description = $validated['description'];
        }
        $role->save();

        if (isset($validated['permissions'])) {
            $oldPermissions = $role->permissions->pluck('name')->toArray();
            $perms = Permission::whereIn('name', $validated['permissions'])
                ->where('guard_name', $role->guard_name)
                ->get();
            $role->syncPermissions($perms);

            ActivityLogger::log(
                'role_permissions_updated',
                "Updated role '{$role->name}'",
                null,
                ['role' => $role->name, 'old' => $oldPermissions, 'new' => $validated['permissions']]
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Role '{$role->name}' updated successfully.",
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name ?? match ($role->name) {
                    'super_admin' => 'Super Administrator',
                    'sk_chairperson' => 'SK Chairperson',
                    'sk_secretary' => 'SK Secretary',
                    'sk_treasurer' => 'SK Treasurer',
                    'sk_kagawad' => 'SK Kagawad (Councilor)',
                    default => ucfirst(str_replace(['sk_', '_'], ['', ' '], $role->name)),
                },
                'description' => $role->description ?? match ($role->name) {
                    'super_admin' => 'Full administrative access across all 38 barangays, user control, system configurations, and security audits.',
                    'sk_chairperson' => 'Executive council authority, legislative presidency, council membership invitations, and barangay representation.',
                    'sk_secretary' => 'Secretariat operations, Sanggunian session minutes, roll call ledger maintenance, and youth registries.',
                    'sk_treasurer' => 'Financial operations, youth development investment allocations, treasury reporting, and budget desk.',
                    'sk_kagawad' => 'Legislative sponsorship, committee chairmanship, youth sports/education advocacy, and community outreach.',
                    default => 'Official role within the Sangguniang Kabataan Federation.',
                },
                'permissions' => $role->fresh('permissions')->permissions->pluck('name'),
                'user_count' => User::role($role->name)->count(),
            ],
        ]);
    }
}

