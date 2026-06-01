<?php

namespace Metrial\RBAC\Tests\Integration;

use Illuminate\Support\Facades\Gate;
use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class GateIntegrationTest extends TestCase
{
    use CreatesRbacData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('rbac.gate_mode', 'auto');
    }

    protected function registerGates(): void
    {
        $this->app->make(\Metrial\RBAC\Gates\RbacGateRegistrar::class)->register();
    }

    public function test_gate_registers_permissions_in_auto_mode(): void
    {
        $perm = $this->createPermission(['name' => 'gate-test-perm']);
        $this->registerGates();

        $this->assertTrue(Gate::has('gate-test-perm'));
    }

    public function test_user_can_via_gate(): void
    {
        $user = $this->createUser();
        $perm = $this->createPermission(['name' => 'can-edit-posts']);
        $this->givePermissionToUser($user, $perm);
        $this->registerGates();

        $this->assertTrue(Gate::forUser($user)->allows('can-edit-posts'));
    }

    public function test_user_cannot_via_gate_without_permission(): void
    {
        $user = $this->createUser();
        $this->registerGates();

        $this->assertFalse(Gate::forUser($user)->allows('nonexistent-perm'));
    }

    public function test_gate_resolves_direct_role_permissions(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Direct', 'slug' => 'direct-gate']);
        $perm = $this->createPermission(['name' => 'direct-gate-perm']);

        \Metrial\RBAC\Facades\Rbac::role()->assignPermission($role, $perm->id);
        $this->assignRoleToUser($user, $role);
        $this->registerGates();

        $this->assertTrue(Gate::forUser($user)->allows('direct-gate-perm'));
    }

    public function test_gate_for_user_with_team_context(): void
    {
        $user = $this->createUser();
        $team = $this->createTeam(['name' => 'Team A', 'slug' => 'team-a-gate']);
        $perm = $this->createPermission(['name' => 'team-gate-perm']);

        $this->givePermissionToUser($user, $perm, $team);
        $user->switchTeam($team);
        $this->registerGates();

        $this->assertTrue(Gate::forUser($user)->allows('team-gate-perm'));
    }

    public function test_gate_check_with_team_argument(): void
    {
        $user = $this->createUser();
        $team = $this->createTeam(['name' => 'Team Arg', 'slug' => 'team-arg-gate']);
        $perm = $this->createPermission(['name' => 'arg-gate-perm']);

        $this->givePermissionToUser($user, $perm, $team);
        $this->registerGates();

        $this->assertTrue(Gate::forUser($user)->allows('arg-gate-perm', [$team]));
    }
}
