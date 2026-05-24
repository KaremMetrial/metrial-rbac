<?php

namespace Metrial\RBAC\Observers;

use Metrial\RBAC\Models\Permission;

class PermissionObserver
{
    public function created(Permission $permission): void
    {
        $this->audit('permission.created', $permission, []);
    }

    public function updated(Permission $permission): void
    {
        $this->audit('permission.updated', $permission, $permission->getOriginal());
    }

    public function deleted(Permission $permission): void
    {
        $this->audit('permission.deleted', $permission, $permission->toArray());
    }

    public function restored(Permission $permission): void
    {
        $this->audit('permission.restored', $permission, []);
    }

    protected function audit(string $action, Permission $permission, array $oldValue): void
    {
        app(\Metrial\RBAC\Services\AuditService::class)->log(
            $action, 'permission', $permission->id, $oldValue, $permission->toArray()
        );
    }
}
