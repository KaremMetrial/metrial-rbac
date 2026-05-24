<?php

namespace Metrial\RBAC\Services;

use Illuminate\Support\Facades\DB;
use Metrial\RBAC\Models\AuditLog;

class AuditService
{
    public function log(string $action, string $entityType, string $entityId, array $oldValue = [], array $newValue = []): void
    {
        if (! config('rbac.audit.enabled', true)) {
            return;
        }

        $context = $this->detectContext();
        $actorId = auth()->id();

        // For CLI/context without request, these will be null
        $ipAddress = null;
        $userAgent = null;

        if (request()) {
            $ipAddress = request()->ip();
            $userAgent = request()->userAgent();
        }

        $payload = [
            'actor_id'    => $actorId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'old_value'   => $oldValue,
            'new_value'   => $newValue,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'context'     => $context,
        ];

        if (config('rbac.audit.queue', false)) {
            dispatch(function () use ($payload) {
                AuditLog::create($payload);
            });
        } else {
            AuditLog::create($payload);
        }
    }

    public function forUser($user, ?int $limit = null)
    {
        $query = AuditLog::where('actor_id', $user->getAuthIdentifier())
            ->orWhere(function ($q) use ($user) {
                $q->where('entity_type', 'role')
                    ->where('entity_id', $user->getAuthIdentifier());
            })
            ->orderByDesc('created_at');

        return $limit ? $query->limit($limit)->get() : $query->get();
    }

    public function prune(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return AuditLog::where('created_at', '<', now()->subDays($days))->delete();
    }

    protected function detectContext(): string
    {
        if (app()->runningInConsole()) {
            return 'cli';
        }

        if (app()->runningUnitTests()) {
            return 'http';
        }

        if (request() && request()->bearerToken()) {
            return 'api';
        }

        return 'http';
    }
}
