<?php

namespace Metrial\RBAC\Tests\Integration;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Metrial\RBAC\Middleware\RoleMiddleware;
use Metrial\RBAC\Middleware\PermissionMiddleware;
use Metrial\RBAC\Middleware\TeamMiddleware;
use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class MiddlewareIntegrationTest extends TestCase
{
    use CreatesRbacData;

    public function test_role_middleware_allows_matching_role(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Admin', 'slug' => 'admin-mid']);
        $this->assignRoleToUser($user, $role);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new RoleMiddleware();
        $response = $middleware->handle($request, fn () => new Response('OK'), 'admin-mid');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_role_middleware_blocks_without_role(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('You do not have the required role');

        $user = $this->createUser();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new RoleMiddleware();
        $middleware->handle($request, fn () => new Response('OK'), 'admin-mid');
    }

    public function test_role_middleware_allows_any_of_multiple_roles(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Editor', 'slug' => 'editor-mid']);
        $this->assignRoleToUser($user, $role);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new RoleMiddleware();
        $response = $middleware->handle($request, fn () => new Response('OK'), 'admin-mid', 'editor-mid');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_role_middleware_blocks_unauthenticated(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => null);

        $middleware = new RoleMiddleware();
        $middleware->handle($request, fn () => new Response('OK'), 'admin-mid');
    }

    public function test_permission_middleware_allows_with_permission(): void
    {
        $user = $this->createUser();
        $perm = $this->createPermission(['name' => 'mid-perm-allowed']);
        $this->givePermissionToUser($user, $perm);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();
        $response = $middleware->handle($request, fn () => new Response('OK'), 'mid-perm-allowed');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_permission_middleware_blocks_without_permission(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('You do not have the required permission');

        $user = $this->createUser();

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();
        $middleware->handle($request, fn () => new Response('OK'), 'denied-perm');
    }

    public function test_permission_middleware_supports_wildcards(): void
    {
        // Create a permission in the 'posts' group so the wildcard matches it
        $this->createPermission(['name' => 'posts.create', 'group' => 'posts']);

        $user = $this->createUser();
        $wildcardPerm = $this->createPermission(['name' => 'posts.*', 'group' => 'posts']);
        $this->givePermissionToUser($user, $wildcardPerm);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new PermissionMiddleware();
        $response = $middleware->handle($request, fn () => new Response('OK'), 'posts.create');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_team_middleware_blocks_non_member(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('No team context provided');

        $user = $this->createUser();

        $request = Request::create('/teams/some-id/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new TeamMiddleware();
        $middleware->handle($request, fn () => new Response('OK'));
    }
}
