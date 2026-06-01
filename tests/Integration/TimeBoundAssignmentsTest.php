<?php

namespace Metrial\RBAC\Tests\Integration;

use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class TimeBoundAssignmentsTest extends TestCase
{
    use CreatesRbacData;

    public function test_expired_role_is_not_resolved(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Expired', 'slug' => 'expired-role-tb']);
        $perm = $this->createPermission(['name' => 'expired-role-perm']);

        \Metrial\RBAC\Facades\Rbac::role()->assignPermission($role, $perm->id);
        $this->assignRoleToUser($user, $role, null, 'web', null, now()->subDay());

        $this->assertFalse($user->hasPermissionTo('expired-role-perm'));
        $this->assertFalse($user->hasRole('expired-role-tb'));
    }

    public function test_future_dated_role_is_not_resolved(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Future', 'slug' => 'future-role-tb']);
        $perm = $this->createPermission(['name' => 'future-role-perm']);

        \Metrial\RBAC\Facades\Rbac::role()->assignPermission($role, $perm->id);
        $this->assignRoleToUser($user, $role, null, 'web', now()->addDay(), null);

        $this->assertFalse($user->hasPermissionTo('future-role-perm'));
        $this->assertFalse($user->hasRole('future-role-tb'));
    }

    public function test_active_time_bound_role_works(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Active Temp', 'slug' => 'active-temp-tb']);
        $perm = $this->createPermission(['name' => 'active-temp-perm']);

        \Metrial\RBAC\Facades\Rbac::role()->assignPermission($role, $perm->id);
        $this->assignRoleToUser($user, $role, null, 'web', now()->subHour(), now()->addHour());

        $this->assertTrue($user->hasPermissionTo('active-temp-perm'));
        $this->assertTrue($user->hasRole('active-temp-tb'));
    }

    public function test_expired_permission_is_not_resolved(): void
    {
        $user = $this->createUser();
        $perm = $this->createPermission(['name' => 'expired-direct-perm']);

        $this->givePermissionToUser($user, $perm, null, 'web', null, now()->subDay());

        $this->assertFalse($user->hasPermissionTo('expired-direct-perm'));
        $this->assertFalse($user->hasDirectPermission('expired-direct-perm'));
    }

    public function test_prune_expired_command_deletes_expired_rows(): void
    {
        $user = $this->createUser();
        $role1 = $this->createRole(['name' => 'Prune 1', 'slug' => 'prune-1']);
        $role2 = $this->createRole(['name' => 'Prune 2', 'slug' => 'prune-2']);

        $this->assignRoleToUser($user, $role1, null, 'web', null, now()->subDay());
        $this->assignRoleToUser($user, $role2, null, 'web', null, now()->addDay());

        $this->artisan('rbac:prune-expired');

        $this->assertDatabaseMissing('model_roles', ['role_id' => $role1->id]);
        $this->assertDatabaseHas('model_roles', ['role_id' => $role2->id]);
    }

    public function test_only_null_constraints_are_resolved_without_dates(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'No Dates', 'slug' => 'no-dates-tb']);
        $perm = $this->createPermission(['name' => 'no-dates-perm']);

        \Metrial\RBAC\Facades\Rbac::role()->assignPermission($role, $perm->id);
        $this->assignRoleToUser($user, $role);

        $this->assertTrue($user->hasPermissionTo('no-dates-perm'));
        $this->assertTrue($user->hasRole('no-dates-tb'));
    }
}
