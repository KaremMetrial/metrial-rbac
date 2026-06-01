<?php

namespace Metrial\RBAC\Providers;

use Faker\Generator;
use Faker\Provider\Base;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Permission;
use Metrial\RBAC\Models\Team;

class RbacFakerProvider extends Base
{
    public function __construct(Generator $generator)
    {
        parent::__construct($generator);
    }

    /**
     * Create and return a Role.
     */
    public function rbacRole(array $attributes = []): Role
    {
        return Role::create(array_merge([
            'name'       => $this->generator->unique()->jobTitle(),
            'guard_name' => 'web',
        ], $attributes));
    }

    /**
     * Create and return a Permission.
     */
    public function rbacPermission(array $attributes = []): Permission
    {
        return Permission::create(array_merge([
            'name'       => $this->generator->unique()->slug(3),
            'guard_name' => 'web',
        ], $attributes));
    }

    /**
     * Create and return a Team.
     */
    public function rbacTeam(array $attributes = []): Team
    {
        return Team::create(array_merge([
            'name' => $this->generator->unique()->company(),
        ], $attributes));
    }

    /**
     * Get an existing random Role (or create one).
     */
    public function existingRole(): ?Role
    {
        return Role::inRandomOrder()->first() ?? $this->rbacRole();
    }

    /**
     * Get an existing random Permission (or create one).
     */
    public function existingPermission(): ?Permission
    {
        return Permission::inRandomOrder()->first() ?? $this->rbacPermission();
    }

    /**
     * Get an existing random Team (or create one).
     */
    public function existingTeam(): ?Team
    {
        return Team::inRandomOrder()->first() ?? $this->rbacTeam();
    }
}
