<?php

namespace Metrial\RBAC\Services;

use Illuminate\Support\Collection;
use Metrial\RBAC\Models\Permission;

class PermissionService
{
    public function __construct(
        protected CacheService $cache,
        protected AuditService $audit,
    ) {}

    public function create(array $data): Permission
    {
        /** @var Permission $permission */
        $permission = Permission::create($data);

        $this->audit->log('permission.created', 'permission', $permission->id, [], $data);

        return $permission;
    }

    public function findByName(string $name, ?string $guard = null): ?Permission
    {
        $query = Permission::where('name', $name);

        if ($guard) {
            $query->where('guard_name', $guard);
        }

        return $query->first();
    }

    public function findById(string $id): ?Permission
    {
        return Permission::find($id);
    }

    public function getAllPermissions(?string $guard = null): Collection
    {
        $query = Permission::query();

        if ($guard) {
            $query->where('guard_name', $guard);
        }

        return $query->get();
    }

    public function getAllGrouped(?string $guard = null): Collection
    {
        return $this->getAllPermissions($guard)->groupBy('group');
    }

    public function getAllPermissionNames(?string $guard = null): Collection
    {
        return $this->cache->remember("permissions:all:{$guard}", function () use ($guard) {
            $query = Permission::query();

            if ($guard) {
                $query->where('guard_name', $guard);
            }

            return $query->pluck('name');
        });
    }
}
