<?php

namespace Metrial\RBAC\Tests\Unit;

use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Services\RoleService;
use Metrial\RBAC\Services\PermissionService;
use Metrial\RBAC\Services\CacheService;
use Metrial\RBAC\Services\AuditService;
use Metrial\RBAC\Tests\TestCase;

class RoleServiceTest extends TestCase
{
    protected RoleService $roleService;
    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleService = $this->app->make(RoleService::class);
        $this->permissionService = $this->app->make(PermissionService::class);
    }

    public function test_it_can_create_a_role(): void
    {
        $role = $this->roleService->create([
            'name' => 'Test Role',
            'slug' => 'test-role',
            'guard_name' => 'web',
        ]);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('Test Role', $role->name);
        $this->assertEquals('test-role', $role->slug);
        $this->assertDatabaseHas('roles', ['slug' => 'test-role']);
    }

    public function test_it_can_find_role_by_slug(): void
    {
        $this->roleService->create([
            'name' => 'Findable Role',
            'slug' => 'findable-role',
        ]);

        $found = $this->roleService->findBySlug('findable-role');
        $this->assertNotNull($found);
        $this->assertEquals('Findable Role', $found->name);
    }

    public function test_it_returns_null_for_missing_slug(): void
    {
        $found = $this->roleService->findBySlug('nonexistent');
        $this->assertNull($found);
    }

    public function test_it_can_assign_permission_to_role(): void
    {
        $role = $this->roleService->create([
            'name' => 'Role With Perm',
            'slug' => 'role-with-perm',
        ]);

        $permission = $this->permissionService->create([
            'name' => 'test.perm',
            'guard_name' => 'web',
        ]);

        $this->roleService->assignPermission($role, $permission->id);

        $this->assertDatabaseHas('role_permission', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_it_can_get_all_roles(): void
    {
        $this->roleService->create(['name' => 'Role A', 'slug' => 'role-a']);
        $this->roleService->create(['name' => 'Role B', 'slug' => 'role-b']);

        $roles = $this->roleService->getAllRoles();
        $this->assertGreaterThanOrEqual(2, $roles->count());
    }

    public function test_it_can_get_roles_by_guard(): void
    {
        $this->roleService->create(['name' => 'Web Role', 'slug' => 'web-role', 'guard_name' => 'web']);
        $this->roleService->create(['name' => 'Api Role', 'slug' => 'api-role', 'guard_name' => 'api']);

        $webRoles = $this->roleService->getAllRoles('web');
        $this->assertCount(1, $webRoles);
        $this->assertEquals('Web Role', $webRoles->first()->name);
    }
}
