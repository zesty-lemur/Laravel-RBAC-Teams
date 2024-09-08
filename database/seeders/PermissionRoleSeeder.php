<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Permission;
use App\Models\Role;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Define permissions and their sub-permissions.
     */
    const PERMISSIONS = [
        // Super Admin
        'super admin' => ['force delete'],
        // User Management
        'manage users' => ['create users', 'read users', 'update users', 'delete users'],
        // Team Management
        'manage own teams' => ['create own teams', 'read own teams', 'update own teams', 'delete own teams'],
        'manage all teams' => ['read all teams', 'update all teams', 'delete all teams'],
        // Requirement Management
        'manage own requirements' => ['create own requirements', 'read own requirements', 'update own requirements', 'delete own requirements'],
        'manage all requirements' => ['read all requirements', 'update all requirements', 'delete all requirements'],
        // Project Management
        'manage own projects' => ['create own projects', 'read own projects', 'update own projects', 'delete own projects'],
        'manage all projects' => ['read all projects', 'update all projects', 'delete all projects'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /**
         * Clear the cache of permissions.
         */
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        /**
         * Create permissions and their sub-permissions.
         */
        foreach (self::PERMISSIONS as $main => $subs) {
            Permission::create(['name' => $main]);
            foreach ($subs as $sub) {
                Permission::create(['name' => $sub]);
            }
        }

        /**
         * Create a global Super Admin role.
         */
        Role::create(['name' => 'Super Admin']);

        /**
         * Create a Project Manager role.
         */
        $this->assignPermissionsWithDependencies('Project Manager', [
            'manage own teams',
            'manage own requirements',
            'manage own projects',
        ]);

        /**
         * Create a global Staff role
         * and assign permissions to it.
         */
        $this->assignPermissionsWithDependencies('Staff', [
            'read users',
            'manage own requirements',
        ]);

        /**
         * Create a global Customer role
         * and assign permissions to it.
         */
        $this->assignPermissionsWithDependencies('Customer', [
            'manage own requirements',
            'read all requirements',
        ]);
    }

    /**
     * Assign permissions to roles with dependent permissions.
     */
    private function assignPermissionsWithDependencies($roleName, $permissions)
    {
        $role = Role::create(['name' => $roleName]);
        foreach ($permissions as $permission) {
            $role->givePermissionTo($permission);

            // Assign dependent permissions
            $dependentPermissions = $this->getDependentPermissions($permission);
            foreach ($dependentPermissions as $dependentPermission) {
                $role->givePermissionTo($dependentPermission);
            }
        }
    }

    /**
     * Get the dependent permissions of a permission.
     */
    private function getDependentPermissions($permission)
    {
        return self::PERMISSIONS[$permission] ?? [];
    }
}
