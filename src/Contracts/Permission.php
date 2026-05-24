<?php

namespace Metrial\RBAC\Contracts;

interface Permission
{
    public function givePermissionTo(string|\Metrial\RBAC\Models\Permission $permission, $team = null, ?string $guard = null, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $expiresAt = null): void;

    public function revokePermissionTo(string|\Metrial\RBAC\Models\Permission $permission, $team = null): void;

    public function syncPermissions(array $permissions, $team = null): void;

    public function hasPermissionTo(string|\Metrial\RBAC\Models\Permission $permission, $team = null): bool;

    public function hasDirectPermission(string|\Metrial\RBAC\Models\Permission $permission, $team = null): bool;
}
