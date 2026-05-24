<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DoctorCommand extends Command
{
    protected $signature = 'rbac:doctor';

    protected $description = 'Diagnose common RBAC misconfigurations';

    public function handle(): int
    {
        $this->info('Metrial RBAC Doctor');
        $this->line(str_repeat('─', 40));

        $issues = 0;

        // Check tables
        $tables = array_values(config('rbac.tables'));
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $this->info("✓ Table [{$table}] exists.");
            } else {
                $this->error("✗ Table [{$table}] is MISSING. Run: php artisan migrate");
                $issues++;
            }
        }

        // Check user model
        $userModel = config('rbac.user_model', config('auth.providers.users.model'));
        if (class_exists($userModel)) {
            $this->info("✓ User model [{$userModel}] exists.");
        } else {
            $this->error("✗ User model [{$userModel}] not found.");
            $issues++;
        }

        // Check cache
        $cacheEnabled = config('rbac.cache.enabled');
        $cacheStore = config('rbac.cache.store', config('cache.default'));
        $this->info('Cache: ' . ($cacheEnabled ? "enabled (store: {$cacheStore})" : 'disabled'));

        // Check super-admin
        $superAdmin = config('rbac.super_admin_role');
        $this->info('Super admin role: ' . ($superAdmin ?? 'disabled'));

        // Check teams
        $teamsEnabled = config('rbac.teams.enabled');
        $this->info('Teams: ' . ($teamsEnabled ? 'enabled' : 'disabled'));

        // Check audit
        $auditEnabled = config('rbac.audit.enabled');
        $this->info('Audit logging: ' . ($auditEnabled ? 'enabled' : 'disabled'));

        $this->line(str_repeat('─', 40));

        if ($issues === 0) {
            $this->info('All checks passed! RBAC is properly configured.');

            return self::SUCCESS;
        }

        $this->error("Found {$issues} issue(s). Please fix them above.");

        return self::FAILURE;
    }
}
