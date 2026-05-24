<?php

namespace Metrial\RBAC\Contracts;

interface Role
{
    public function assignRole(string|\Metrial\RBAC\Models\Role $role, $team = null, ?string $guard = null, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $expiresAt = null): void;

    public function removeRole(string|\Metrial\RBAC\Models\Role $role, $team = null): void;

    public function syncRoles(array $roles, $team = null): void;

    public function hasRole(string|\Metrial\RBAC\Models\Role $role, $team = null): bool;

    public function hasAllRoles(array $roles, $team = null): bool;

    public function hasAnyRole(array $roles, $team = null): bool;
}
