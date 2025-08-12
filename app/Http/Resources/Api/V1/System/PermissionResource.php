<?php

namespace App\Http\Resources\Api\V1\System;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Permission categorization
            'category' => $this->getPermissionCategory($this->name),
            'module' => $this->getPermissionModule($this->name),
            'action' => $this->getPermissionAction($this->name),
            'is_system_permission' => $this->isSystemPermission($this->name),

            // Description and context
            'description' => $this->getPermissionDescription($this->name),
            'context' => $this->getPermissionContext($this->name),
            'risk_level' => $this->getPermissionRiskLevel($this->name),

            // Role assignment info
            'roles_count' => $this->whenCounted('roles'),
            'roles' => $this->when(
                $this->relationLoaded('roles') && $request->user()->can('roles.view'),
                function () {
                    return $this->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'display_name' => $role->display_name,
                            'is_system_role' => $this->isSystemRole($role->name),
                            'users_count' => $role->users_count ?? null,
                        ];
                    });
                }
            ),

            // Usage statistics
            'usage_stats' => $this->when(
                $request->user()->can('permissions.view'),
                function () {
                    $rolesCount = $this->roles->count();
                    $usersCount = $this->roles->sum(function ($role) {
                        return $role->users_count ?? 0;
                    });

                    return [
                        'assigned_to_roles' => $rolesCount,
                        'total_users_affected' => $usersCount,
                        'usage_level' => $this->calculateUsageLevel($rolesCount, $usersCount),
                        'is_widely_used' => $usersCount > 10,
                        'is_critical' => $this->isCriticalPermission($this->name),
                    ];
                }
            ),

            // Related permissions
            'related_permissions' => $this->when(
                $request->user()->can('permissions.view'),
                function () {
                    return $this->getRelatedPermissions($this->name);
                }
            ),

            // Administrative info
            'administrative' => $this->when(
                $request->user()->can('permissions.view'),
                [
                    'can_delete' => $this->canBeDeleted(),
                    'deletion_blockers' => $this->getDeletionBlockers(),
                    'modification_restrictions' => $this->getModificationRestrictions(),
                    'system_info' => $this->when(
                        $this->isSystemPermission($this->name),
                        [
                            'is_core_permission' => $this->isCorePermission($this->name),
                            'auto_assigned_roles' => $this->getAutoAssignedRoles($this->name),
                        ]
                    ),
                ]
            ),
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
     * Check if role is a system role
     */
    private function isSystemRole(string $roleName): bool
    {
        $systemRoles = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
        return in_array($roleName, $systemRoles);
    }

    /**
     * Get permission description
     */
    private function getPermissionDescription(string $permissionName): string
    {
        $descriptions = [
            'equipment.view' => 'View equipment information and status',
            'equipment.create' => 'Create new equipment records',
            'equipment.edit' => 'Modify equipment information and settings',
            'equipment.delete' => 'Delete equipment records (irreversible)',
            'equipment.operate' => 'Operate equipment and log usage',
            'equipment.assign' => 'Assign equipment to users and locations',
            'inspection.view' => 'View inspection records and reports',
            'inspection.create' => 'Create new inspection records',
            'inspection.edit' => 'Modify inspection records and results',
            'inspection.delete' => 'Delete inspection records',
            'inspection.approve' => 'Approve or reject inspection results',
            'inspection.templates' => 'Manage inspection templates and checklists',
            'maintenance.view' => 'View maintenance records and schedules',
            'maintenance.create' => 'Create maintenance tasks and records',
            'maintenance.edit' => 'Modify maintenance information',
            'maintenance.delete' => 'Delete maintenance records',
            'maintenance.approve' => 'Approve maintenance requests and costs',
            'maintenance.schedule' => 'Schedule maintenance tasks',
            'users.view' => 'View user profiles and information',
            'users.create' => 'Create new user accounts',
            'users.edit' => 'Modify user profiles and settings',
            'users.delete' => 'Delete user accounts (irreversible)',
            'users.manage_roles' => 'Assign and manage user roles',
            'roles.view' => 'View roles and their permissions',
            'roles.create' => 'Create new roles',
            'roles.edit' => 'Modify role permissions and settings',
            'roles.delete' => 'Delete roles (irreversible)',
            'permissions.view' => 'View system permissions',
            'permissions.assign' => 'Assign permissions to roles',
            'system.view' => 'View system settings and configuration',
            'system.edit' => 'Modify system settings',
            'system.backup' => 'Create and manage system backups',
            'system.maintenance' => 'Put system in maintenance mode',
            'reports.view' => 'View generated reports',
            'reports.export' => 'Export reports to various formats',
            'reports.advanced' => 'Access advanced reporting features',
            'sessions.view' => 'View active user sessions',
            'sessions.create' => 'Create new sessions',
            'sessions.edit' => 'Manage session settings',
            'sessions.delete' => 'Terminate user sessions',
        ];

        return $descriptions[$permissionName] ?? 'Custom system permission';
    }

    /**
     * Get permission context
     */
    private function getPermissionContext(string $permissionName): array
    {
        $contexts = [
            'equipment.delete' => ['warning' => 'Irreversible action', 'requires_confirmation' => true],
            'users.delete' => ['warning' => 'Irreversible action', 'requires_confirmation' => true],
            'roles.delete' => ['warning' => 'Affects all assigned users', 'requires_confirmation' => true],
            'system.backup' => ['note' => 'Resource intensive operation'],
            'system.maintenance' => ['warning' => 'Affects all users', 'requires_confirmation' => true],
            'permissions.assign' => ['note' => 'High security impact'],
        ];

        return $contexts[$permissionName] ?? [];
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
     * Calculate usage level
     */
    private function calculateUsageLevel(int $roleCount, int $userCount): string
    {
        if ($userCount >= 50) return 'critical';
        if ($userCount >= 20) return 'high';
        if ($userCount >= 5) return 'medium';
        if ($userCount > 0) return 'low';
        return 'unused';
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
     * Check if permission is a core permission
     */
    private function isCorePermission(string $permissionName): bool
    {
        $corePermissions = [
            'equipment.view', 'users.view', 'roles.view', 'permissions.view',
            'system.view', 'reports.view', 'sessions.view'
        ];

        return in_array($permissionName, $corePermissions);
    }

    /**
     * Get related permissions
     */
    private function getRelatedPermissions(string $permissionName): array
    {
        $parts = explode('.', $permissionName);
        $module = $parts[0] ?? '';
        
        // Return other permissions in the same module
        $relatedPatterns = [
            $module . '.view',
            $module . '.create',
            $module . '.edit',
            $module . '.delete',
        ];

        return array_filter($relatedPatterns, fn($pattern) => $pattern !== $permissionName);
    }

    /**
     * Get auto-assigned roles for system permissions
     */
    private function getAutoAssignedRoles(string $permissionName): array
    {
        $autoAssignments = [
            'equipment.view' => ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'],
            'users.view' => ['super_admin', 'admin', 'supervisor'],
            'roles.view' => ['super_admin', 'admin'],
            'permissions.view' => ['super_admin', 'admin'],
            'system.view' => ['super_admin', 'admin'],
        ];

        return $autoAssignments[$permissionName] ?? [];
    }

    /**
     * Check if permission can be deleted
     */
    private function canBeDeleted(): bool
    {
        // System permissions cannot be deleted
        if ($this->isSystemPermission($this->name)) {
            return false;
        }

        // Permissions with roles cannot be deleted
        if ($this->roles_count > 0) {
            return false;
        }

        return true;
    }

    /**
     * Get deletion blockers
     */
    private function getDeletionBlockers(): array
    {
        $blockers = [];

        if ($this->isSystemPermission($this->name)) {
            $blockers[] = 'System permission cannot be deleted';
        }

        if ($this->roles_count > 0) {
            $blockers[] = "{$this->roles_count} role(s) have this permission assigned";
        }

        return $blockers;
    }

    /**
     * Get modification restrictions
     */
    private function getModificationRestrictions(): array
    {
        $restrictions = [];

        if ($this->isSystemPermission($this->name)) {
            $restrictions[] = 'Permission name and guard cannot be modified';
            $restrictions[] = 'Display name can be updated';
        }

        return $restrictions;
    }
}