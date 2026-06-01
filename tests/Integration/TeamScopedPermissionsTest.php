<?php

namespace Metrial\RBAC\Tests\Integration;

use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class TeamScopedPermissionsTest extends TestCase
{
    use CreatesRbacData;

    public function test_user_has_permission_in_assigned_team_only(): void
    {
        $user = $this->createUser();
        $teamA = $this->createTeam(['name' => 'Team A', 'slug' => 'team-a-scope']);
        $teamB = $this->createTeam(['name' => 'Team B', 'slug' => 'team-b-scope']);
        $perm = $this->createPermission(['name' => 'scoped-perm', 'group' => 'test']);

        $this->givePermissionToUser($user, $perm, $teamA);

        $this->assertTrue($user->hasPermissionTo('scoped-perm', $teamA));
        $this->assertFalse($user->hasPermissionTo('scoped-perm', $teamB));
    }

    public function test_switch_team_changes_permission_context(): void
    {
        $user = $this->createUser();
        $teamA = $this->createTeam(['name' => 'Team A', 'slug' => 'team-a-switch']);
        $teamB = $this->createTeam(['name' => 'Team B', 'slug' => 'team-b-switch']);
        $permA = $this->createPermission(['name' => 'perm-team-a', 'group' => 'test']);
        $permB = $this->createPermission(['name' => 'perm-team-b', 'group' => 'test']);

        $this->givePermissionToUser($user, $permA, $teamA);
        $this->givePermissionToUser($user, $permB, $teamB);

        $user->switchTeam($teamA);
        $this->assertTrue($user->hasPermissionTo('perm-team-a'));
        $this->assertFalse($user->hasPermissionTo('perm-team-b'));

        $user->switchTeam($teamB);
        $this->assertFalse($user->hasPermissionTo('perm-team-a'));
        $this->assertTrue($user->hasPermissionTo('perm-team-b'));
    }

    public function test_roles_are_team_scoped(): void
    {
        $user = $this->createUser();
        $teamA = $this->createTeam(['name' => 'Team A', 'slug' => 'team-a-role-scope']);
        $teamB = $this->createTeam(['name' => 'Team B', 'slug' => 'team-b-role-scope']);
        $roleA = $this->createRole(['name' => 'Role Team A', 'slug' => 'role-team-a']);
        $roleB = $this->createRole(['name' => 'Role Team B', 'slug' => 'role-team-b']);

        $this->assignRoleToUser($user, $roleA, $teamA);
        $this->assignRoleToUser($user, $roleB, $teamB);

        $this->assertTrue($user->hasRole('role-team-a', $teamA));
        $this->assertTrue($user->hasRole('role-team-b', $teamB));
    }

    public function test_active_team_id_returns_correct_value(): void
    {
        $user = $this->createUser();
        $team = $this->createTeam(['name' => 'Active', 'slug' => 'active-team']);

        $this->assertNull($user->getActiveTeamId());

        $user->switchTeam($team);

        $this->assertEquals($team->id, $user->getActiveTeamId());
    }

    public function test_user_can_be_member_of_multiple_teams(): void
    {
        $user = $this->createUser();
        $teamA = $this->createTeam(['name' => 'Team A', 'slug' => 'team-a-multi']);
        $teamB = $this->createTeam(['name' => 'Team B', 'slug' => 'team-b-multi']);

        $user->addToTeam($teamA);
        $user->addToTeam($teamB);

        $this->assertTrue($user->isMemberOf($teamA));
        $this->assertTrue($user->isMemberOf($teamB));
    }

    public function test_owner_flag_is_tracked_per_team(): void
    {
        $user = $this->createUser();
        $team = $this->createTeam(['name' => 'Owned', 'slug' => 'owned-team']);

        $user->addToTeam($team, asOwner: true);

        $this->assertTrue($user->isOwnerOf($team));

        $user->removeFromTeam($team);

        $this->assertFalse($user->isMemberOf($team));
    }
}
