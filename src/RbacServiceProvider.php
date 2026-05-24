<?php

namespace Metrial\RBAC;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Metrial\RBAC\Services\CacheService;
use Metrial\RBAC\Services\AuditService;
use Metrial\RBAC\Services\RoleService;
use Metrial\RBAC\Services\PermissionService;
use Metrial\RBAC\Services\AssignmentService;
use Metrial\RBAC\Services\TeamService;
use Metrial\RBAC\Middleware\RoleMiddleware;
use Metrial\RBAC\Middleware\PermissionMiddleware;
use Metrial\RBAC\Middleware\TeamMiddleware;
use Metrial\RBAC\Blade\RbacBladeDirectives;
use Metrial\RBAC\Gates\RbacGateRegistrar;
use Metrial\RBAC\Observers\RoleObserver;
use Metrial\RBAC\Observers\PermissionObserver;
use Metrial\RBAC\Observers\TeamObserver;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Models\Team;
use Metrial\RBAC\Commands;

class RbacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/rbac.php', 'rbac');

        // Register services
        $this->app->singleton(CacheService::class);
        $this->app->singleton(AuditService::class);
        $this->app->singleton(RoleService::class);
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(AssignmentService::class);
        $this->app->singleton(TeamService::class);

        // Register facade accessor
        $this->app->singleton('rbac', function ($app) {
            return new class($app) {
                public function __construct(protected $app) {}

                public function role(): RoleService { return $this->app->make(RoleService::class); }
                public function permission(): PermissionService { return $this->app->make(PermissionService::class); }
                public function assignment(): AssignmentService { return $this->app->make(AssignmentService::class); }
                public function team(): TeamService { return $this->app->make(TeamService::class); }
                public function audit(): AuditService { return $this->app->make(AuditService::class); }
            };
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/rbac.php' => config_path('rbac.php'),
        ], 'rbac-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'rbac-migrations');

        // Publish seeder
        $this->publishes([
            __DIR__ . '/../database/seeders/' => database_path('seeders'),
        ], 'rbac-seeders');

        // Register middleware
        $this->registerMiddleware();

        // Register blade directives
        RbacBladeDirectives::register();

        // Register gates (deferred — only when Gate is actually resolved, not during boot)
        if (config('rbac.gate_mode') === 'auto') {
            $this->callAfterResolving('gate', function () {
                app(RbacGateRegistrar::class)->register();
            });
        }

        // Register observers
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);
        Team::observe(TeamObserver::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
                Commands\RoleCreateCommand::class,
                Commands\PermissionCreateCommand::class,
                Commands\AssignRoleCommand::class,
                Commands\RevokeRoleCommand::class,
                Commands\CacheClearCommand::class,
                Commands\CacheWarmCommand::class,
                Commands\PruneExpiredCommand::class,
                Commands\AuditPruneCommand::class,
                Commands\DoctorCommand::class,
            ]);
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware(config('rbac.middleware.role', 'rbac.role'), RoleMiddleware::class);
        $router->aliasMiddleware(config('rbac.middleware.permission', 'rbac.permission'), PermissionMiddleware::class);
        $router->aliasMiddleware(config('rbac.middleware.team', 'rbac.team'), TeamMiddleware::class);
    }
}
