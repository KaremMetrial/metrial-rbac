<?php

namespace Metrial\RBAC\Tests\Feature;

use Metrial\RBAC\Models\AuditLog;
use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class AuditLoggingTest extends TestCase
{
    use CreatesRbacData;

    public function test_role_assignment_is_audited(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Audited', 'slug' => 'audited-role']);
        $this->assignRoleToUser($user, $role);

        $this->assertDatabaseHas('rbac_audit_log', [
            'action'      => 'role.assigned',
            'entity_id'   => $role->id,
            'entity_type' => 'role',
        ]);
    }

    public function test_role_revocation_is_audited(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'To Unassign', 'slug' => 'to-unassign']);
        $this->assignRoleToUser($user, $role);
        $user->removeRole('to-unassign');

        $this->assertDatabaseHas('rbac_audit_log', [
            'action'      => 'role.revoked',
            'entity_id'   => $role->id,
        ]);
    }

    public function test_permission_given_is_audited(): void
    {
        $user = $this->createUser();
        $perm = $this->createPermission(['name' => 'audited-perm']);
        $this->givePermissionToUser($user, $perm);

        $this->assertDatabaseHas('rbac_audit_log', [
            'action'      => 'permission.given',
            'entity_id'   => $perm->id,
        ]);
    }

    public function test_audit_log_contains_context(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Context Role', 'slug' => 'context-role']);
        $this->assignRoleToUser($user, $role);

        // PHPUnit runs in console context
        $this->assertDatabaseHas('rbac_audit_log', [
            'action'  => 'role.assigned',
            'context' => 'cli',
        ]);
    }

    public function test_audit_prune_deletes_old_entries(): void
    {
        $user = $this->createUser();
        $role = $this->createRole(['name' => 'Prune Audit', 'slug' => 'prune-audit']);
        $this->assignRoleToUser($user, $role);

        // Manually set created_at to 100 days ago
        AuditLog::where('action', 'role.assigned')->update([
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('rbac:audit:prune', ['--days' => 90]);

        $this->assertDatabaseMissing('rbac_audit_log', [
            'action'    => 'role.assigned',
            'entity_id' => $role->id,
        ]);
    }
}
