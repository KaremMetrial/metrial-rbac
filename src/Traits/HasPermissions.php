<?php

namespace Metrial\RBAC\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Models\Team;
use Metrial\RBAC\Services\AssignmentService;

trait HasPermissions
{
    use HasRoles;

    // ── Assignment ───────────────────────────────────────────────────

    public function givePermissionTo(string|Permission $permission, ?Team $team = null, ?string $guard = null, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $expiresAt = null): void
    {
        app(AssignmentService::class)->givePermissionTo($this, $permission, $team, $guard, $startsAt, $expiresAt);
    }

    public function revokePermissionTo(string|Permission $permission, ?Team $team = null): void
    {
        app(AssignmentService::class)->revokePermissionTo($this, $permission, $team);
    }

    public function syncPermissions(array $permissions, ?Team $team = null): void
    {
        app(AssignmentService::class)->syncPermissions($this, $permissions, $team);
    }

    // ── Direct Permission Checks ─────────────────────────────────────

    public function permissions(): MorphToMany
    {
        return $this->morphToMany(Permission::class, 'model', 'model_permissions')
            ->withPivot(['team_id', 'guard_name', 'starts_at', 'expires_at', 'assigned_by'])
            ->withTimestamps();
    }
}
