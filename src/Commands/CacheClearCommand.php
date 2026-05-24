<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Metrial\RBAC\Services\CacheService;

class CacheClearCommand extends Command
{
    protected $signature = 'rbac:cache:clear';

    protected $description = 'Flush all RBAC caches';

    public function handle(CacheService $cache): int
    {
        $cache->flush();

        $this->info('RBAC cache flushed successfully.');

        return self::SUCCESS;
    }
}
