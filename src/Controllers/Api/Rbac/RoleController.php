<?php

namespace Metrial\RBAC\Controllers\Api\Rbac;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Http\Resources\RoleResource;
use Metrial\RBAC\Facades\Rbac;

class RoleController
{
    public function index(Request $request): JsonResponse
    {
        $guard = $request->query('guard', 'web');
        $roles = Rbac::role()->getAllRoles($guard);

        return response()->json([
            'data' => RoleResource::collection($roles),
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'parentRoles', 'childRoles']);

        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:roles,slug',
            'description' => 'nullable|string',
            'guard_name'  => 'nullable|string|max:50',
            'level'       => 'nullable|integer|min:0',
            'is_system'   => 'nullable|boolean',
            'team_id'     => 'nullable|string',
        ]);

        $role = Rbac::role()->create($validated);

        return response()->json([
            'message' => 'Role created successfully.',
            'data'    => new RoleResource($role),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'slug'        => 'sometimes|string|max:255|unique:roles,slug,' . $role->id,
            'description' => 'nullable|string',
            'guard_name'  => 'sometimes|string|max:50',
            'level'       => 'sometimes|integer|min:0',
            'is_system'   => 'sometimes|boolean',
            'team_id'     => 'nullable|string',
        ]);

        $role->update($validated);

        return response()->json([
            'message' => 'Role updated successfully.',
            'data'    => new RoleResource($role->fresh()),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json([
                'message' => 'System roles cannot be deleted.',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }
}
