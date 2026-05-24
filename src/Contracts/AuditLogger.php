<?php

namespace Metrial\RBAC\Contracts;

interface AuditLogger
{
    public function log(string $action, string $entityType, string $entityId, array $oldValue = [], array $newValue = []): void;

    public function forUser($user, ?int $limit = null);

    public function prune(int $days): int;
}
