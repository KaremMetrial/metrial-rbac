<?php

namespace Metrial\RBAC\Tests\Feature;

use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Team;
use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class CheckPermissionTest extends TestCase
{
    use CreatesRbacData;

    public function test_exact_permission_match(): void
    {
        $user = $this->createUser();
        $perm = $this->createPermission(['name' => 'exact-match']);
        $this->givePermissionToUser($user, $perm);

        $this->assertTrue($user->hasPermissionTo('exact-match'));
    }

    public function test_wildcard_permission_group_strategy(): void
    {
        // Create permissions in the 'articles' group so the wildcard matches them
        $this->createPermission(['name' => 'articles.create', 'group' => 'articles']);
        $this->createPermission(['name' => 'articles.edit', 'group' => 'articles']);
        $this->createPermission(['name' => 'articles.delete', 'group' => 'articles']);

        $user = $this->createUser();
        $wildcard = $this->createPermission(['name' => 'articles.*', 'group' => 'articles']);
        $this->givePermissionToUser($user, $wildcard);

        $this->assertTrue($user->hasPermissionTo('articles.create'));
        $this->assertTrue($user->hasPermissionTo('articles.edit'));
        $this->assertTrue($user->hasPermissionTo('articles.delete'));
    }

    public function test_wildcard_permission_pattern_strategy(): void
    {
        $this->app['config']->set('rbac.wildcards.strategy', 'pattern');

        // Create permissions that match the pattern
        $this->createPermission(['name' => 'mod.ban-users', 'group' => 'moderation']);
        $this->createPermission(['name' => 'mod.delete-post', 'group' => 'moderation']);

        $user = $this->createUser();
        $wildcard = $this->createPermission(['name' => 'mod.*', 'group' => 'moderation']);
        $this->givePermissionToUser($user, $wildcard);

        $this->assertTrue($user->hasPermissionTo('mod.ban-users'));
        $this->assertTrue($user->hasPermissionTo('mod.delete-post'));
    }

    public function test_wildcards_disabled_by_config(): void
    {
        $this->app['config']->set('rbac.wildcards.enabled', false);

        $user = $this->createUser();
        $wildcard = $this->createPermission(['name' => 'disabled.*', 'group' => 'disabled']);
        $this->givePermissionToUser($user, $wildcard);

        $this->assertFalse($user->hasPermissionTo('disabled.anything'));
    }

    public function test_role_inheritance_permission_check(): void
    {
        $user = $this->createUser();
        $parent = $this->createRole(['name' => 'Parent', 'slug' => 'parent-feat']);
        $child = $this->createRole(['name' => 'Child', 'slug' => 'child-feat']);
        $perm = $this->createPermission(['name' => 'inherited-check']);

        \Metrial\RBAC\Facades\Rbac::role()->assignPermission($parent, $perm->id);
        \Metrial\RBAC\Facades\Rbac::role()->setParent($child, $parent);
        $this->assignRoleToUser($user, $child);

        $this->assertTrue($user->hasPermissionTo('inherited-check'));
    }

    public function test_team_scoped_permission_check(): void
    {
        $user = $this->createUser();
        $team = $this->createTeam(['name' => 'Scoped', 'slug' => 'scoped-feat']);
        $perm = $this->createPermission(['name' => 'team-scoped-feat']);

        $this->givePermissionToUser($user, $perm, $team);

        $this->assertTrue($user->hasPermissionTo('team-scoped-feat', $team));
        $this->assertFalse($user->hasPermissionTo('team-scoped-feat', $this->createTeam(['name' => 'Other', 'slug' => 'other-feat'])));
    }

    public function test_direct_vs_inherited_permission(): void
    {
        $user = $this->createUser();
        $perm = $this->createPermission(['name' => 'direct-vs-inherited']);

        $this->givePermissionToUser($user, $perm);

        $this->assertTrue($user->hasDirectPermission('direct-vs-inherited'));
        $this->assertTrue($user->hasPermissionTo('direct-vs-inherited'));
    }
}
