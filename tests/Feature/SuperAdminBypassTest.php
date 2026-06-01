<?php

namespace Metrial\RBAC\Tests\Feature;

use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class SuperAdminBypassTest extends TestCase
{
    use CreatesRbacData;

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $this->app['config']->set('rbac.super_admin_role', 'super-admin-test');

        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Super Admin Test', 'slug' => 'super-admin-test']);
        $this->assignRoleToUser($user, $role);

        $this->assertTrue($user->hasPermissionTo('any-permission-at-all'));
        $this->assertTrue($user->hasPermissionTo('completely-fake-perm'));
    }

    public function test_super_admin_bypass_is_logged(): void
    {
        $this->app['config']->set('rbac.super_admin_role', 'super-admin-audit');

        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Super Admin Audit', 'slug' => 'super-admin-audit']);
        $this->assignRoleToUser($user, $role);

        $user->hasPermissionTo('some-bypassed-perm');

        $this->assertDatabaseHas('rbac_audit_log', [
            'action'    => 'superadmin.bypass',
            'entity_id' => 'some-bypassed-perm',
        ]);
    }

    public function test_super_admin_disabled_by_default(): void
    {
        $this->assertNull(config('rbac.super_admin_role'));

        $user = $this->createUser();

        $this->assertFalse($user->hasPermissionTo('unauthorized-perm'));
    }

    public function test_super_admin_bypasses_role_checks(): void
    {
        $this->app['config']->set('rbac.super_admin_role', 'super-admin-roles');

        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Super Admin Roles', 'slug' => 'super-admin-roles']);
        $this->assignRoleToUser($user, $role);

        $this->assertTrue($user->hasRole('nonexistent-role'));
        $this->assertTrue($user->hasAnyRole(['fake-1', 'fake-2']));
        $this->assertTrue($user->hasAllRoles(['fake-1', 'fake-2']));
    }
}
