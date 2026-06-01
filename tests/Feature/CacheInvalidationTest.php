<?php

namespace Metrial\RBAC\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Metrial\RBAC\Services\CacheService;
use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class CacheInvalidationTest extends TestCase
{
    use CreatesRbacData;

    protected CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = app(CacheService::class);
        $this->cacheService->flush();
    }

    public function test_cache_stores_user_permissions(): void
    {
        $this->app['config']->set('rbac.cache.enabled', true);

        $user = $this->createUser();
        $perm = $this->createPermission(['name' => 'cache-test-perm']);
        $this->givePermissionToUser($user, $perm);

        // First call should cache
        $perms1 = $user->getPermissions();
        $this->assertTrue($perms1->contains('cache-test-perm'));
    }

    public function test_assigning_role_invalidates_cache(): void
    {
        $this->app['config']->set('rbac.cache.enabled', true);

        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Cache Invalidate', 'slug' => 'cache-inv']);

        // Prime the cache
        $user->getRoles();

        // Assign role — should bust cache
        $this->assignRoleToUser($user, $role);

        // Should still reflect the new assignment
        $this->assertTrue($user->hasRole('cache-inv'));
    }

    public function test_revoking_role_invalidates_cache(): void
    {
        $this->app['config']->set('rbac.cache.enabled', true);

        $user = $this->createUser();
        $role = $this->createRole(['name' => 'To Remove', 'slug' => 'to-remove-cache']);
        $this->assignRoleToUser($user, $role);

        // Prime cache
        $this->assertTrue($user->hasRole('to-remove-cache'));

        // Remove — should bust cache
        $user->removeRole('to-remove-cache');
        $this->assertFalse($user->hasRole('to-remove-cache'));
    }

    public function test_cache_service_forget_clears_keys(): void
    {
        $this->app['config']->set('rbac.cache.enabled', true);

        // Store a value using cache service
        $this->cacheService->remember('test-forget-key', fn () => 'stored');

        // Forget should remove it
        $this->cacheService->forget('test-forget-key');

        // Callback should be called again (value was forgotten)
        $callCount = 0;
        $result = $this->cacheService->remember('test-forget-key', function () use (&$callCount) {
            $callCount++;

            return 'value-' . $callCount;
        });

        $this->assertEquals('value-1', $result);
    }

    public function test_cache_warm_command_preloads_users(): void
    {
        $this->app['config']->set('rbac.cache.enabled', true);

        $user = $this->createUser();
        $perm = $this->createPermission(['name' => 'warm-perm']);
        $this->givePermissionToUser($user, $perm);

        $this->cacheService->flush();

        $this->artisan('rbac:cache:warm');

        // After warm, permissions should be resolvable
        $this->assertTrue($user->hasPermissionTo('warm-perm'));
    }
}
