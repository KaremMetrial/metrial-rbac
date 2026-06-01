<?php

namespace Metrial\RBAC\Controllers\Api\Rbac;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Http\Resources\PermissionResource;
use Metrial\RBAC\Facades\Rbac;

class PermissionController
{
    public function index(Request $request): JsonResponse
    {
        $guard = $request->query('guard', 'web');
        $grouped = $request->boolean('grouped', false);

        if ($grouped) {
            $permissions = Rbac::permission()->getAllGrouped($guard);

            return response()->json([
                'data' => $permissions->map(fn ($group) => PermissionResource::collection($group)),
            ]);
        }

        $permissions = Rbac::permission()->getAllPermissions($guard);

        return response()->json([
            'data' => PermissionResource::collection($permissions),
        ]);
    }

    public function show(Permission $permission): JsonResponse
    {
        $permission->load('roles');

        return response()->json([
            'data' => new PermissionResource($permission),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:permissions,name',
            'guard_name'  => 'nullable|string|max:50',
            'group'       => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $permission = Rbac::permission()->create($validated);

        return response()->json([
            'message' => 'Permission created successfully.',
            'data'    => new PermissionResource($permission),
        ], 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255|unique:permissions,name,' . $permission->id,
            'guard_name'  => 'sometimes|string|max:50',
            'group'       => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $permission->update($validated);

        return response()->json([
            'message' => 'Permission updated successfully.',
            'data'    => new PermissionResource($permission->fresh()),
        ]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted successfully.',
        ]);
    }
}
