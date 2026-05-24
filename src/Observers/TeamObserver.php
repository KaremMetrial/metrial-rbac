<?php

namespace Metrial\RBAC\Observers;

use Metrial\RBAC\Models\Team;

class TeamObserver
{
    public function created(Team $team): void
    {
        $this->audit('team.created', $team, []);
    }

    public function updated(Team $team): void
    {
        $this->audit('team.updated', $team, $team->getOriginal());
    }

    public function deleted(Team $team): void
    {
        $this->audit('team.deleted', $team, $team->toArray());
    }

    public function restored(Team $team): void
    {
        $this->audit('team.restored', $team, []);
    }

    protected function audit(string $action, Team $team, array $oldValue): void
    {
        app(\Metrial\RBAC\Services\AuditService::class)->log(
            $action, 'team', $team->id, $oldValue, $team->toArray()
        );
    }
}
