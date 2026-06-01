<?php

namespace Metrial\RBAC\Controllers\Api\Rbac;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Metrial\RBAC\Models\AuditLog;
use Metrial\RBAC\Http\Resources\AuditLogResource;

class AuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::orderByDesc('created_at');

        if ($request->has('actor_id')) {
            $query->where('actor_id', $request->query('actor_id'));
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->query('entity_id'));
        }

        if ($request->has('action')) {
            $query->where('action', $request->query('action'));
        }

        $perPage = $request->query('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json([
            'data' => new AuditLogResource($auditLog),
        ]);
    }
}
