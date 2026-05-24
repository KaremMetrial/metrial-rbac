<?php

namespace Metrial\RBAC\Seeders;

use Illuminate\Database\Seeder;
use Metrial\RBAC\Models\Role;
use Metrial\RBAC\Models\Permission;

class RbacDefaultSeeder extends Seeder
{
    public function run(): void
    {
        // Default roles
        $superAdmin = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'level' => 100, 'guard_name' => 'web', 'is_system' => true]
        );

        $admin = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'level' => 50, 'guard_name' => 'web', 'is_system' => true]
        );

        $editor = Role::firstOrCreate(
            ['slug' => 'editor'],
            ['name' => 'Editor', 'level' => 20, 'guard_name' => 'web']
        );

        $viewer = Role::firstOrCreate(
            ['slug' => 'viewer'],
            ['name' => 'Viewer', 'level' => 10, 'guard_name' => 'web']
        );

        // Default permissions
        $permissions = [
            ['name' => 'view-dashboard', 'group' => 'dashboard'],
            ['name' => 'manage-users', 'group' => 'users'],
            ['name' => 'view-users', 'group' => 'users'],
            ['name' => 'create-posts', 'group' => 'posts'],
            ['name' => 'edit-posts', 'group' => 'posts'],
            ['name' => 'delete-posts', 'group' => 'posts'],
            ['name' => 'view-posts', 'group' => 'posts'],
            ['name' => 'manage-roles', 'group' => 'roles'],
            ['name' => 'assign-roles', 'group' => 'roles'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                array_merge($perm, ['guard_name' => 'web'])
            );
        }

        // Assign all permissions to super-admin
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $allPermissionIds = Permission::pluck('id')->toArray();
        if ($superAdminRole) {
            foreach ($allPermissionIds as $pid) {
                \Illuminate\Support\Facades\DB::table('role_permission')->insertOrIgnore([
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'role_id' => $superAdminRole->id,
                    'permission_id' => $pid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $editorPermIds = Permission::whereIn('name', ['view-dashboard', 'view-posts', 'create-posts', 'edit-posts'])->pluck('id')->toArray();
        foreach ($editorPermIds as $pid) {
            \Illuminate\Support\Facades\DB::table('role_permission')->insertOrIgnore([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'role_id' => $editor->id,
                'permission_id' => $pid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $viewerPermIds = Permission::whereIn('name', ['view-dashboard', 'view-posts'])->pluck('id')->toArray();
        foreach ($viewerPermIds as $pid) {
            \Illuminate\Support\Facades\DB::table('role_permission')->insertOrIgnore([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'role_id' => $viewer->id,
                'permission_id' => $pid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
