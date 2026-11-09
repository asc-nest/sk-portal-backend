<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
            $userCount = User::role($role->name)->count();
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => match ($role->name) {
                    'super_admin' => 'Super Administrator',
                    'sk_chairperson' => 'SK Chairperson',
                    'sk_secretary' => 'SK Secretary',
                    'sk_treasurer' => 'SK Treasurer',
                    'sk_kagawad' => 'SK Kagawad (Councilor)',
                    default => ucfirst(str_replace(['sk_', '_'], ['', ' '], $role->name)),
                },
                'description' => match ($role->name) {
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
     * Update permissions assigned to a role.
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
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $oldPermissions = $role->permissions->pluck('name')->toArray();
        $role->syncPermissions($validated['permissions']);

        ActivityLogger::log(
            'role_permissions_updated',
            "Updated permissions for role '{$role->name}'",
            null,
            ['role' => $role->name, 'old' => $oldPermissions, 'new' => $validated['permissions']]
        );

        return response()->json([
            'success' => true,
            'message' => "Permissions for role '{$role->name}' updated successfully.",
            'data' => $role->fresh('permissions'),
        ]);
    }
}

