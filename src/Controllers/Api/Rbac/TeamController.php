<?php

namespace Metrial\RBAC\Controllers\Api\Rbac;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Metrial\RBAC\Models\Team;
use Metrial\RBAC\Http\Resources\TeamResource;
use Metrial\RBAC\Facades\Rbac;

class TeamController
{
    public function index(): JsonResponse
    {
        $teams = Rbac::team()->getAllTeams();

        return response()->json([
            'data' => TeamResource::collection($teams),
        ]);
    }

    public function show(Team $team): JsonResponse
    {
        $team->load(['members', 'roles']);

        return response()->json([
            'data' => new TeamResource($team),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:teams,slug',
            'description' => 'nullable|string',
        ]);

        $team = Rbac::team()->create($validated);

        return response()->json([
            'message' => 'Team created successfully.',
            'data'    => new TeamResource($team),
        ], 201);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'slug'        => 'sometimes|string|max:255|unique:teams,slug,' . $team->id,
            'description' => 'nullable|string',
        ]);

        $team->update($validated);

        return response()->json([
            'message' => 'Team updated successfully.',
            'data'    => new TeamResource($team->fresh()),
        ]);
    }

    public function destroy(Team $team): JsonResponse
    {
        $team->delete();

        return response()->json([
            'message' => 'Team deleted successfully.',
        ]);
    }

    public function addMember(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'user_id'  => 'required|string',
            'is_owner' => 'nullable|boolean',
        ]);

        $userModel = config('rbac.user_model', config('auth.providers.users.model'));
        $user = $userModel::findOrFail($validated['user_id']);

        Rbac::team()->addMember($team, $user, $validated['is_owner'] ?? false);

        return response()->json([
            'message' => 'Member added successfully.',
        ]);
    }

    public function removeMember(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string',
        ]);

        $userModel = config('rbac.user_model', config('auth.providers.users.model'));
        $user = $userModel::findOrFail($validated['user_id']);

        Rbac::team()->removeMember($team, $user);

        return response()->json([
            'message' => 'Member removed successfully.',
        ]);
    }
}
