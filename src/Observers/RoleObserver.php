<?php

namespace Metrial\RBAC\Observers;

use Metrial\RBAC\Models\Role;

class RoleObserver
{
    public function created(Role $role): void
    {
        $this->audit('role.created', $role, []);
    }

    public function updated(Role $role): void
    {
        $this->audit('role.updated', $role, $role->getOriginal());
    }

    public function deleted(Role $role): void
    {
        $this->audit('role.deleted', $role, $role->toArray());
    }

    public function restored(Role $role): void
    {
        $this->audit('role.restored', $role, []);
    }

    protected function audit(string $action, Role $role, array $oldValue): void
    {
        app(\Metrial\RBAC\Services\AuditService::class)->log(
            $action, 'role', $role->id, $oldValue, $role->toArray()
        );
    }
}
