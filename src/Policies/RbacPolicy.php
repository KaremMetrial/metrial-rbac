<?php

namespace Metrial\RBAC\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

abstract class RbacPolicy
{
    use HandlesAuthorization;

    /**
     * Map of policy methods to RBAC permission names.
     * Override in child classes.
     *
     * @var array<string, string>
     */
    protected array $rbacMap = [];

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        $this->registerRbacChecks();
    }

    /**
     * Register RBAC checks for methods defined in $rbacMap.
     */
    protected function registerRbacChecks(): void
    {
        foreach ($this->rbacMap as $method => $permission) {
            \Illuminate\Support\Facades\Gate::define(
                $this->abilityName($method),
                function (Model $user, ...$args) use ($method, $permission) {
                    if (! $user->hasPermissionTo($permission)) {
                        return false;
                    }

                    // If the child class defines the method, call it for resource-level checks
                    if (method_exists($this, $method)) {
                        return $this->{$method}($user, ...$args);
                    }

                    return true;
                }
            );
        }
    }

    /**
     * Generate a Gate ability name from the policy method.
     */
    protected function abilityName(string $method): string
    {
        $class = class_basename(static::class);
        $resource = str_replace('Policy', '', $class);

        return strtolower($resource) . '.' . $method;
    }

    /**
     * Fallback: before hook that checks RBAC permissions.
     * Override $rbacMap to use this.
     */
    public function before(Model $user, string $ability): ?bool
    {
        $permission = $this->rbacMap[$ability] ?? null;

        if ($permission && $user->hasPermissionTo($permission)) {
            return true;
        }

        return null;
    }
}
