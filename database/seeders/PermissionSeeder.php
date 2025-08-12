<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            ['name' => 'View Users', 'slug' => 'users.view', 'resource' => 'user', 'action' => 'read'],
            ['name' => 'Create Users', 'slug' => 'users.create', 'resource' => 'user', 'action' => 'create'],
            ['name' => 'Update Users', 'slug' => 'users.update', 'resource' => 'user', 'action' => 'update'],
            ['name' => 'Delete Users', 'slug' => 'users.delete', 'resource' => 'user', 'action' => 'delete'],
            
            // Role Management
            ['name' => 'View Roles', 'slug' => 'roles.view', 'resource' => 'role', 'action' => 'read'],
            ['name' => 'Create Roles', 'slug' => 'roles.create', 'resource' => 'role', 'action' => 'create'],
            ['name' => 'Update Roles', 'slug' => 'roles.update', 'resource' => 'role', 'action' => 'update'],
            ['name' => 'Delete Roles', 'slug' => 'roles.delete', 'resource' => 'role', 'action' => 'delete'],
            
            // Equipment Management
            ['name' => 'View Equipment', 'slug' => 'equipment.view', 'resource' => 'equipment', 'action' => 'read'],
            ['name' => 'Create Equipment', 'slug' => 'equipment.create', 'resource' => 'equipment', 'action' => 'create'],
            ['name' => 'Update Equipment', 'slug' => 'equipment.update', 'resource' => 'equipment', 'action' => 'update'],
            ['name' => 'Delete Equipment', 'slug' => 'equipment.delete', 'resource' => 'equipment', 'action' => 'delete'],
            ['name' => 'Operate Equipment', 'slug' => 'equipment.operate', 'resource' => 'equipment', 'action' => 'operate'],
            
            // Inspection Management
            ['name' => 'View Inspections', 'slug' => 'inspections.view', 'resource' => 'inspection', 'action' => 'read'],
            ['name' => 'Create Inspections', 'slug' => 'inspections.create', 'resource' => 'inspection', 'action' => 'create'],
            ['name' => 'Update Inspections', 'slug' => 'inspections.update', 'resource' => 'inspection', 'action' => 'update'],
            ['name' => 'Delete Inspections', 'slug' => 'inspections.delete', 'resource' => 'inspection', 'action' => 'delete'],
            ['name' => 'Perform Inspections', 'slug' => 'inspections.perform', 'resource' => 'inspection', 'action' => 'inspect'],
            ['name' => 'Approve Inspections', 'slug' => 'inspections.approve', 'resource' => 'inspection', 'action' => 'approve'],
            
            // Maintenance Management
            ['name' => 'View Maintenance', 'slug' => 'maintenance.view', 'resource' => 'maintenance', 'action' => 'read'],
            ['name' => 'Create Maintenance', 'slug' => 'maintenance.create', 'resource' => 'maintenance', 'action' => 'create'],
            ['name' => 'Update Maintenance', 'slug' => 'maintenance.update', 'resource' => 'maintenance', 'action' => 'update'],
            ['name' => 'Delete Maintenance', 'slug' => 'maintenance.delete', 'resource' => 'maintenance', 'action' => 'delete'],
            ['name' => 'Schedule Maintenance', 'slug' => 'maintenance.schedule', 'resource' => 'maintenance', 'action' => 'schedule'],
            ['name' => 'Approve Maintenance', 'slug' => 'maintenance.approve', 'resource' => 'maintenance', 'action' => 'approve'],
            
            // Reports
            ['name' => 'View Reports', 'slug' => 'reports.view', 'resource' => 'report', 'action' => 'read'],
            ['name' => 'Generate Reports', 'slug' => 'reports.generate', 'resource' => 'report', 'action' => 'create'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'resource' => 'report', 'action' => 'export'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']], 
                array_merge($permission, ['description' => $permission['name']])
            );
        }
    }
}
