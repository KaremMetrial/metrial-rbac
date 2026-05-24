<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Metrial\RBAC\Services\AssignmentService;

class CacheWarmCommand extends Command
{
    protected $signature = 'rbac:cache:warm';

    protected $description = 'Pre-warm the RBAC permission cache for all users';

    public function handle(AssignmentService $assignments): int
    {
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));

        $users = $userModel::all();
        $bar = $this->output->createProgressBar($users->count());

        foreach ($users as $user) {
            $assignments->getRoles($user);
            $assignments->getPermissions($user);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Cache warmed for {$users->count()} users.");

        return self::SUCCESS;
    }
}
