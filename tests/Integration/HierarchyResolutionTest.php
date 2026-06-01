<?php

namespace Metrial\RBAC\Tests\Integration;

use Metrial\RBAC\Exceptions\RoleCycleException;
use Metrial\RBAC\Facades\Rbac;
use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class HierarchyResolutionTest extends TestCase
{
    use CreatesRbacData;

    public function test_direct_parent_inherits_permissions(): void
    {
        $user = $this->createUser();
        $admin = $this->createRole(['name' => 'Admin', 'slug' => 'admin-hier']);
        $superAdmin = $this->createRole(['name' => 'Super Admin', 'slug' => 'super-admin-hier']);
        $perm = $this->createPermission(['name' => 'admin-only-perm', 'group' => 'admin']);

        Rbac::role()->assignPermission($admin, $perm->id);
        Rbac::role()->setParent($superAdmin, $admin);
        $this->assignRoleToUser($user, $superAdmin);

        $this->assertTrue($user->hasPermissionTo('admin-only-perm'));
    }

    public function test_grandparent_inherits_permissions(): void
    {
        $user = $this->createUser();
        $level1 = $this->createRole(['name' => 'Level 1', 'slug' => 'level-1-hier']);
        $level2 = $this->createRole(['name' => 'Level 2', 'slug' => 'level-2-hier']);
        $level3 = $this->createRole(['name' => 'Level 3', 'slug' => 'level-3-hier']);
        $perm = $this->createPermission(['name' => 'deep-perm', 'group' => 'test']);

        Rbac::role()->assignPermission($level1, $perm->id);
        Rbac::role()->setParent($level2, $level1);
        Rbac::role()->setParent($level3, $level2);
        $this->assignRoleToUser($user, $level3);

        $this->assertTrue($user->hasPermissionTo('deep-perm'));
    }

    public function test_cycle_detection_throws_exception(): void
    {
        $this->expectException(RoleCycleException::class);

        $roleA = $this->createRole(['name' => 'Role A', 'slug' => 'role-a-hier']);
        $roleB = $this->createRole(['name' => 'Role B', 'slug' => 'role-b-hier']);

        Rbac::role()->setParent($roleB, $roleA);
        Rbac::role()->setParent($roleA, $roleB); // This creates a cycle
    }

    public function test_get_child_roles_returns_descendants(): void
    {
        $parent = $this->createRole(['name' => 'Parent', 'slug' => 'parent-hier']);
        $child = $this->createRole(['name' => 'Child', 'slug' => 'child-hier']);
        $grandchild = $this->createRole(['name' => 'Grandchild', 'slug' => 'grandchild-hier']);

        Rbac::role()->setParent($child, $parent);
        Rbac::role()->setParent($grandchild, $child);

        $descendants = Rbac::role()->getChildRoles($parent->id);
        $descendantIds = $descendants->pluck('id');

        $this->assertTrue($descendantIds->contains($child->id));
        $this->assertTrue($descendantIds->contains($grandchild->id));
    }

    public function test_get_parent_roles_returns_ancestors(): void
    {
        $parent = $this->createRole(['name' => 'Parent', 'slug' => 'parent-hier-2']);
        $child = $this->createRole(['name' => 'Child', 'slug' => 'child-hier-2']);

        Rbac::role()->setParent($child, $parent);

        $ancestors = Rbac::role()->getParentRoles($child->id);
        $ancestorIds = $ancestors->pluck('id');

        $this->assertTrue($ancestorIds->contains($parent->id));
    }
}
