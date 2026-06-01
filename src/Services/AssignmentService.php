<?php

namespace Metrial\RBAC\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Models\Team;

class AssignmentService
{
    public function __construct(
        protected CacheService $cache,
        protected AuditService $audit,
        protected RoleService $roleService,
    ) {}

    // ── Role Assignment ──────────────────────────────────────────────

    public function assignRole(Model $model, string|Role $role, ?Team $team = null, ?string $guard = null, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $expiresAt = null): void
    {
        $role = $this->resolveRole($role, $guard);
        $guard ??= 'web';
        $teamId = $team?->id;
        $actorId = auth()->id();

        DB::table('model_roles')->insert([
            'id'           => \Illuminate\Support\Str::uuid()->toString(),
            'team_id'      => $teamId,
            'role_id'      => $role->id,
            'model_type'   => $model->getMorphClass(),
            'model_id'     => $model->getKey(),
            'guard_name'   => $guard,
            'starts_at'    => $startsAt,
            'expires_at'   => $expiresAt,
            'assigned_by'  => $actorId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->bustModelCache($model);
        $this->audit->log('role.assigned', 'role', $role->id, [], [
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'team_id' => $teamId,
            'guard_name' => $guard,
        ]);
    }

    public function removeRole(Model $model, string|Role $role, ?Team $team = null): void
    {
        $role = $this->resolveRole($role);
        $teamId = $team?->id;

        $query = DB::table('model_roles')
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('role_id', $role->id);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $query->delete();

        $this->bustModelCache($model);
        $this->audit->log('role.revoked', 'role', $role->id, [], [
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'team_id' => $teamId,
        ]);
    }

    public function syncRoles(Model $model, array $roles, ?Team $team = null): void
    {
        $teamId = $team?->id;
        $morphType = $model->getMorphClass();
        $modelId = $model->getKey();

        DB::transaction(function () use ($model, $morphType, $modelId, $roles, $teamId) {
            $query = DB::table('model_roles')
                ->where('model_type', $morphType)
                ->where('model_id', $modelId);

            if ($teamId) {
                $query->where('team_id', $teamId);
            }

            $query->delete();

            $inserts = [];
            foreach ($roles as $roleData) {
                $role = $this->resolveRole($roleData['slug'] ?? $roleData['id'] ?? $roleData);
                $inserts[] = [
                    'id'         => \Illuminate\Support\Str::uuid()->toString(),
                    'team_id'    => $teamId,
                    'role_id'    => $role->id,
                    'model_type' => $morphType,
                    'model_id'   => $modelId,
                    'guard_name' => $roleData['guard_name'] ?? 'web',
                    'starts_at'  => $roleData['starts_at'] ?? null,
                    'expires_at' => $roleData['expires_at'] ?? null,
                    'assigned_by'=> auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($inserts)) {
                DB::table('model_roles')->insert($inserts);
            }
        });

        $this->bustModelCache($model);
        $this->audit->log('roles.synced', 'model', $model->getKey(), [], [
            'model_type' => $morphType,
            'roles' => collect($roles)->map(fn ($r) => is_array($r) ? ($r['slug'] ?? $r['id'] ?? 'unknown') : $r)->toArray(),
        ]);
    }

    // ── Direct Permission Assignment ─────────────────────────────────

    public function givePermissionTo(Model $model, string|Permission $permission, ?Team $team = null, ?string $guard = null, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $expiresAt = null): void
    {
        $permission = $this->resolvePermission($permission);
        $guard ??= 'web';
        $teamId = $team?->id;
        $actorId = auth()->id();

        DB::table('model_permissions')->insert([
            'id'           => \Illuminate\Support\Str::uuid()->toString(),
            'team_id'      => $teamId,
            'permission_id'=> $permission->id,
            'model_type'   => $model->getMorphClass(),
            'model_id'     => $model->getKey(),
            'guard_name'   => $guard,
            'starts_at'    => $startsAt,
            'expires_at'   => $expiresAt,
            'assigned_by'  => $actorId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->bustModelCache($model);
        $this->audit->log('permission.given', 'permission', $permission->id, [], [
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'team_id' => $teamId,
            'guard_name' => $guard,
        ]);
    }

    public function revokePermissionTo(Model $model, string|Permission $permission, ?Team $team = null): void
    {
        $permission = $this->resolvePermission($permission);
        $teamId = $team?->id;

        $query = DB::table('model_permissions')
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('permission_id', $permission->id);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $query->delete();

        $this->bustModelCache($model);
        $this->audit->log('permission.revoked', 'permission', $permission->id, [], [
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'team_id' => $teamId,
        ]);
    }

    public function syncPermissions(Model $model, array $permissions, ?Team $team = null): void
    {
        $teamId = $team?->id;
        $morphType = $model->getMorphClass();
        $modelId = $model->getKey();

        DB::transaction(function () use ($model, $morphType, $modelId, $permissions, $teamId) {
            $query = DB::table('model_permissions')
                ->where('model_type', $morphType)
                ->where('model_id', $modelId);

            if ($teamId) {
                $query->where('team_id', $teamId);
            }

            $query->delete();

            $inserts = [];
            foreach ($permissions as $permData) {
                $permission = $this->resolvePermission($permData['name'] ?? $permData['id'] ?? $permData);
                $inserts[] = [
                    'id'            => \Illuminate\Support\Str::uuid()->toString(),
                    'team_id'       => $teamId,
                    'permission_id' => $permission->id,
                    'model_type'    => $morphType,
                    'model_id'      => $modelId,
                    'guard_name'    => $permData['guard_name'] ?? 'web',
                    'starts_at'     => $permData['starts_at'] ?? null,
                    'expires_at'    => $permData['expires_at'] ?? null,
                    'assigned_by'   => auth()->id(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            if (! empty($inserts)) {
                DB::table('model_permissions')->insert($inserts);
            }
        });

        $this->bustModelCache($model);
        $this->audit->log('permissions.synced', 'model', $model->getKey(), [], [
            'model_type' => $morphType,
            'permissions' => collect($permissions)->map(fn ($p) => is_array($p) ? ($p['name'] ?? $p['id'] ?? 'unknown') : $p)->toArray(),
        ]);
    }

    // ── Permission Resolution ────────────────────────────────────────

    public function getPermissions(Model $model, ?Team $team = null, ?string $guard = null): Collection
    {
        $guard ??= 'web';
        $cacheKey = "user:{$model->getKey()}:permissions";

        if ($team) {
            $cacheKey .= ":team:{$team->id}";
        }

        return $this->cache->remember($cacheKey, function () use ($model, $team, $guard) {
            return $this->resolvePermissions($model, $team, $guard);
        });
    }

    public function getDirectPermissions(Model $model, ?Team $team = null, ?string $guard = null): Collection
    {
        $guard ??= 'web';
        $teamId = $team?->id;

        $query = DB::table('model_permissions')
            ->where('model_type', $model->getMorphClass())
            ->where('model_id', $model->getKey())
            ->where('model_permissions.guard_name', $guard)
            ->where(function ($q) {
                $now = now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = now();
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        return $query->join('permissions', 'permissions.id', '=', 'model_permissions.permission_id')
            ->pluck('permissions.name');
    }

    public function getRoles(Model $model, ?Team $team = null, ?string $guard = null): Collection
    {
        $guard ??= 'web';
        $cacheKey = "user:{$model->getKey()}:roles";

        if ($team) {
            $cacheKey .= ":team:{$team->id}";
        }

        return $this->cache->remember($cacheKey, function () use ($model, $team, $guard) {
            return $this->resolveRoles($model, $team, $guard);
        });
    }

    protected function resolvePermissions(Model $model, ?Team $team, string $guard): Collection
    {
        $morphType = $model->getMorphClass();
        $modelId = $model->getKey();
        $teamId = $team?->id;

        // 1. Direct permissions
        $directQuery = DB::table('model_permissions')
            ->where('model_type', $morphType)
            ->where('model_id', $modelId)
            ->where('guard_name', $guard)
            ->where(function ($q) {
                $now = now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = now();
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        if ($teamId) {
            $directQuery->where('team_id', $teamId);
        }

        $directPermissions = $directQuery->pluck('permission_id');

        // 2. Role-based permissions (with hierarchy resolution)
        $roleIdsQuery = DB::table('model_roles')
            ->where('model_type', $morphType)
            ->where('model_id', $modelId)
            ->where('guard_name', $guard)
            ->where(function ($q) {
                $now = now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = now();
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        if ($teamId) {
            $roleIdsQuery->where('team_id', $teamId);
        }

        $roleIds = $roleIdsQuery->pluck('role_id')->unique()->values()->toArray();

        // Resolve hierarchy: get all ancestor role IDs
        $allRoleIds = $roleIds;
        if (! empty($roleIds)) {
            $ancestorIds = DB::table('role_hierarchy')
                ->whereIn('descendant_id', $roleIds)
                ->where('depth', '>', 0)
                ->pluck('ancestor_id')
                ->unique()
                ->values()
                ->toArray();

            $allRoleIds = array_merge($roleIds, $ancestorIds);
        }

        $rolePermissions = empty($allRoleIds)
            ? collect()
            : DB::table('role_permission')
                ->whereIn('role_id', $allRoleIds)
                ->pluck('permission_id');

        // 3. Merge all permission IDs
        $allPermissionIds = $directPermissions->merge($rolePermissions)->unique()->values();

        // 4. Wildcard expansion
        if (config('rbac.wildcards.enabled', true)) {
            $wildcardIds = $this->resolveWildcardPermissionIds($morphType, $modelId, $teamId, $guard);
            $allPermissionIds = $allPermissionIds->merge($wildcardIds)->unique()->values();
        }

        if ($allPermissionIds->isEmpty()) {
            return collect();
        }

        return Permission::whereIn('id', $allPermissionIds)->pluck('name');
    }

    /**
     * Resolve permissions that are covered by wildcard permissions assigned to the user.
     * Supports two strategies: "group" and "pattern".
     */
    protected function resolveWildcardPermissionIds(string $morphType, string $modelId, ?string $teamId, string $guard): Collection
    {
        $strategy = config('rbac.wildcards.strategy', 'group');

        // Find wildcard permission IDs directly assigned to this user
        $wildcardPermQuery = DB::table('model_permissions')
            ->where('model_type', $morphType)
            ->where('model_id', $modelId)
            ->where('guard_name', $guard)
            ->where(function ($q) {
                $now = now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = now();
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        if ($teamId) {
            $wildcardPermQuery->where('team_id', $teamId);
        }

        $wildcardPermIds = $wildcardPermQuery->pluck('permission_id')->toArray();

        if (empty($wildcardPermIds)) {
            return collect();
        }

        // Load wildcard permission definitions
        $wildcardPerms = DB::table('permissions')
            ->whereIn('id', $wildcardPermIds)
            ->where('name', 'LIKE', '%*%')
            ->get();

        if ($wildcardPerms->isEmpty()) {
            return collect();
        }

        $matchedIds = collect();

        foreach ($wildcardPerms as $wildcard) {
            if (config('rbac.cache.enabled') && $strategy === 'group' && $wildcard->group) {
                // Group strategy: match all permissions in the same group
                $groupIds = DB::table('permissions')
                    ->where('group', $wildcard->group)
                    ->where('guard_name', $guard)
                    ->pluck('id');
                $matchedIds = $matchedIds->merge($groupIds);
            } elseif ($strategy === 'pattern') {
                // Pattern strategy: use LIKE matching on the name
                $likePattern = str_replace('*', '%', $wildcard->name);
                $patternIds = DB::table('permissions')
                    ->where('guard_name', $guard)
                    ->where('name', 'LIKE', $likePattern)
                    ->pluck('id');
                $matchedIds = $matchedIds->merge($patternIds);
            }
        }

        return $matchedIds->unique()->values();
    }


    protected function resolveRoles(Model $model, ?Team $team, string $guard): Collection
    {
        $morphType = $model->getMorphClass();
        $modelId = $model->getKey();
        $teamId = $team?->id;

        $query = DB::table('model_roles')
            ->where('model_type', $morphType)
            ->where('model_id', $modelId)
            ->where('guard_name', $guard)
            ->where(function ($q) {
                $now = now();
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) {
                $now = now();
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $roleIds = $query->pluck('role_id')->unique()->values()->toArray();

        if (empty($roleIds)) {
            return collect();
        }

        return Role::whereIn('id', $roleIds)->get();
    }

    // ── Cache Busting ────────────────────────────────────────────────

    protected function bustModelCache(Model $model): void
    {
        $this->cache->forget("user:{$model->getKey()}:permissions");
        $this->cache->forget("user:{$model->getKey()}:roles");
        $this->cache->forgetByPattern("user:{$model->getKey()}:permissions:team:*");
        $this->cache->forgetByPattern("user:{$model->getKey()}:roles:team:*");
    }

    // ── Helpers ──────────────────────────────────────────────────────

    protected function resolveRole(string|Role $role, ?string $guard = null): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        $found = $this->roleService->findBySlug($role, $guard);

        if (! $found) {
            $found = $this->roleService->findById($role);
        }

        if (! $found) {
            throw new \Metrial\RBAC\Exceptions\RoleNotFoundException("Role [{$role}] not found.");
        }

        return $found;
    }

    protected function resolvePermission(string|Permission $permission): Permission
    {
        if ($permission instanceof Permission) {
            return $permission;
        }

        $found = app(PermissionService::class)->findByName($permission);

        if (! $found) {
            $found = app(PermissionService::class)->findById($permission);
        }

        if (! $found) {
            throw new \InvalidArgumentException("Permission [{$permission}] not found.");
        }

        return $found;
    }
}
