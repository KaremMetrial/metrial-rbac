<?php

namespace Metrial\RBAC\Tests\Unit;

use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Services\PermissionService;
use Metrial\RBAC\Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    protected PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(PermissionService::class);
    }

    public function test_it_can_create_a_permission(): void
    {
        $perm = $this->service->create([
            'name' => 'posts.create',
            'guard_name' => 'web',
            'group' => 'posts',
        ]);

        $this->assertInstanceOf(Permission::class, $perm);
        $this->assertEquals('posts.create', $perm->name);
        $this->assertEquals('posts', $perm->group);
        $this->assertDatabaseHas('permissions', ['name' => 'posts.create']);
    }

    public function test_it_can_find_permission_by_name(): void
    {
        $this->service->create(['name' => 'users.view', 'guard_name' => 'web']);

        $found = $this->service->findByName('users.view');
        $this->assertNotNull($found);
        $this->assertEquals('users.view', $found->name);
    }

    public function test_it_returns_null_for_missing_permission(): void
    {
        $found = $this->service->findByName('nonexistent.perm');
        $this->assertNull($found);
    }

    public function test_it_can_get_grouped_permissions(): void
    {
        $this->service->create(['name' => 'posts.create', 'group' => 'posts', 'guard_name' => 'web']);
        $this->service->create(['name' => 'posts.edit', 'group' => 'posts', 'guard_name' => 'web']);
        $this->service->create(['name' => 'users.view', 'group' => 'users', 'guard_name' => 'web']);

        $grouped = $this->service->getAllGrouped();
        $this->assertArrayHasKey('posts', $grouped);
        $this->assertArrayHasKey('users', $grouped);
        $this->assertCount(2, $grouped['posts']);
    }

    public function test_it_can_get_permission_names(): void
    {
        $this->service->create(['name' => 'cache.clear', 'guard_name' => 'web']);

        $names = $this->service->getAllPermissionNames();
        $this->assertTrue($names->contains('cache.clear'));
    }
}
