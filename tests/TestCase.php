<?php

namespace Metrial\RBAC\Tests;

use Metrial\RBAC\RbacServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            RbacServiceProvider::class,
        ];
    }

    protected function getPackageMigrations(): array
    {
        return [
            'packages/metrial/rbac/database/migrations',
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('auth.providers.users.model', \Illuminate\Foundation\Auth\User::class);
        $app['config']->set('rbac.user_model', \Illuminate\Foundation\Auth\User::class);
        $app['config']->set('rbac.teams.enabled', true);
        $app['config']->set('rbac.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(realpath(__DIR__ . '/../database/migrations'));
    }
}
