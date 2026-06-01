<?php

namespace Metrial\RBAC\Tests\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Models\Team;
use Metrial\RBAC\Traits\HasRoles;
use Metrial\RBAC\Traits\HasPermissions;
use Metrial\RBAC\Traits\HasTeams;

trait CreatesRbacData
{
    protected function createUser(array $overrides = []): Authenticatable
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->timestamps();
            });
        }

        $user = new class extends Authenticatable {
            use HasRoles, HasPermissions, HasTeams;

            protected $table = 'users';
            protected $guarded = [];
            protected $keyType = 'string';
            public $incrementing = false;
        };

        $user->id = $overrides['id'] ?? \Illuminate\Support\Str::uuid()->toString();
        $user->name = $overrides['name'] ?? 'Test User';
        $user->email = $overrides['email'] ?? 'test.' . uniqid() . '@example.com';
        $user->password = bcrypt('password');
        $user->save();

        return $user;
    }

    protected function createRole(array $overrides = []): Role
    {
        return Role::create(array_merge([
            'name'       => 'Test Role',
            'slug'       => 'test-role-' . uniqid(),
            'guard_name' => 'web',
        ], $overrides));
    }

    protected function createPermission(array $overrides = []): Permission
    {
        return Permission::create(array_merge([
            'name'       => 'test-perm-' . uniqid(),
            'guard_name' => 'web',
        ], $overrides));
    }

    protected function createTeam(array $overrides = []): Team
    {
        return Team::create(array_merge([
            'name' => 'Test Team',
            'slug' => 'test-team-' . uniqid(),
        ], $overrides));
    }

    protected function assignRoleToUser($user, $role, ?Team $team = null, ?string $guard = null, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $expiresAt = null): void
    {
        $user->assignRole($role, $team, $guard ?? 'web', $startsAt, $expiresAt);
    }

    protected function givePermissionToUser($user, $permission, ?Team $team = null, ?string $guard = null, ?\DateTimeInterface $startsAt = null, ?\DateTimeInterface $expiresAt = null): void
    {
        $user->givePermissionTo($permission, $team, $guard ?? 'web', $startsAt, $expiresAt);
    }
}
