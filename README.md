# Metrial Laravel RBAC

<p align="center">
  <strong>Enterprise-grade Role-Based Access Control for Laravel</strong>
  <br>
  Roles · Permissions · Teams · Hierarchy · Time-Bound Assignments · Audit Logging
</p>

<p align="center">
  <a href="https://packagist.org/packages/metrial/laravel-rbac"><img src="https://img.shields.io/packagist/v/metrial/laravel-rbac.svg" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/metrial/laravel-rbac"><img src="https://img.shields.io/packagist/l/metrial/laravel-rbac.svg" alt="License"></a>
  <a href="https://github.com/KaremMetrial/metrial-rbac/actions"><img src="https://img.shields.io/github/actions/workflow/status/KaremMetrial/metrial-rbac/tests.yml" alt="Tests"></a>
</p>

Metrial RBAC is a production-ready, drop-in authorization package for Laravel applications. It provides a complete role-based access control system with teams, hierarchical roles, time-bound assignments, and a full audit trail — all without dictating your application's architecture.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Usage](#usage)
  - [Roles](#roles)
  - [Permissions](#permissions)
  - [Assigning & Revoking](#assigning--revoking)
  - [Checking Authorization](#checking-authorization)
  - [Teams](#teams)
  - [Role Hierarchy](#role-hierarchy)
  - [Time-Bound Assignments](#time-bound-assignments)
  - [Blade Directives](#blade-directives)
  - [Middleware](#middleware)
  - [The Gate Layer](#the-gate-layer)
  - [The Facade](#the-facade)
- [Artisan Commands](#artisan-commands)
- [Audit Logging](#audit-logging)
- [Caching](#caching)
- [Database Schema](#database-schema)
- [Testing](#testing)
- [Security](#security)
- [License](#license)

---

## Features

| Feature | Description |
|---|---|
| **Roles & Permissions** | Create granular roles and assign fine-grained permissions to them. |
| **Direct Permissions** | Assign permissions directly to users, bypassing roles. |
| **Teams** | Scope roles and permissions per team/tenant. Users switch context with `switchTeam()`. |
| **Role Hierarchy** | Roles inherit from other roles via a DAG closure table — no recursive queries. |
| **Time-Bound Assignments** | Assign roles or permissions with `starts_at` / `expires_at` for temporary access. |
| **Audit Trail** | Every mutation (assign, revoke, create, delete) is logged with actor, IP, context, and snapshots. |
| **Cache-First Reads** | Permission resolution is cached with automatic invalidation on mutation. Expiry-safe. |
| **Blade Directives** | `@role`, `@hasanyrole`, `@hasallroles`, `@haspermission` built in. |
| **Middleware** | Route-level `rbac.role`, `rbac.permission`, `rbac.team` middleware. |
| **Gate Integration** | Auto-registers every permission as a Gate ability. `@can`, `->can()`, `->authorize()` all work. |
| **Soft Deletes** | All mutable entities support soft deletes for history preservation. |
| **Guard-Aware** | Full multi-guard support (`web`, `api`, `sanctum`, custom). |
| **10 Artisan Commands** | Install, create, assign, revoke, cache, prune, doctor. |
| **Super-Admin Bypass** | Optional opt-in super-admin role with fully traceable bypass logging. |

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ≥ 8.2 |
| Laravel | 10.x, 11.x, 12.x, 13.x |
| Database | MySQL 8+, PostgreSQL 14+, SQLite 3.35+ |

---

## Installation

### 1. Install via Composer

```bash
composer require metrial/laravel-rbac
```

The package auto-discovers its service provider on Laravel 10+. No manual registration needed.

### 2. Run the Installer

```bash
php artisan rbac:install
```

This publishes the config file, migrations, and scaffolds your User model with the `HasRoles` and `HasPermissions` traits.

### 3. Run Migrations

```bash
php artisan migrate
```

This creates all 9 RBAC tables: `teams`, `roles`, `permissions`, `role_permission`, `role_hierarchy`, `model_roles`, `model_permissions`, `model_teams`, and `rbac_audit_log`.

### 4. (Optional) Seed Default Data

```bash
php artisan db:seed --class=Metrial\\RBAC\\Seeders\\RbacDefaultSeeder
```

This creates 4 default roles (`super-admin`, `admin`, `editor`, `viewer`) and 9 common permissions.

---

## Quick Start

```php
<?php

// app/Models/User.php — added by rbac:install
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Metrial\RBAC\Traits\HasRoles;
use Metrial\RBAC\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasRoles, HasPermissions;
}
```

```php
// Create roles and permissions
use Metrial\RBAC\Facades\Rbac;

$admin = Rbac::role()->create(['name' => 'Admin', 'slug' => 'admin']);
$editPosts = Rbac::permission()->create(['name' => 'edit-posts', 'group' => 'posts']);

// Assign permission to role
Rbac::role()->assignPermission($admin, $editPosts->id);

// Assign role to user
$user->assignRole('admin');

// Check authorization
$user->hasRole('admin');           // true
$user->hasPermissionTo('edit-posts'); // true
$user->can('edit-posts');          // true (Gate)
```

---

## Configuration

Publish the config file (also done by `rbac:install`):

```bash
php artisan vendor:publish --tag=rbac-config
```

Key options in `config/rbac.php`:

```php
return [

    // The authenticatable model that receives the HasRoles trait.
    'user_model' => env('RBAC_USER_MODEL', App\Models\User::class),

    // Super-admin role name. Set to null to disable.
    // Every bypass is logged to the audit log with action `superadmin.bypass`.
    'super_admin_role' => env('RBAC_SUPER_ADMIN_ROLE', null),

    // Cache settings (reads are always cached)
    'cache' => [
        'enabled'    => env('RBAC_CACHE_ENABLED', true),
        'store'      => env('RBAC_CACHE_STORE', config('cache.default')),
        'ttl'        => env('RBAC_CACHE_TTL', 300), // 5 minutes default
        'version_key'=> 'rbac:schema_version',      // bump to nuke all
    ],

    // Database table names
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

    // Auto-register every permission as a Gate ability at boot.
    'gate_mode' => 'auto', // "auto" or "explicit"

    // Teams
    'teams' => [
        'enabled'           => true,
        'strict'            => false, // reject permissions without team_id
        'user_primary_team' => true,  // auto-set first team as primary
    ],

    // Audit logging
    'audit' => [
        'enabled'    => true,
        'queue'      => false, // dispatch audit writes to queue?
        'prune_after' => 90,   // days; 0 = never
    ],
];
```

---

## Usage

### Roles

```php
use Metrial\RBAC\Facades\Rbac;
use Metrial\RBAC\Models\Role;

// Create a role
$role = Rbac::role()->create([
    'name'       => 'Editor',
    'slug'       => 'editor',
    'guard_name' => 'web',
    'level'      => 20,
]);

// Find a role
$role = Rbac::role()->findBySlug('editor');
$role = Rbac::role()->findById('uuid-here');

// Get all roles (optionally filtered by guard)
$roles = Rbac::role()->getAllRoles('web');
```

### Permissions

```php
use Metrial\RBAC\Facades\Rbac;
use Metrial\RBAC\Models\Permission;

// Create a permission
$perm = Rbac::permission()->create([
    'name'       => 'edit-posts',
    'guard_name' => 'web',
    'group'      => 'posts',
]);

// Find a permission
$perm = Rbac::permission()->findByName('edit-posts');
$perm = Rbac::permission()->findById('uuid-here');

// Get all permissions grouped by `group` column
$grouped = Rbac::permission()->allGrouped('web');
// ['posts' => Collection, 'users' => Collection, ...]

// Get flat collection of permission names
$names = Rbac::permission()->getAllPermissionNames('web');
```

### Assigning & Revoking

```php
// Assign a role to a user
$user->assignRole('editor');
$user->assignRole($roleInstance);
$user->assignRole('editor', team: $team);
$user->assignRole('editor', team: $team, startsAt: now(), expiresAt: now()->addDays(30));

// Remove a role (all assignments for this slug across all teams and time windows)
$user->removeRole('editor');
$user->removeRole('editor', team: $team); // only in this team

// Sync roles (replace all with new set)
$user->syncRoles(['editor', 'reviewer']);
$user->syncRoles($roleCollection, team: $team);

// Direct permissions
$user->givePermissionTo('edit-posts');
$user->givePermissionTo('edit-posts', team: $team, expiresAt: now()->addWeek());
$user->revokePermissionTo('edit-posts');
$user->syncPermissions(['edit-posts', 'publish-posts']);
```

### Checking Authorization

```php
// Role checks
$user->hasRole('editor');                        // bool
$user->hasRole('editor', team: $team);           // bool (team-scoped)
$user->hasAllRoles(['editor', 'admin']);         // bool (must have ALL)
$user->hasAnyRole(['editor', 'reviewer']);       // bool (must have ANY)

// Permission checks
$user->hasPermissionTo('edit-posts');            // bool (includes role inheritance)
$user->hasPermissionTo('edit-posts', team: $team); // bool (team-scoped)
$user->hasDirectPermission('edit-posts');        // bool (only direct, no role inheritance)
$user->hasAnyPermission(['edit', 'publish']);    // bool

// Gate checks (auto-registered when gate_mode = "auto")
$user->can('edit-posts');                        // bool
$user->cannot('edit-posts');                     // bool
```

### Teams

```php
use Metrial\RBAC\Models\Team;

// Create a team
$team = Rbac::team()->create([
    'name' => 'Acme Corp',
    'slug' => 'acme-corp',
]);

// Add/remove members
$user->addToTeam($team, asOwner: true);
$user->removeFromTeam($team);

// Check membership
$user->isMemberOf($team);   // bool
$user->isOwnerOf($team);    // bool

// Switch team context (affects all downstream permission resolution)
$user->switchTeam($team);
$user->getActiveTeamId(); // returns the team's UUID
```

### Role Hierarchy

Roles can inherit from other roles via a Directed Acyclic Graph (DAG). Permission resolution automatically walks the hierarchy — no recursive queries.

```php
$editor = Rbac::role()->create(['name' => 'Editor', 'slug' => 'editor']);
$admin  = Rbac::role()->create(['name' => 'Admin',  'slug' => 'admin']);

// Make admin a parent of editor
Rbac::role()->setParent($editor, $admin);

// Now editor inherits all of admin's permissions automatically
// Cycle detection throws RoleCycleException if you try to create a loop
$descendants = Rbac::role()->getChildRoles($editor->id);
$ancestors  = Rbac::role()->getParentRoles($editor->id);
```

### Time-Bound Assignments

Assign roles or permissions with automatic expiry:

```php
// Assign for 30 days only
$user->assignRole('editor', startsAt: now(), expiresAt: now()->addDays(30));

// Assign permission for 1 week
$user->givePermissionTo('temp-access', expiresAt: now()->addWeek());

// Future-dated (not active yet)
$user->assignRole('editor', startsAt: now()->addMonth());
```

Expired/future-dated assignments are completely ignored during resolution. No special filtering needed in your code.

**Prune expired rows and bust caches:**

```bash
# Run manually
php artisan rbac:prune-expired

# Or schedule it in app/Console/Kernel.php
$schedule->command('rbac:prune-expired')->everyMinute();
```

### Blade Directives

```blade
@role('admin')
    <a href="/admin">Admin Panel</a>
@endrole

@hasanyrole(['editor', 'reviewer'])
    <a href="/review">Review Queue</a>
@endhasanyrole

@hasallroles(['editor', 'publisher'])
    <button>Publish</button>
@endhasallroles

@haspermission('edit-posts')
    <a href="/posts/1/edit">Edit</a>
@endhaspermission

@can('edit-posts')
    <a href="/posts/1/edit">Edit</a>
@endcan
```

### Middleware

Register routes with role, permission, or team checks:

```php
use Illuminate\Support\Facades\Route;

// Role-based
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('rbac.role:admin');

// Any of the listed roles
Route::get('/moderation', [ModController::class, 'index'])
    ->middleware('rbac.role:admin,moderator');

// Permission-based
Route::resource('posts', PostController::class)
    ->middleware('rbac.permission:edit-posts');

// Team context (user must be a member; sets team context for downstream resolution)
Route::get('/teams/{team}/analytics', [AnalyticsController::class, 'index'])
    ->middleware('rbac.team');

// Combined
Route::get('/reports', [ReportController::class, 'index'])
    ->middleware(['auth', 'rbac.role:admin', 'rbac.permission:view-reports']);
```

### The Gate Layer

When `gate_mode = 'auto'` (default), every permission in the database is registered as a Gate ability at boot time. This means all standard Laravel authorization patterns work out of the box:

```php
// In controllers
$this->authorize('edit-posts');

// In policies
public function update(User $user, Post $post): bool
{
    return $user->can('edit-posts');
}

// In Blade
@can('edit-posts')
    <a>Edit</a>
@endcan

// Direct check
if ($user->can('edit-posts')) { ... }
if ($user->cant('delete-posts')) { ... }
```

Set `gate_mode` to `'explicit'` in config to disable auto-registration and manually define your Gate abilities.

### The Facade

```php
use Metrial\RBAC\Facades\Rbac;

// Service access
Rbac::role()->create([...]);
Rbac::permission()->findBySlug('edit-posts');
Rbac::team()->addMember($team, $user);
Rbac::audit()->forUser($user);
```

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan rbac:install` | Scaffold User model, publish config and migrations |
| `php artisan rbac:role:create {name}` | Create a new role |
| `php artisan rbac:permission:create {name}` | Create a new permission |
| `php artisan rbac:assign {user} {role}` | Assign role to user |
| `php artisan rbac:revoke {user} {role}` | Revoke role from user |
| `php artisan rbac:cache:clear` | Flush all RBAC caches |
| `php artisan rbac:cache:warm` | Pre-warm permission cache for all users |
| `php artisan rbac:prune-expired` | Delete expired assignments and bust affected caches |
| `php artisan rbac:audit:prune {--days=90}` | Prune old audit log entries |
| `php artisan rbac:doctor` | Diagnose common misconfigurations |

---

## Audit Logging

Every mutation is logged to the `rbac_audit_log` table:

| Column | Description |
|---|---|
| `actor_id` | The authenticated user who performed the action |
| `action` | Machine-readable action name: `role.assigned`, `permission.given`, etc. |
| `entity_type` | Entity type: `role`, `permission`, `team` |
| `entity_id` | UUID of the affected entity |
| `old_value` | JSON snapshot before the change |
| `new_value` | JSON snapshot after the change |
| `ip_address` | Request IP (null for CLI/queue context) |
| `user_agent` | Request UA (null for CLI/queue context) |
| `context` | `http`, `cli`, `queue`, or `api` |

```php
// Query audit logs for a user
$logs = Rbac::audit()->forUser($user, limit: 50);

// Prune logs older than 90 days
php artisan rbac:audit:prune --days=90
```

---

## Caching

Permission resolution is cached by default. Cache keys:

| Key Pattern | Contains |
|---|---|
| `rbac:{version}:user:{id}:roles` | Assigned roles for a user |
| `rbac:{version}:user:{id}:permissions` | All resolved permissions (inherited + direct) |
| `rbac:{version}:user:{id}:team:{teamId}:permissions` | Team-scoped permission set |
| `rbac:{version}:role:{id}:permissions` | Permissions on a role |

**Cache is automatically invalidated** on every mutation (assign, revoke, sync). Time-bound cache entries store the `expires_at` timestamp in the payload and use a shorter TTL near expiry, ensuring expired permissions never linger in cache.

Disable caching during development:

```env
RBAC_CACHE_ENABLED=false
```

---

## Database Schema

```
teams                   roles                   permissions
──────────────────      ──────────────────      ──────────────────
id (uuid PK)            id (uuid PK)            id (uuid PK)
name                    team_id (FK, null)      name (unique)
slug (unique)           name                    guard_name
description             slug (unique)           group
created_at              description             description
updated_at              level (int)             created_at
deleted_at              guard_name              updated_at
                        is_system               deleted_at
                        created_at
                        updated_at
                        deleted_at

role_permission         role_hierarchy          model_roles
──────────────────      ──────────────────      ──────────────────
id (uuid PK)            id (uuid PK)            id (uuid PK)
role_id (FK)            ancestor_id (FK)        team_id (FK, null)
permission_id (FK)      descendant_id (FK)      role_id (FK)
created_at              depth (int)             model_type
updated_at              created_at              model_id
                        updated_at              guard_name
                                                starts_at (null)
                                                expires_at (null)
                                                assigned_by (FK, null)
                                                created_at
                                                updated_at
                                                deleted_at

model_permissions       model_teams             rbac_audit_log
──────────────────      ──────────────────      ──────────────────
id (uuid PK)            id (uuid PK)            id (uuid PK)
team_id (FK, null)      team_id (FK)            actor_id (FK, null)
permission_id (FK)      model_type              action
model_type              model_id                entity_type
model_id                is_owner                entity_id
guard_name              created_at              old_value (json)
starts_at (null)        updated_at              new_value (json)
expires_at (null)                               ip_address (null)
assigned_by (null)                              user_agent (null)
created_at                                      context
updated_at                                      created_at
deleted_at
```

---

## Testing

```bash
cd packages/metrial/rbac
composer install
vendor/bin/phpunit
```

Or from the host application:

```bash
php vendor/bin/phpunit packages/metrial/rbac/tests/
```

### Running the Test Suite

The package test suite uses Orchestra Testbench with an in-memory SQLite database. All models, services, and migrations are tested in isolation.

---

## Security

- **Super-admin bypass is opt-in** and defaults to `null`. Every bypass is logged to the audit trail.
- **Application-level time** for time-bound assignments — never relies on SQL `NOW()` to avoid clock skew.
- **Cache-safety for expiry** — cached entries store `expires_at` and use shorter TTLs near expiry. The prune command busts affected caches.
- **Append-only audit log** — no update/delete methods exposed on the AuditLog model.
- **Guard isolation** — every query scopes to `guard_name`; cross-guard access is impossible.
- **UUIDs as PKs** — no sequential ID leakage in distributed systems.
- **Soft deletes** — preserves history and keeps audit log references intact.
- **Hash-lookup permission checks** — not string comparison; resistant to timing attacks.

---

## License

Metrial Laravel RBAC is open-source software licensed under the [MIT license](LICENSE).
