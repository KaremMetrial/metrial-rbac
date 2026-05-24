<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Metrial\RBAC\Services\RoleService;
use Metrial\RBAC\Services\AssignmentService;

class AssignRoleCommand extends Command
{
    protected $signature = 'rbac:assign
                            {user : The user ID or email}
                            {role : The role slug or ID}
                            {--guard=web : The guard name}
                            {--team= : The team ID}';

    protected $description = 'Assign a role to a user';

    public function handle(RoleService $roles, AssignmentService $assignments): int
    {
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));
        $user = $this->findUser($userModel);

        if (! $user) {
            $this->error("User [{$this->argument('user')}] not found.");

            return self::FAILURE;
        }

        $role = $roles->findBySlug($this->argument('role'), $this->option('guard'))
            ?? $roles->findById($this->argument('role'));

        if (! $role) {
            $this->error("Role [{$this->argument('role')}] not found.");

            return self::FAILURE;
        }

        $team = $this->option('team')
            ? \Metrial\RBAC\Models\Team::find($this->option('team'))
            : null;

        $assignments->assignRole($user, $role, $team, $this->option('guard'));

        $this->info("Role [{$role->name}] assigned to user [{$user->getAuthIdentifier()}].");

        return self::SUCCESS;
    }

    protected function findUser(string $userModel)
    {
        $identifier = $this->argument('user');

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $userModel::where('email', $identifier)->first();
        }

        return $userModel::find($identifier);
    }
}
