<?php

namespace Metrial\RBAC\Gates;

use Illuminate\Support\Facades\Gate;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Models\Team;

class RbacGateRegistrar
{
    public function register(): void
    {
        if (config('rbac.gate_mode') !== 'auto') {
            return;
        }

        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            Gate::define($permission->name, function ($user, ...$args) use ($permission) {
                $team = $args['team'] ?? null;

                if ($team instanceof Team) {
                    $user->switchTeam($team);
                }

                return $user->hasPermissionTo($permission->name, $team);
            });
        }
    }
}
