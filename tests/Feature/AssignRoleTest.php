<?php

namespace Metrial\RBAC\Tests\Feature;

use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Facades\Rbac;
use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class AssignRoleTest extends TestCase
{
    use CreatesRbacData;

    public function test_full_assign_flow(): void
    {
        $user = $this->createUser();
        $role = Rbac::role()->create(['name' => 'Test Flow', 'slug' => 'test-flow']);
        $perm = Rbac::permission()->create(['name' => 'flow-perm', 'guard_name' => 'web']);

        Rbac::role()->assignPermission($role, $perm->id);
        $user->assignRole($role);

        $this->assertTrue($user->hasRole('test-flow'));
        $this->assertTrue($user->hasPermissionTo('flow-perm'));
    }

    public function test_revoke_flow(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'To Revoke', 'slug' => 'to-revoke']);
        $this->assignRoleToUser($user, $role);

        $this->assertTrue($user->hasRole('to-revoke'));

        $user->removeRole('to-revoke');

        $this->assertFalse($user->hasRole('to-revoke'));
    }

    public function test_sync_replaces_all_roles(): void
    {
        $user = $this->createUser();
        $roleA = $this->createRole(['name' => 'Keep', 'slug' => 'keep-role']);
        $roleB = $this->createRole(['name' => 'Add', 'slug' => 'add-role']);

        $this->assignRoleToUser($user, $roleB);
        $user->syncRoles(['keep-role'], null);

        $this->assertTrue($user->hasRole('keep-role'));
        $this->assertFalse($user->hasRole('add-role'));
    }

    public function test_idempotent_role_assignment(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Idempotent', 'slug' => 'idempotent']);

        $this->assignRoleToUser($user, $role);
        $this->assignRoleToUser($user, $role);

        $this->assertTrue($user->hasRole('idempotent'));
    }
}
