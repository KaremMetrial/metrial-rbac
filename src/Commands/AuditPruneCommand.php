<?php

namespace Metrial\RBAC\Commands;

use Illuminate\Console\Command;
use Metrial\RBAC\Services\AuditService;

class AuditPruneCommand extends Command
{
    protected $signature = 'rbac:audit:prune
                            {--days=90 : Delete audit log entries older than this many days}';

    protected $description = 'Prune old audit log entries';

    public function handle(AuditService $audit): int
    {
        $days = (int) $this->option('days');

        if ($days <= 0) {
            $this->error('Days must be greater than 0.');

            return self::FAILURE;
        }

        $deleted = $audit->prune($days);

        $this->info("Pruned {$deleted} audit log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
