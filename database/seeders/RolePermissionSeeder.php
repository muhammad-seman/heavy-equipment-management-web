<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Equipment permissions
            'equipment.view' => 'View equipment',
            'equipment.create' => 'Create equipment',
            'equipment.edit' => 'Edit equipment',
            'equipment.delete' => 'Delete equipment',
            'equipment.operate' => 'Operate equipment',
            'equipment.assign' => 'Assign equipment',

            // Inspection permissions
            'inspection.view' => 'View inspections',
            'inspection.create' => 'Create inspections',
            'inspection.edit' => 'Edit inspections',
            'inspection.delete' => 'Delete inspections',
            'inspection.approve' => 'Approve inspections',
            'inspection.templates' => 'Manage inspection templates',

            // Maintenance permissions
            'maintenance.view' => 'View maintenance',
            'maintenance.create' => 'Create maintenance',
            'maintenance.edit' => 'Edit maintenance',
            'maintenance.delete' => 'Delete maintenance',
            'maintenance.approve' => 'Approve maintenance',
            'maintenance.schedule' => 'Schedule maintenance',

            // User management permissions
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.edit' => 'Edit users',
            'users.delete' => 'Delete users',
            'users.manage_roles' => 'Manage user roles',

            // Role management permissions
            'roles.view' => 'View roles',
            'roles.create' => 'Create roles',
            'roles.edit' => 'Edit roles',
            'roles.delete' => 'Delete roles',

            // Permission management
            'permissions.view' => 'View permissions',
            'permissions.assign' => 'Assign permissions',

            // System management
            'system.view' => 'View system settings',
            'system.edit' => 'Edit system settings',
            'system.backup' => 'Backup system',
            'system.maintenance' => 'System maintenance',

            // Reporting permissions
            'reports.view' => 'View reports',
            'reports.export' => 'Export reports',
            'reports.advanced' => 'Advanced reporting',

            // Operating sessions
            'sessions.view' => 'View operating sessions',
            'sessions.create' => 'Create operating sessions',
            'sessions.edit' => 'Edit operating sessions',
            'sessions.delete' => 'Delete operating sessions',
        ];

        foreach ($permissions as $permission => $description) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $roles = [
            'super_admin' => [
                'description' => 'Full system access',
                'permissions' => array_keys($permissions) // All permissions
            ],
            'admin' => [
                'description' => 'Administrative access',
                'permissions' => [
                    'equipment.view', 'equipment.create', 'equipment.edit', 'equipment.assign',
                    'inspection.view', 'inspection.create', 'inspection.edit', 'inspection.approve', 'inspection.templates',
                    'maintenance.view', 'maintenance.create', 'maintenance.edit', 'maintenance.approve', 'maintenance.schedule',
                    'users.view', 'users.create', 'users.edit', 'users.manage_roles',
                    'roles.view', 'permissions.view',
                    'system.view', 'system.edit',
                    'reports.view', 'reports.export', 'reports.advanced',
                    'sessions.view', 'sessions.create', 'sessions.edit'
                ]
            ],
            'supervisor' => [
                'description' => 'Supervisory access',
                'permissions' => [
                    'equipment.view', 'equipment.edit', 'equipment.assign',
                    'inspection.view', 'inspection.create', 'inspection.edit', 'inspection.approve',
                    'maintenance.view', 'maintenance.create', 'maintenance.edit', 'maintenance.approve',
                    'users.view',
                    'reports.view', 'reports.export',
                    'sessions.view', 'sessions.create', 'sessions.edit'
                ]
            ],
            'inspector' => [
                'description' => 'Inspection and reporting access',
                'permissions' => [
                    'equipment.view',
                    'inspection.view', 'inspection.create', 'inspection.edit',
                    'maintenance.view',
                    'reports.view',
                    'sessions.view'
                ]
            ],
            'operator' => [
                'description' => 'Equipment operation access',
                'permissions' => [
                    'equipment.view', 'equipment.operate',
                    'inspection.view', 'inspection.create',
                    'maintenance.view',
                    'sessions.view', 'sessions.create', 'sessions.edit'
                ]
            ],
            'maintenance_tech' => [
                'description' => 'Maintenance and repair access',
                'permissions' => [
                    'equipment.view', 'equipment.edit',
                    'inspection.view',
                    'maintenance.view', 'maintenance.create', 'maintenance.edit',
                    'sessions.view'
                ]
            ],
            'viewer' => [
                'description' => 'Read-only access',
                'permissions' => [
                    'equipment.view',
                    'inspection.view',
                    'maintenance.view',
                    'reports.view',
                    'sessions.view'
                ]
            ]
        ];

        foreach ($roles as $roleName => $roleData) {
            $role = Role::create([
                'name' => $roleName,
            ]);

            $role->givePermissionTo($roleData['permissions']);
        }
    }
}