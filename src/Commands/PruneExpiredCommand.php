<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredCommand extends Command
{
    protected $signature = 'rbac:prune-expired';

    protected $description = 'Remove expired role/permission assignments and bust affected user caches';

    public function handle(): int
    {
        $now = now();

        // Find expired model_roles
        $expiredRoleUsers = DB::table('model_roles')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->select('model_type', 'model_id')
            ->distinct()
            ->get();

        $rolesDeleted = DB::table('model_roles')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->delete();

        // Find expired model_permissions
        $expiredPermUsers = DB::table('model_permissions')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->select('model_type', 'model_id')
            ->distinct()
            ->get();

        $permsDeleted = DB::table('model_permissions')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->delete();

        // Bust caches for affected users
        $affectedUsers = $expiredRoleUsers->merge($expiredPermUsers)->unique('model_id');
        $cache = app(\Metrial\RBAC\Services\CacheService::class);

        foreach ($affectedUsers as $userRef) {
            $cache->forget("user:{$userRef->model_id}:permissions");
            $cache->forget("user:{$userRef->model_id}:roles");
            $cache->forgetByPattern("user:{$userRef->model_id}:permissions:team:*");
            $cache->forgetByPattern("user:{$userRef->model_id}:roles:team:*");
        }

        $totalDeleted = $rolesDeleted + $permsDeleted;
        $this->info("Pruned {$totalDeleted} expired assignments ({$rolesDeleted} roles, {$permsDeleted} permissions).");
        $this->info("Busted caches for {$affectedUsers->count()} affected users.");

        return self::SUCCESS;
    }
}
