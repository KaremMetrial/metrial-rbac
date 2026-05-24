<?php

namespace Metrial\RBAC\Blade;

use Illuminate\Support\Facades\Blade;

class RbacBladeDirectives
{
    public static function register(): void
    {
        Blade::if('role', function (string ...$roles) {
            $user = auth()->user();

            return $user && $user->hasAnyRole($roles);
        });

        Blade::if('hasanyrole', function (array $roles) {
            $user = auth()->user();

            return $user && $user->hasAnyRole($roles);
        });

        Blade::if('hasallroles', function (array $roles) {
            $user = auth()->user();

            return $user && $user->hasAllRoles($roles);
        });

        Blade::if('haspermission', function (string $permission) {
            $user = auth()->user();

            return $user && $user->hasPermissionTo($permission);
        });
    }
}
