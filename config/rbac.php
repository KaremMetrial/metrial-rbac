<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    | The authenticatable model that will receive the HasRoles trait.
    */
    'user_model' => env('RBAC_USER_MODEL', App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    | When a user holds this role, all Gate checks return true automatically.
    | Set to null to disable. Every super-admin bypass is logged to the audit
    | log with action `superadmin.bypass`.
    */
    'super_admin_role' => env('RBAC_SUPER_ADMIN_ROLE', null),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled'     => env('RBAC_CACHE_ENABLED', true),
        'store'       => env('RBAC_CACHE_STORE', config('cache.default')),
        'prefix'      => 'rbac:',
        'ttl'         => env('RBAC_CACHE_TTL', 300),
        'version_key' => 'rbac:schema_version',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'teams'             => 'teams',
        'roles'             => 'roles',
        'permissions'       => 'permissions',
        'role_permission'   => 'role_permission',
        'role_hierarchy'    => 'role_hierarchy',
        'model_roles'       => 'model_roles',
        'model_permissions' => 'model_permissions',
        'model_teams'       => 'model_teams',
        'audit_log'         => 'rbac_audit_log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model UUIDs
    |--------------------------------------------------------------------------
    */
    'uuids' => true,

    /*
    |--------------------------------------------------------------------------
    | Teams
    |--------------------------------------------------------------------------
    */
    'teams' => [
        'enabled'           => true,
        'strict'            => false,
        'user_primary_team' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled'     => true,
        'queue'       => false,
        'connection'  => null,
        'prune_after'  => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Aliases
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'role'       => 'rbac.role',
        'permission' => 'rbac.permission',
        'team'       => 'rbac.team',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route/Resource Permission Mapping
    |--------------------------------------------------------------------------
    */
    'resource_permissions' => [
        'map' => [
            'index'   => 'view',
            'show'    => 'view',
            'create'  => 'create',
            'store'   => 'create',
            'edit'    => 'update',
            'update'  => 'update',
            'destroy' => 'delete',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate Registration Mode
    |--------------------------------------------------------------------------
    | "auto"  — register every permission as a Gate ability at boot.
    | "explicit" — only abilities defined in Gate::define() callbacks.
    */
    'gate_mode' => 'auto',

];
