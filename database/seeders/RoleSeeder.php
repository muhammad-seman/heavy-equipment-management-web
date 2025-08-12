<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrator',
                'slug' => 'super-admin',
                'description' => 'Full system access with all permissions',
                'permissions' => 'all'
            ],
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Administrative access to most system features',
                'permissions' => [
                    'users.view', 'users.create', 'users.update',
                    'roles.view', 'roles.create', 'roles.update',
                    'equipment.view', 'equipment.create', 'equipment.update', 'equipment.delete',
                    'inspections.view', 'inspections.create', 'inspections.update', 'inspections.approve',
                    'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.schedule', 'maintenance.approve',
                    'reports.view', 'reports.generate', 'reports.export',
                ]
            ],
            [
                'name' => 'Supervisor',
                'slug' => 'supervisor',
                'description' => 'Supervisory access to equipment and inspections',
                'permissions' => [
                    'users.view',
                    'equipment.view', 'equipment.operate',
                    'inspections.view', 'inspections.create', 'inspections.update', 'inspections.perform', 'inspections.approve',
                    'maintenance.view', 'maintenance.create', 'maintenance.schedule',
                    'reports.view', 'reports.generate',
                ]
            ],
            [
                'name' => 'Operator',
                'slug' => 'operator',
                'description' => 'Equipment operation and basic inspection access',
                'permissions' => [
                    'equipment.view', 'equipment.operate',
                    'inspections.view', 'inspections.create', 'inspections.perform',
                    'maintenance.view',
                ]
            ],
            [
                'name' => 'Inspector',
                'slug' => 'inspector',
                'description' => 'Equipment inspection and maintenance tracking',
                'permissions' => [
                    'equipment.view',
                    'inspections.view', 'inspections.create', 'inspections.update', 'inspections.perform',
                    'maintenance.view', 'maintenance.create',
                    'reports.view',
                ]
            ]
        ];

        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(
                ['slug' => $roleData['slug']], 
                [
                    'name' => $roleData['name'],
                    'slug' => $roleData['slug'],
                    'description' => $roleData['description']
                ]
            );

            // Assign permissions to role
            if ($roleData['permissions'] === 'all') {
                $permissions = Permission::all();
                $role->permissions()->sync($permissions);
            } else {
                $permissions = Permission::whereIn('slug', $roleData['permissions'])->get();
                $role->permissions()->sync($permissions);
            }
        }
    }
}
