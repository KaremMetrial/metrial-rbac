<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Metrial\RBAC\Services\RoleService;

class RoleCreateCommand extends Command
{
    protected $signature = 'rbac:role:create
                            {name : The name of the role}
                            {--slug= : The slug (auto-generated from name if not provided)}
                            {--guard=web : The guard name}
                            {--description= : Description of the role}
                            {--level=0 : The role level/hierarchy depth}';

    protected $description = 'Create a new role';

    public function handle(RoleService $roles): int
    {
        $data = [
            'name'        => $this->argument('name'),
            'slug'        => $this->option('slug'),
            'guard_name'  => $this->option('guard'),
            'description' => $this->option('description'),
            'level'       => (int) $this->option('level'),
        ];

        $role = $roles->create($data);

        $this->info("Role [{$role->name}] created successfully with ID: {$role->id}");

        return self::SUCCESS;
    }
}
