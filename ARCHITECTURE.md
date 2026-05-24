# Metrial RBAC — Architecture & Design Decisions

> Technical deep-dive: what was built, why it was built that way, and the trade-offs involved.

---

## Table of Contents

- [Design Philosophy](#design-philosophy)
- [Package Structure](#package-structure)
- [Architecture Overview](#architecture-overview)
- [Why This Architecture](#why-this-architecture)
- [Key Design Decisions](#key-design-decisions)
  - [UUIDs Over Auto-Increment](#uuids-over-auto-increment)
  - [Closure Table for Role Hierarchy](#closure-table-for-role-hierarchy)
  - [Polymorphic Pivot Tables](#polymorphic-pivot-tables)
  - [Time-Bound Assignments at the Database Level](#time-bound-assignments-at-the-database-level)
  - [Cache-First Reads with Expiry Safety](#cache-first-reads-with-expiry-safety)
  - [Guard-Aware Scoping](#guard-aware-scoping)
  - [Soft Deletes on All Mutable Entities](#soft-deletes-on-all-mutable-entities)
  - [Super-Admin as Opt-In](#super-admin-as-opt-in)
  - [Application-Level Time (Not SQL NOW())](#application-level-time-not-sql-now)
  - [Deferred Gate Registration](#deferred-gate-registration)
- [Permission Resolution Flow](#permission-resolution-flow)
- [Service Layer Design](#service-layer-design)
- [Middleware Design](#middleware-design)
- [Audit Logging Strategy](#audit-logging-strategy)
- [Database Schema Rationale](#database-schema-rationale)
- [Performance Characteristics](#performance-characteristics)
- [Migration Guide from spatie/laravel-permission](#migration-guide-from-spatie/laravel-permission)

---

## Design Philosophy

Metrial RBAC was designed around four core principles:

1. **Authorization-only** — The package never touches authentication. It hooks into Laravel's existing `Authenticatable` / `Gate` layer and considers itself a pure authorization concern.

2. **Zero-configuration default** — Adding `HasRoles` to a User model and running migrations should be enough to get started. Advanced features (teams, hierarchy, time-bound) are available but not required.

3. **Enterprise-readiness** — Every production requirement was considered: multi-tenancy (teams), temporal access (time-bound), audit compliance (audit log), scalability (caching, closure tables), and security (UUIDs, super-admin caution).

4. **Opinionated where it matters, flexible where it counts** — The package is opinionated about data integrity (soft deletes, UUIDs, guard scoping) but stays flexible about how you organize your application.

---

## Package Structure

```
packages/metrial/rbac/
├── composer.json                          # Package metadata, autoloading, discovery
├── config/rbac.php                        # Default configuration
├── README.md                              # User-facing documentation
├── ARCHITECTURE.md                        # This file — technical architecture
├── database/
│   ├── migrations/                        # 9 migration files
│   │   ├── 0001_01_01_000001_create_teams_table.php
│   │   ├── 0001_01_01_000002_create_roles_table.php
│   │   ├── 0001_01_01_000003_create_permissions_table.php
│   │   ├── 0001_01_01_000004_create_role_permission_table.php
│   │   ├── 0001_01_01_000005_create_role_hierarchy_table.php
│   │   ├── 0001_01_01_000006_create_model_roles_table.php
│   │   ├── 0001_01_01_000007_create_model_permissions_table.php
│   │   ├── 0001_01_01_000008_create_audit_log_table.php
│   │   └── 0001_01_01_000009_create_model_teams_table.php
│   └── seeders/
│       └── RbacDefaultSeeder.php          # Default roles, permissions, assignments
├── src/
│   ├── RbacServiceProvider.php            # Package bootstrap, service registration
│   ├── Contracts/                         # Interfaces
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   └── AuditLogger.php
│   ├── Traits/                            # Applied to User model
│   │   ├── HasRoles.php                   # Role assignment, permission checks, super-admin
│   │   ├── HasPermissions.php             # Direct permission assignment
│   │   └── HasTeams.php                  # Team membership management
│   ├── Models/                            # Eloquent models
│   │   ├── Team.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── RoleHierarchy.php
│   │   └── AuditLog.php
│   ├── Services/                          # Business logic layer
│   │   ├── CacheService.php
│   │   ├── AuditService.php
│   │   ├── RoleService.php
│   │   ├── PermissionService.php
│   │   ├── AssignmentService.php
│   │   └── TeamService.php
│   ├── Middleware/
│   │   ├── RoleMiddleware.php
│   │   ├── PermissionMiddleware.php
│   │   └── TeamMiddleware.php
│   ├── Gates/
│   │   └── RbacGateRegistrar.php
│   ├── Blade/
│   │   └── RbacBladeDirectives.php
│   ├── Facades/
│   │   └── Rbac.php
│   ├── Observers/
│   │   ├── RoleObserver.php
│   │   ├── PermissionObserver.php
│   │   └── TeamObserver.php
│   ├── Exceptions/
│   │   ├── RoleNotFoundException.php
│   │   ├── PermissionDeniedException.php
│   │   ├── RoleCycleException.php
│   │   ├── InvalidAssignmentException.php
│   │   └── TeamAccessDeniedException.php
│   └── Commands/
│       ├── InstallCommand.php
│       ├── RoleCreateCommand.php
│       ├── PermissionCreateCommand.php
│       ├── AssignRoleCommand.php
│       ├── RevokeRoleCommand.php
│       ├── CacheClearCommand.php
│       ├── CacheWarmCommand.php
│       ├── PruneExpiredCommand.php
│       ├── AuditPruneCommand.php
│       └── DoctorCommand.php
└── tests/
    ├── TestCase.php
    └── Unit/
        ├── RoleServiceTest.php
        ├── PermissionServiceTest.php
        └── AssignmentServiceTest.php
```

---

## Architecture Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                         Host Laravel App                         │
│                                                                  │
│  ┌──────────────┐  ┌────────────────┐  ┌─────────────────────┐  │
│  │  Middleware   │  │  Gate Layer    │  │  Blade Directives   │  │
│  │  rbac.role    │  │  auto-registered│  │  @role @hasanyrole  │  │
│  │  rbac.perm    │  │  per permission│  │  @hasallroles       │  │
│  │  rbac.team    │  │  @can ->can()  │  │  @haspermission     │  │
│  └──────┬───────┘  └───────┬────────┘  └──────────┬──────────┘  │
│         │                  │                       │              │
│  ┌──────▼──────────────────▼───────────────────────▼──────────┐  │
│  │                    HasRoles Trait                           │  │
│  │  assignRole() · removeRole() · hasRole() · can()            │  │
│  │  switchTeam() · syncRoles() · hasPermissionTo()             │  │
│  └──────────────────────────┬─────────────────────────────────┘  │
│                              │                                    │
│  ┌───────────────────────────▼────────────────────────────────┐  │
│  │                    Service Layer                             │  │
│  │  RoleService │ PermissionService │ AssignmentService         │  │
│  │  TeamService │ CacheService      │ AuditService              │  │
│  └──────────────────────────┬─────────────────────────────────┘  │
│                              │                                    │
│  ┌───────────────────────────▼────────────────────────────────┐  │
│  │                   Eloquent Models                            │  │
│  │  Role │ Permission │ Team │ RoleHierarchy │ AuditLog        │  │
│  └──────────────────────────┬─────────────────────────────────┘  │
│                              │                                    │
│  ┌───────────────────────────▼────────────────────────────────┐  │
│  │              Database (MySQL / PostgreSQL / SQLite)          │  │
│  │  9 tables: teams, roles, permissions, role_permission,      │  │
│  │  role_hierarchy, model_roles, model_permissions,            │  │
│  │  model_teams, rbac_audit_log                                │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
```

---

## Why This Architecture

### Service Layer (Not Fat Models)

Business logic lives in dedicated service classes, not in model methods or traits. This was a deliberate choice:

- **Testability** — Services can be unit-tested in isolation with mocked dependencies.
- **Composability** — The `AssignmentService` depends on `RoleService`, `CacheService`, and `AuditService` — each with a single responsibility.
- **Extensibility** — Adding a new assignment strategy (e.g., conditional permissions) means modifying `AssignmentService`, not the trait.

The `HasRoles` trait stays thin — it validates the model, delegates to services, and provides super-admin bypass logic.

### Trait + Service Pattern

The traits (`HasRoles`, `HasPermissions`, `HasTeams`) are the developer-facing API. They provide an expressive, fluent interface on the User model while delegating all logic to the service layer:

```php
// Trait (thin — delegates to service)
public function assignRole(string|Role $role, ?Team $team = null, ...): void
{
    $this->ensureRolesTraitInModel(); // Guard clause
    app(AssignmentService::class)->assignRole($this, $role, $team, ...);
}

// Service (thick — all business logic)
public function assignRole(Model $model, ...): void
{
    DB::table('model_roles')->insert([...]);
    $this->bustModelCache($model);
    $this->audit->log('role.assigned', ...);
}
```

### Observer Pattern for Audit Logging

Model observers (`RoleObserver`, `PermissionObserver`, `TeamObserver`) automatically log all model lifecycle events (created, updated, deleted, restored). This captures changes made through Artisan commands, tinker, or direct Eloquent usage — not just through the service layer.

The service layer *also* logs its own assignments/revocations, providing redundant audit coverage at the business-logic level.

---

## Key Design Decisions

### UUIDs Over Auto-Increment

All primary keys use UUIDs generated via `Str::uuid()` in model `boot()` methods.

**Why:** In enterprise / distributed environments, auto-incrementing IDs leak information (row count, insertion order) and cause merge conflicts across environments. UUIDs are safe for multi-database synchronization and API exposure.

**Trade-off:** Slightly larger index size and marginally slower joins. Acceptable for an authorization table that will rarely exceed hundreds of thousands of rows.

### Closure Table for Role Hierarchy

Role hierarchy is stored in a closure table (`role_hierarchy`) with `(ancestor_id, descendant_id, depth)` tuples.

**Why not nested set?** Nested sets are fast for reads but expensive for writes (re-indexing). Roles change infrequently in most applications, so write performance is less critical.

**Why not adjacency list?** Adjacency lists require recursive CTEs or N+1 queries to resolve inheritance. A closure table resolves the full ancestor chain in a single non-recursive query:

```sql
SELECT p.name
FROM role_hierarchy rh
JOIN role_permission rp ON rp.role_id = rh.ancestor_id
JOIN permissions p ON p.id = rp.permission_id
WHERE rh.descendant_id = ?
  AND rh.depth > 0
```

**Why DAG (not tree)?** A role can inherit from multiple parents. The closure table naturally supports directed acyclic graphs. Cycle detection is performed on write via `RoleCycleException`.

### Polymorphic Pivot Tables

`model_roles`, `model_permissions`, and `model_teams` use polymorphic relationships (`model_type`, `model_id`).

**Why:** This allows any Eloquent model (User, Bot, ServiceAccount, ApiClient) to carry role/permission/team assignments. The package doesn't hardcode a dependency on `App\Models\User`.

**Trade-off:** No foreign key constraint on `model_id` (since it references different tables). Application-level enforcement and testing compensate for this.

### Time-Bound Assignments at the Database Level

Expired/future-dated assignments are filtered at the query level using bound parameters:

```php
$now = now();
->where(function ($q) use ($now) {
    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
})
->where(function ($q) use ($now) {
    $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
})
```

**Why bound parameters instead of `NOW()`:** `NOW()` returns the database server's time. In distributed setups (RDS, read replicas, multi-region), clock skew between app and DB servers can cause permissions to activate/deactivate seconds late. Binding the app server's `now()` eliminates this.

**Why not just check in PHP?** DB-level filtering ensures that even raw queries, exports, and admin tools respect time bounds. Application-level checks alone would leave gaps.

### Cache-First Reads with Expiry Safety

The `CacheService` provides a structured caching layer with several safety mechanisms:

1. **Versioned keys** — `rbac:{version}:user:{id}:permissions`. Incrementing the version key (via `rbac:cache:clear`) invalidates all entries atomically.

2. **Expiry-aware cache reads** — Cached permission payloads include the nearest `expires_at` timestamp. On read, if `expires_at` is in the past, the entry is treated as a cache miss and re-resolved. If `expires_at` is in the near future, the entry is re-cached with a shorter TTL.

3. **Mutation-triggered invalidation** — Every `assignRole()`, `removeRole()`, `syncRoles()`, etc. call busts the affected user's cache keys.

4. **Scheduled prune as safety net** — `rbac:prune-expired` runs every minute, deleting expired rows and busting affected caches. This catches any edge case where the shorter-TTL re-cache window is still in flight.

**Why a custom cache service instead of Laravel's Cache facade directly?** The custom service encapsulates the versioning strategy, expiry-aware TTL resolution, and pattern-based key busting. Consumers call `$this->cache->remember()` or `$this->cache->forget()` without worrying about key format.

### Guard-Aware Scoping

Every query that resolves roles or permissions includes a `WHERE guard_name = ?` clause. This ensures:

- A user with the `editor` role on the `web` guard doesn't inherit those permissions when using an `api` guard.
- Permissions created for one guard (e.g., `api.posts.create`) are invisible to another (e.g., `web.posts.create`).

**Why:** Laravel guards are the standard way to separate authentication contexts. RBAC should respect that boundary natively.

### Soft Deletes on All Mutable Entities

`roles`, `permissions`, `teams`, `model_roles`, and `model_permissions` all use `SoftDeletes`.

**Why:**
- Audit log entries reference entity IDs. Hard deleting a role would orphan those references.
- Accidental deletion is recoverable.
- Historical assignments remain queryable for compliance.

### Super-Admin as Opt-In

The `super_admin_role` config defaults to `null`. When a user holds the configured super-admin role, all Gate checks return `true` immediately. Every bypass is logged with action `superadmin.bypass`.

**Why not default to `super-admin`?** In enterprise environments, accidentally granting super-admin access is a critical security incident. Requiring explicit opt-in prevents this.

**Why log every bypass?** If super-admin is enabled, compliance requires knowing *when* it was used.

### Application-Level Time (Not SQL NOW())

All time-bound resolution uses `now()` from the PHP application layer, passed as a bound parameter:

```php
$now = now(); // App server time
->where('starts_at', '<=', $now);
->where('expires_at', '>', $now);
```

**Why not `NOW()`:** See [Time-Bound Assignments](#time-bound-assignments-at-the-database-level). Clock skew between app and DB servers in distributed deployments is a real production issue.

### Deferred Gate Registration

Gate auto-registration uses `$this->callAfterResolving('gate', ...)` instead of direct registration in `boot()`.

**Why:** During `php artisan migrate`, the Gate may attempt to resolve the `permissions` table before migrations have run, causing a `QueryException`. By deferring, gates are only registered when the Gate is first actually resolved (i.e., during a real HTTP/console request with the DB available).

---

## Permission Resolution Flow

When `$user->hasPermissionTo('edit-posts')` is called:

```
1. Super-admin check
   └─ Does user hold super_admin_role? → return true (log bypass)

2. Cache lookup
   └─ rbac:{version}:user:{id}:permissions → HIT? → check if 'edit-posts' in set
      └─ Cache entry has expired? → treat as MISS

3. (Cache miss) Resolve from database:
   a. Direct permissions:
      SELECT permissions.name FROM model_permissions
      JOIN permissions ON permissions.id = model_permissions.permission_id
      WHERE model_type = ? AND model_id = ? AND guard_name = ?
        AND (starts_at IS NULL OR starts_at <= :now)
        AND (expires_at IS NULL OR expires_at > :now)

   b. Role-based permissions:
      SELECT role_id FROM model_roles
      WHERE model_type = ? AND model_id = ? AND guard_name = ?
        AND (starts_at IS NULL OR starts_at <= :now)
        AND (expires_at IS NULL OR expires_at > :now)

   c. Role hierarchy expansion:
      SELECT ancestor_id FROM role_hierarchy
      WHERE descendant_id IN (:role_ids) AND depth > 0

   d. Merge all role IDs (direct + ancestors):
      SELECT permissions.name FROM role_permission
      JOIN permissions ON permissions.id = role_permission.permission_id
      WHERE role_id IN (:all_role_ids)

   e. Union (a) and (d) results → unique permission names

4. Cache the result with TTL = min(config ttl, nearest expires_at)

5. Return bool: does the set contain 'edit-posts'?
```

**Key insight:** Team scoping adds a `AND team_id = ?` clause to steps (a) and (b). The `switchTeam()` method on the trait sets the active team context, which resolution methods use automatically.

---

## Service Layer Design

| Service | Responsibility | Key Methods |
|---|---|---|
| **CacheService** | Versioned caching, key busting, expiry-aware TTL | `remember()`, `forget()`, `flush()`, `resolveTtl()` |
| **AuditService** | Append-only audit logging with context detection | `log()`, `forUser()`, `prune()` |
| **RoleService** | Role CRUD, permission assignment, hierarchy management | `create()`, `findBySlug()`, `setParent()`, `getChildRoles()`, `getCachedPermissions()` |
| **PermissionService** | Permission CRUD, retrieval | `create()`, `findByName()`, `getAllGrouped()` |
| **AssignmentService** | Role/permission assignment & revocation, time-bound resolution | `assignRole()`, `removeRole()`, `syncRoles()`, `givePermissionTo()`, `revokePermissionTo()`, `getPermissions()`, `getRoles()` |
| **TeamService** | Team CRUD, membership management | `create()`, `addMember()`, `removeMember()`, `isMember()`, `isOwner()` |

**Dependency chain:**
```
AssignmentService → RoleService, CacheService, AuditService
RoleService → CacheService, AuditService
PermissionService → CacheService, AuditService
TeamService → AuditService
CacheService → (standalone)
AuditService → (standalone)
```

---

## Middleware Design

Three middleware classes handle route-level authorization:

| Middleware | Alias | Purpose |
|---|---|---|
| `RoleMiddleware` | `rbac.role` | Requires user to hold one of the specified roles |
| `PermissionMiddleware` | `rbac.permission` | Requires user to hold one of the specified permissions |
| `TeamMiddleware` | `rbac.team` | Requires user to be a member of the route's team; switches context |

All three abort with 403 if the check fails. The `TeamMiddleware` additionally calls `$user->switchTeam($team)` so all downstream permission resolution uses the correct team context.

**Design note:** Middleware is intentionally simple. Complex authorization logic (e.g., "edit if owner OR has admin role") belongs in Policies, not middleware.

---

## Audit Logging Strategy

Audit logging operates at two levels:

1. **Model Observers** — Capture all lifecycle events (`created`, `updated`, `deleted`, `restored`) on `Role`, `Permission`, and `Team` models. These fire regardless of how the model was modified (service, tinker, direct query).

2. **Service Layer** — Every assignment/revocation through `AssignmentService`, `RoleService`, `TeamService`, etc. logs its own audit entries with structured data.

**Context detection:** The `AuditService` automatically detects the execution environment:
- `http` — Standard web request
- `api` — Request has a bearer token
- `cli` — Running in console (artisan commands, tinker)
- `queue` — Running in a queue worker

**Why both levels?** Observers catch everything (even direct DB manipulation). Service logs provide richer business context (e.g., which specific assignment was revoked). Redundancy is a feature in audit logging.

---

## Database Schema Rationale

### The 9 Tables

| Table | Purpose | Why Separate? |
|---|---|---|
| `teams` | Team/tenant entities | Scoped permissions need team context |
| `roles` | Role definitions per guard | Guard scoping requires row-level isolation |
| `permissions` | Permission definitions per guard | Group column for UI organization |
| `role_permission` | Role ↔ Permission many-to-many | Standard pivot; UUID PK for consistency |
| `role_hierarchy` | Closure table for DAG inheritance | Single-query ancestor resolution |
| `model_roles` | Any model ↔ Role polymorphic pivot | Time-bound, team-scoped, guard-aware |
| `model_permissions` | Any model ↔ Permission polymorphic pivot | Time-bound, team-scoped, guard-aware |
| `model_teams` | Any model ↔ Team polymorphic pivot | Membership with owner flag |
| `rbac_audit_log` | Append-only audit trail | Actor, entity, snapshots, context, IP |

### No `parent_id` on Roles

The original plan included a `parent_id` on the `roles` table, but this was removed in favor of the `role_hierarchy` closure table. The closure table is strictly superior:

- **Supports DAG** (multiple parents), not just trees.
- **Single-query resolution** of the full ancestor chain.
- **Cycle detection** on write.
- **No recursive queries** at runtime.

### Why `model_roles` and `model_permissions` Are Separate

Some packages combine these into a single `model_has_roles` table with a type column. Separate tables were chosen for:

1. **Clarity** — Different semantics (role vs. permission), different query patterns.
2. **Performance** — No need to filter by type when querying role assignments.
3. **Time-bound columns** — `starts_at` and `expires_at` on both tables. Roles and permissions have independent expiration.

---

## Performance Characteristics

| Operation | Expected Latency | Notes |
|---|---|---|
| Permission check (cached) | < 1 ms | In-memory hash lookup on serialized set |
| Permission check (uncached) | < 10 ms | Single query with joins, no recursion |
| Role assignment | < 50 ms | Write + cache invalidation |
| Full hierarchy resolution | < 5 ms | Closure table query |
| Cache warm (10k users) | < 30 s | Batch query + pipeline |

**Cache store:** Configurable. Works with any Laravel cache driver (Redis, Memcached, database, array, file). Tag-based invalidation when the store supports it; prefix-based with version key fallback.

---

## Migration Guide from spatie/laravel-permission

If you're currently using `spatie/laravel-permission`, here's what's different:

| Concept | spatie/laravel-permission | Metrial RBAC |
|---|---|---|
| Models | `Role`, `Permission` (single guard) | `Role`, `Permission`, `Team`, `AuditLog` with guard scoping |
| User relation | `roles()`, `permissions()` (direct) | `roles()`, `permissions()`, `teams()` (polymorphic) |
| Teams | Basic support | First-class with context switching |
| Hierarchy | Not supported | DAG via closure table |
| Time-bound | Not supported | Native `starts_at` / `expires_at` |
| Audit log | Not included | Full audit trail |
| Super-admin | Not included | Opt-in with traceable bypass |
| Soft deletes | Not included | All mutable entities |

**Data migration:** There's no automatic migration tool. Export your spatie data and re-create it through the Metrial API. The schema is different enough that a direct SQL migration would be error-prone.
