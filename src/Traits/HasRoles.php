<?php

namespace Metrial\RBAC\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Team;
use Metrial\RBAC\Services\AssignmentService;
use Metrial\RBAC\Services\CacheService;

trait HasRoles
{
    /**
     * The currently active team context for this model instance.
     */
    protected ?string $activeTeamId = null;

    // ── Assignment ───────────────────────────────────────────────────

    public function assignRole(string|Role $role, ?Team $team = null, ?string $guard = null, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $expiresAt = null): void
    {
        $this->ensureRolesTraitInModel();

        app(AssignmentService::class)->assignRole($this, $role, $team, $guard, $startsAt, $expiresAt);
    }

    public function removeRole(string|Role $role, ?Team $team = null): void
    {
        $this->ensureRolesTraitInModel();

        app(AssignmentService::class)->removeRole($this, $role, $team);
    }

    public function syncRoles(array $roles, ?Team $team = null): void
    {
        $this->ensureRolesTraitInModel();

        app(AssignmentService::class)->syncRoles($this, $roles, $team);
    }

    // ── Role Checks ──────────────────────────────────────────────────

    public function hasRole(string|Role|array $roles, ?Team $team = null): bool
    {
        $this->ensureRolesTraitInModel();

        // Super-admin bypass
        $superAdminRole = config('rbac.super_admin_role');
        if ($superAdminRole && $this->hasRoleDirect($superAdminRole, $team)) {
            return true;
        }

        if (is_array($roles)) {
            return $this->hasAnyRole($roles, $team);
        }

        $role = $this->resolveRoleString($roles);

        return $this->getRoles($team)->pluck('slug')->contains($role)
            || $this->getRoles($team)->pluck('id')->contains($role);
    }

    public function hasAllRoles(array $roles, ?Team $team = null): bool
    {
        $this->ensureRolesTraitInModel();

        $superAdminRole = config('rbac.super_admin_role');
        if ($superAdminRole && $this->hasRoleDirect($superAdminRole, $team)) {
            return true;
        }

        $roleSlugs = $this->getRoles($team)->pluck('slug')->merge(
            $this->getRoles($team)->pluck('id')
        );

        foreach ($roles as $role) {
            $resolved = $this->resolveRoleString($role);
            if (! $roleSlugs->contains($resolved)) {
                return false;
            }
        }

        return true;
    }

    public function hasAnyRole(array $roles, ?Team $team = null): bool
    {
        $this->ensureRolesTraitInModel();

        $superAdminRole = config('rbac.super_admin_role');
        if ($superAdminRole && $this->hasRoleDirect($superAdminRole, $team)) {
            return true;
        }

        $roleSlugs = $this->getRoles($team)->pluck('slug')->merge(
            $this->getRoles($team)->pluck('id')
        );

        foreach ($roles as $role) {
            $resolved = $this->resolveRoleString($role);
            if ($roleSlugs->contains($resolved)) {
                return true;
            }
        }

        return false;
    }

    protected function hasRoleDirect(string|Role $role, ?Team $team = null): bool
    {
        $resolved = $this->resolveRoleString($role);

        return $this->getRoles($team)->pluck('slug')->contains($resolved)
            || $this->getRoles($team)->pluck('id')->contains($resolved);
    }

    // ── Permission Checks (delegated from roles) ─────────────────────

    public function hasPermissionTo(string|\Metrial\RBAC\Models\Permission $permission, ?Team $team = null): bool
    {
        $this->ensureRolesTraitInModel();

        // Super-admin bypass
        $superAdminRole = config('rbac.super_admin_role');
        if ($superAdminRole && $this->hasRoleDirect($superAdminRole, $team)) {
            if (config('rbac.audit.enabled')) {
                app(\Metrial\RBAC\Services\AuditService::class)->log(
                    'superadmin.bypass', 'permission',
                    $permission instanceof \Metrial\RBAC\Models\Permission ? $permission->id : $permission,
                    [], ['granted' => true]
                );
            }
            return true;
        }

        $permName = $permission instanceof \Metrial\RBAC\Models\Permission
            ? $permission->name
            : (string) $permission;

        $permissions = $this->getPermissions($team);

        return $permissions->contains($permName);
    }

    public function hasAnyPermission(array $permissions, ?Team $team = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionTo($permission, $team)) {
                return true;
            }
        }

        return false;
    }

    public function hasDirectPermission(string|\Metrial\RBAC\Models\Permission $permission, ?Team $team = null): bool
    {
        $this->ensureRolesTraitInModel();

        $permName = $permission instanceof \Metrial\RBAC\Models\Permission
            ? $permission->name
            : (string) $permission;

        return app(AssignmentService::class)->getDirectPermissions($this, $team)->contains($permName);
    }

    // ── Team Context ─────────────────────────────────────────────────

    public function switchTeam(?Team $team): static
    {
        $this->activeTeamId = $team?->id;

        return $this;
    }

    public function getActiveTeamId(): ?string
    {
        return $this->activeTeamId;
    }

    // ── Resolution ───────────────────────────────────────────────────

    public function getPermissions(?Team $team = null): Collection
    {
        $team = $team ?? $this->resolveActiveTeam();

        return app(AssignmentService::class)->getPermissions($this, $team);
    }

    /**
     * @return Collection<int, Role>
     */
    public function getRoles(?Team $team = null): Collection
    {
        $team = $team ?? $this->resolveActiveTeam();

        return app(AssignmentService::class)->getRoles($this, $team);
    }

    // ── Relationships ────────────────────────────────────────────────

    public function roles(): MorphToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_roles')
            ->withPivot(['team_id', 'guard_name', 'starts_at', 'expires_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function teams(): MorphToMany
    {
        return $this->morphToMany(Team::class, 'model', 'model_teams')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    // ── Helpers ──────────────────────────────────────────────────────

    protected function ensureRolesTraitInModel(): void
    {
        if (! method_exists($this, 'getKey')) {
            throw new \RuntimeException('HasRoles trait must be used on an Eloquent model.');
        }
    }

    protected function resolveActiveTeam(): ?Team
    {
        if ($this->activeTeamId) {
            return \Metrial\RBAC\Models\Team::find($this->activeTeamId);
        }

        // Default to first team if configured
        if (config('rbac.teams.user_primary_team') && config('rbac.teams.enabled')) {
            return $this->teams->first();
        }

        return null;
    }

    protected function resolveRoleString(string|Role $role): string
    {
        return $role instanceof Role ? $role->slug : (string) $role;
    }
}
