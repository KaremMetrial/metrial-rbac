<?php

namespace Metrial\RBAC\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Exceptions\RoleNotFoundException;
use Metrial\RBAC\Exceptions\RoleCycleException;

class RoleService
{
    public function __construct(
        protected CacheService $cache,
        protected AuditService $audit,
    ) {}

    public function create(array $data): Role
    {
        /** @var Role $role */
        $role = Role::create($data);

        $this->audit->log('role.created', 'role', $role->id, [], $data);

        return $role;
    }

    public function findBySlug(string $slug, ?string $guard = null): ?Role
    {
        $query = Role::where('slug', $slug);

        if ($guard) {
            $query->where('guard_name', $guard);
        }

        return $query->first();
    }

    public function findById(string $id): ?Role
    {
        return Role::find($id);
    }

    public function getAllRoles(?string $guard = null): Collection
    {
        $query = Role::query();

        if ($guard) {
            $query->where('guard_name', $guard);
        }

        return $query->get();
    }

    public function assignPermission(Role $role, string $permissionId): void
    {
        DB::table('role_permission')->upsert(
            [['id' => \Illuminate\Support\Str::uuid()->toString(), 'role_id' => $role->id, 'permission_id' => $permissionId, 'created_at' => now(), 'updated_at' => now()]],
            ['role_id', 'permission_id'],
            ['updated_at']
        );

        $this->cache->forgetByPattern("role:{$role->id}:permissions");
        $this->cache->flush();

        $this->audit->log('role.permission.attached', 'role', $role->id, [], ['permission_id' => $permissionId]);
    }

    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);

        $this->cache->forgetByPattern("role:{$role->id}:permissions");
        $this->cache->flush();

        $this->audit->log('role.permissions.synced', 'role', $role->id, [], ['permission_ids' => $permissionIds]);
    }

    public function getChildRoles(string $roleId): Collection
    {
        return DB::table('role_hierarchy')
            ->where('ancestor_id', $roleId)
            ->where('depth', '>', 0)
            ->join('roles', 'roles.id', '=', 'role_hierarchy.descendant_id')
            ->select('roles.*', 'role_hierarchy.depth')
            ->get();
    }

    public function getParentRoles(string $roleId): Collection
    {
        return DB::table('role_hierarchy')
            ->where('descendant_id', $roleId)
            ->where('depth', '>', 0)
            ->join('roles', 'roles.id', '=', 'role_hierarchy.ancestor_id')
            ->select('roles.*', 'role_hierarchy.depth')
            ->get();
    }

    public function setParent(Role $child, Role $parent): void
    {
        if ($this->wouldCreateCycle($child->id, $parent->id)) {
            throw new RoleCycleException("Setting role [{$parent->slug}] as parent of [{$child->slug}] would create a cycle.");
        }

        DB::transaction(function () use ($child, $parent) {
            // Remove existing hierarchy entries for this child's subtree
            DB::table('role_hierarchy')
                ->where('descendant_id', $child->id)
                ->where('ancestor_id', '!=', $child->id)
                ->delete();

            // Direct parent-child
            DB::table('role_hierarchy')->upsert(
                [['id' => \Illuminate\Support\Str::uuid()->toString(), 'ancestor_id' => $parent->id, 'descendant_id' => $child->id, 'depth' => 1, 'created_at' => now(), 'updated_at' => now()]],
                ['ancestor_id', 'descendant_id'],
                ['depth', 'updated_at']
            );

            // Also add transitive relationships: all ancestors of parent -> child
            $ancestors = DB::table('role_hierarchy')
                ->where('descendant_id', $parent->id)
                ->get();

            foreach ($ancestors as $ancestor) {
                DB::table('role_hierarchy')->upsert(
                    [['id' => \Illuminate\Support\Str::uuid()->toString(), 'ancestor_id' => $ancestor->ancestor_id, 'descendant_id' => $child->id, 'depth' => $ancestor->depth + 1, 'created_at' => now(), 'updated_at' => now()]],
                    ['ancestor_id', 'descendant_id'],
                    ['depth', 'updated_at']
                );
            }

            // All descendants of child also get new ancestors from parent's subtree
            $descendants = DB::table('role_hierarchy')
                ->where('ancestor_id', $child->id)
                ->where('descendant_id', '!=', $child->id)
                ->get();

            foreach ($descendants as $desc) {
                // parent -> descendant
                DB::table('role_hierarchy')->upsert(
                    [['id' => \Illuminate\Support\Str::uuid()->toString(), 'ancestor_id' => $parent->id, 'descendant_id' => $desc->descendant_id, 'depth' => $desc->depth + 1, 'created_at' => now(), 'updated_at' => now()]],
                    ['ancestor_id', 'descendant_id'],
                    ['depth', 'updated_at']
                );

                // ancestors of parent -> descendant
                foreach ($ancestors as $ancestor) {
                    DB::table('role_hierarchy')->upsert(
                        [['id' => \Illuminate\Support\Str::uuid()->toString(), 'ancestor_id' => $ancestor->ancestor_id, 'descendant_id' => $desc->descendant_id, 'depth' => $ancestor->depth + $desc->depth + 1, 'created_at' => now(), 'updated_at' => now()]],
                        ['ancestor_id', 'descendant_id'],
                        ['depth', 'updated_at']
                    );
                }
            }

            // Self-references
            DB::table('role_hierarchy')->upsert(
                [['id' => \Illuminate\Support\Str::uuid()->toString(), 'ancestor_id' => $child->id, 'descendant_id' => $child->id, 'depth' => 0, 'created_at' => now(), 'updated_at' => now()]],
                ['ancestor_id', 'descendant_id'],
                ['depth', 'updated_at']
            );
        });

        $this->cache->flush();
        $this->audit->log('role.hierarchy.updated', 'role', $child->id, [], ['parent_id' => $parent->id]);
    }

    protected function wouldCreateCycle(string $childId, string $parentId): bool
    {
        // If the parent is already a descendant of the child, it's a cycle
        return DB::table('role_hierarchy')
            ->where('ancestor_id', $childId)
            ->where('descendant_id', $parentId)
            ->exists();
    }

    public function getCachedPermissions(Role $role): Collection
    {
        return $this->cache->remember("role:{$role->id}:permissions", function () use ($role) {
            $roleIds = $this->getAllDescendantIds($role->id);
            $roleIds[] = $role->id;

            return DB::table('role_permission')
                ->whereIn('role_id', $roleIds)
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->select('permissions.name', 'permissions.guard_name')
                ->get();
        });
    }

    public function getAllDescendantIds(string $roleId): array
    {
        return DB::table('role_hierarchy')
            ->where('ancestor_id', $roleId)
            ->where('depth', '>', 0)
            ->pluck('descendant_id')
            ->toArray();
    }
}
