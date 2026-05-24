<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Metrial\RBAC\Services\PermissionService;

class PermissionCreateCommand extends Command
{
    protected $signature = 'rbac:permission:create
                            {name : The name of the permission}
                            {--guard=web : The guard name}
                            {--group= : The permission group}
                            {--description= : Description of the permission}';

    protected $description = 'Create a new permission';

    public function handle(PermissionService $permissions): int
    {
        $data = [
            'name'        => $this->argument('name'),
            'guard_name'  => $this->option('guard'),
            'group'       => $this->option('group'),
            'description' => $this->option('description'),
        ];

        $permission = $permissions->create($data);

        $this->info("Permission [{$permission->name}] created successfully with ID: {$permission->id}");

        return self::SUCCESS;
    }
}
