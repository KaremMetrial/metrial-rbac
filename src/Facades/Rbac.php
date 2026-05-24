<?php

namespace Metrial\RBAC\Facades;

use Illuminate\Support\Facades\Facade;
use Metrial\RBAC\Services\RoleService;
use Metrial\RBAC\Services\PermissionService;
use Metrial\RBAC\Services\AssignmentService;
use Metrial\RBAC\Services\TeamService;
use Metrial\RBAC\Services\AuditService;

class Rbac extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rbac';
    }

    public static function role(): RoleService
    {
        return app(RoleService::class);
    }

    public static function permission(): PermissionService
    {
        return app(PermissionService::class);
    }

    public static function assignment(): AssignmentService
    {
        return app(AssignmentService::class);
    }

    public static function team(): TeamService
    {
        return app(TeamService::class);
    }

    public static function audit(): AuditService
    {
        return app(AuditService::class);
    }
}
