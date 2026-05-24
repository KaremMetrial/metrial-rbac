<?php

namespace Metrial\RBAC\Tests\Unit;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Models\Team;
use Metrial\RBAC\Services\AssignmentService;
use Metrial\RBAC\Services\RoleService;
use Metrial\RBAC\Services\PermissionService;
use Metrial\RBAC\Tests\TestCase;

class AssignmentServiceTest extends TestCase
{
    protected AssignmentService $assignmentService;
    protected RoleService $roleService;
    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assignmentService = $this->app->make(AssignmentService::class);
        $this->roleService = $this->app->make(RoleService::class);
        $this->permissionService = $this->app->make(PermissionService::class);
    }

    protected function createUser(): User
    {
        // Create users table for testing
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        $user = new User();
        $user->id = \Illuminate\Support\Str::uuid()->toString();
        $user->name = 'Test User';
        $user->email = 'test.' . uniqid() . '@example.com';
        $user->password = bcrypt('password');
        $user->save();

        return $user;
    }

    public function test_it_can_assign_role_to_user(): void
    {
        $user = $this->createUser();
        $role = $this->roleService->create(['name' => 'Editor', 'slug' => 'editor']);

        $this->assignmentService->assignRole($user, $role);

        $this->assertDatabaseHas('model_roles', [
            'model_type' => User::class,
            'model_id' => $user->getKey(),
            'role_id' => $role->id,
        ]);
    }

    public function test_it_can_remove_role_from_user(): void
    {
        $user = $this->createUser();
        $role = $this->roleService->create(['name' => 'Reviewer', 'slug' => 'reviewer']);

        $this->assignmentService->assignRole($user, $role);
        $this->assignmentService->removeRole($user, $role);

        $this->assertDatabaseMissing('model_roles', [
            'model_id' => $user->getKey(),
            'role_id' => $role->id,
        ]);
    }

    public function test_it_can_sync_roles(): void
    {
        $user = $this->createUser();
        $roleA = $this->roleService->create(['name' => 'Role A', 'slug' => 'role-a']);
        $roleB = $this->roleService->create(['name' => 'Role B', 'slug' => 'role-b']);

        $this->assignmentService->assignRole($user, $roleA);
        $this->assignmentService->syncRoles($user, [['slug' => 'role-b']]);

        $this->assertDatabaseMissing('model_roles', [
            'model_id' => $user->getKey(),
            'role_id' => $roleA->id,
        ]);
        $this->assertDatabaseHas('model_roles', [
            'model_id' => $user->getKey(),
            'role_id' => $roleB->id,
        ]);
    }

    public function test_it_can_give_direct_permission(): void
    {
        $user = $this->createUser();
        $perm = $this->permissionService->create(['name' => 'delete-posts', 'guard_name' => 'web']);

        $this->assignmentService->givePermissionTo($user, $perm);

        $this->assertDatabaseHas('model_permissions', [
            'model_type' => User::class,
            'model_id' => $user->getKey(),
            'permission_id' => $perm->id,
        ]);
    }

    public function test_it_can_revoke_direct_permission(): void
    {
        $user = $this->createUser();
        $perm = $this->permissionService->create(['name' => 'publish-posts', 'guard_name' => 'web']);

        $this->assignmentService->givePermissionTo($user, $perm);
        $this->assignmentService->revokePermissionTo($user, $perm);

        $this->assertDatabaseMissing('model_permissions', [
            'model_id' => $user->getKey(),
            'permission_id' => $perm->id,
        ]);
    }

    public function test_it_does_not_return_expired_roles(): void
    {
        $user = $this->createUser();
        $role = $this->roleService->create(['name' => 'Expired Role', 'slug' => 'expired-role']);

        $this->assignmentService->assignRole($user, $role, null, 'web', null, now()->subDay());

        $roles = $this->assignmentService->getRoles($user);
        $this->assertFalse($roles->pluck('id')->contains($role->id));
    }

    public function test_it_does_not_return_future_dated_roles(): void
    {
        $user = $this->createUser();
        $role = $this->roleService->create(['name' => 'Future Role', 'slug' => 'future-role']);

        $this->assignmentService->assignRole($user, $role, null, 'web', now()->addDay(), null);

        $roles = $this->assignmentService->getRoles($user);
        $this->assertFalse($roles->pluck('id')->contains($role->id));
    }

    public function test_it_resolves_permissions_from_role(): void
    {
        $user = $this->createUser();
        $role = $this->roleService->create(['name' => 'Power User', 'slug' => 'power-user']);
        $perm = $this->permissionService->create(['name' => 'do-something', 'guard_name' => 'web']);

        $this->roleService->assignPermission($role, $perm->id);
        $this->assignmentService->assignRole($user, $role);

        $perms = $this->assignmentService->getPermissions($user);
        $this->assertTrue($perms->contains('do-something'));
    }
}
