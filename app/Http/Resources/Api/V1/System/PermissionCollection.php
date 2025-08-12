<?php

namespace App\Http\Resources\Api\V1\System;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PermissionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($permission) use ($request) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'guard_name' => $permission->guard_name,
                    'created_at' => $permission->created_at->toISOString(),
                    
                    // Categorization
                    'category' => $this->getPermissionCategory($permission->name),
                    'module' => $this->getPermissionModule($permission->name),
                    'action' => $this->getPermissionAction($permission->name),
                    'is_system_permission' => $this->isSystemPermission($permission->name),
                    
                    // Usage info
                    'roles_count' => $permission->roles_count ?? 0,
                    'risk_level' => $this->getPermissionRiskLevel($permission->name),
                    'is_critical' => $this->isCriticalPermission($permission->name),
                    
                    // Status indicators
                    'status_indicators' => [
                        'is_system_permission' => $this->isSystemPermission($permission->name),
                        'has_roles' => ($permission->roles_count ?? 0) > 0,
                        'is_widely_used' => ($permission->roles_count ?? 0) > 3,
                        'can_be_deleted' => $this->canBeDeleted($permission),
                        'is_critical' => $this->isCriticalPermission($permission->name),
                    ],
                    
                    // Quick description
                    'description' => $this->getPermissionDescription($permission->name),
                ];
            }),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        $systemPermissions = $this->collection->filter(function ($permission) {
            return $this->isSystemPermission($permission->name);
        });

        $customPermissions = $this->collection->filter(function ($permission) {
            return !$this->isSystemPermission($permission->name);
        });

        // Group by categories
        $byCategory = $this->collection->groupBy(function ($permission) {
            return $this->getPermissionCategory($permission->name);
        });

        // Group by risk level
        $byRiskLevel = $this->collection->groupBy(function ($permission) {
            return $this->getPermissionRiskLevel($permission->name);
        });

        return [
            'meta' => [
                'total_permissions' => $this->collection->count(),
                'system_permissions' => $systemPermissions->count(),
                'custom_permissions' => $customPermissions->count(),
                'permissions_with_roles' => $this->collection->filter(function ($permission) {
                    return ($permission->roles_count ?? 0) > 0;
                })->count(),
                'unassigned_permissions' => $this->collection->filter(function ($permission) {
                    return ($permission->roles_count ?? 0) === 0;
                })->count(),
                'critical_permissions' => $this->collection->filter(function ($permission) {
                    return $this->isCriticalPermission($permission->name);
                })->count(),
            ],
            'categories' => $byCategory->map(function ($permissions, $category) {
                return [
                    'name' => $category,
                    'count' => $permissions->count(),
                    'assigned_count' => $permissions->filter(function ($permission) {
                        return ($permission->roles_count ?? 0) > 0;
                    })->count(),
                ];
            })->values(),
            'risk_distribution' => $byRiskLevel->map(function ($permissions, $riskLevel) {
                return [
                    'level' => $riskLevel,
                    'count' => $permissions->count(),
                ];
            })->values(),
            'module_structure' => [
                'equipment' => 'Equipment Management',
                'inspection' => 'Inspection System',
                'maintenance' => 'Maintenance System',
                'users' => 'User Management',
                'roles' => 'Role Management',
                'permissions' => 'Permission Management',
                'system' => 'System Administration',
                'reports' => 'Reporting & Analytics',
                'sessions' => 'Session Management',
            ],
        ];
    }

    /**
     * Get permission category
     */
    private function getPermissionCategory(string $permissionName): string
    {
        $categories = [
            'equipment' => 'Equipment Management',
            'inspection' => 'Inspection System',
            'maintenance' => 'Maintenance System',
            'users' => 'User Management',
            'roles' => 'Role Management',
            'permissions' => 'Permission Management',
            'system' => 'System Administration',
            'reports' => 'Reporting & Analytics',
            'sessions' => 'Session Management',
        ];

        $parts = explode('.', $permissionName);
        $module = $parts[0] ?? 'general';

        return $categories[$module] ?? ucwords(str_replace('_', ' ', $module));
    }

    /**
     * Get permission module
     */
    private function getPermissionModule(string $permissionName): string
    {
        $parts = explode('.', $permissionName);
        return $parts[0] ?? 'general';
    }

    /**
     * Get permission action
     */
    private function getPermissionAction(string $permissionName): string
    {
        $parts = explode('.', $permissionName);
        return $parts[1] ?? 'unknown';
    }

    /**
     * Check if permission is a system permission
     */
    private function isSystemPermission(string $permissionName): bool
    {
        $systemModules = ['equipment', 'inspection', 'maintenance', 'users', 'roles', 'permissions', 'system', 'reports', 'sessions'];
        $parts = explode('.', $permissionName);
        $module = $parts[0] ?? '';
        
        return in_array($module, $systemModules);
    }

    /**
     * Get permission risk level
     */
    private function getPermissionRiskLevel(string $permissionName): string
    {
        $criticalPermissions = [
            'users.delete', 'roles.delete', 'system.backup', 'system.maintenance',
            'permissions.assign', 'equipment.delete'
        ];

        $highRiskPermissions = [
            'users.create', 'users.edit', 'roles.create', 'roles.edit',
            'system.edit', 'equipment.create', 'equipment.edit'
        ];

        if (in_array($permissionName, $criticalPermissions)) {
            return 'critical';
        }

        if (in_array($permissionName, $highRiskPermissions)) {
            return 'high';
        }

        if (str_contains($permissionName, '.edit') || str_contains($permissionName, '.create')) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check if permission is critical
     */
    private function isCriticalPermission(string $permissionName): bool
    {
        $criticalPermissions = [
            'system.maintenance', 'system.backup', 'users.delete', 'roles.delete',
            'permissions.assign', 'equipment.delete'
        ];

        return in_array($permissionName, $criticalPermissions);
    }

    /**
     * Check if permission can be deleted
     */
    private function canBeDeleted($permission): bool
    {
        // System permissions cannot be deleted
        if ($this->isSystemPermission($permission->name)) {
            return false;
        }

        // Permissions with roles cannot be deleted
        if (($permission->roles_count ?? 0) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Get permission description
     */
    private function getPermissionDescription(string $permissionName): string
    {
        $descriptions = [
            'equipment.view' => 'View equipment information',
            'equipment.create' => 'Create equipment records',
            'equipment.edit' => 'Modify equipment information',
            'equipment.delete' => 'Delete equipment records',
            'equipment.operate' => 'Operate equipment',
            'equipment.assign' => 'Assign equipment to users',
            'users.view' => 'View user information',
            'users.create' => 'Create user accounts',
            'users.edit' => 'Modify user information',
            'users.delete' => 'Delete user accounts',
            'users.manage_roles' => 'Manage user roles',
            'roles.view' => 'View roles and permissions',
            'roles.create' => 'Create new roles',
            'roles.edit' => 'Modify roles',
            'roles.delete' => 'Delete roles',
            'permissions.view' => 'View permissions',
            'permissions.assign' => 'Assign permissions to roles',
            'system.view' => 'View system settings',
            'system.edit' => 'Modify system settings',
            'system.backup' => 'Create system backups',
            'system.maintenance' => 'System maintenance mode',
        ];

        return $descriptions[$permissionName] ?? 'System permission';
    }
}