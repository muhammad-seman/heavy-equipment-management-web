<?php

namespace App\Http\Resources\Api\V1\System;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'description' => $this->description,
            'guard_name' => $this->guard_name,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // System role identification
            'is_system_role' => $this->isSystemRole(),
            'system_info' => $this->when(
                $this->isSystemRole(),
                function () {
                    return [
                        'level' => $this->getSystemRoleLevel(),
                        'description' => $this->getSystemRoleDescription(),
                        'is_modifiable' => $this->isSystemRoleModifiable(),
                    ];
                }
            ),

            // Permissions
            'permissions' => $this->when(
                $this->relationLoaded('permissions'),
                function () {
                    return $this->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'display_name' => $permission->display_name,
                            'category' => $this->getPermissionCategory($permission->name),
                        ];
                    });
                }
            ),
            'permissions_summary' => $this->when(
                $this->relationLoaded('permissions'),
                function () {
                    $permissions = $this->permissions;
                    return [
                        'total' => $permissions->count(),
                        'by_category' => $this->groupPermissionsByCategory($permissions),
                        'critical_permissions' => $this->getCriticalPermissions($permissions),
                    ];
                }
            ),

            // User assignment info
            'users_count' => $this->whenCounted('users'),
            'users' => $this->when(
                $this->relationLoaded('users') && $request->user()->can('roles.view_users'),
                function () {
                    return $this->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->full_name,
                            'email' => $user->email,
                            'employee_id' => $user->employee_id,
                            'is_active' => $user->is_active,
                        ];
                    });
                }
            ),

            // Usage statistics
            'usage_stats' => $this->when(
                $request->user()->can('roles.view_statistics'),
                [
                    'active_users' => $this->when(
                        $this->relationLoaded('users'),
                        fn() => $this->users->where('is_active', true)->count()
                    ),
                    'last_assigned' => $this->when(
                        $this->relationLoaded('users'),
                        fn() => $this->users->max('created_at')
                    ),
                ]
            ),

            // Administrative info (admin only)
            'administrative' => $this->when(
                $request->user()->can('roles.view_admin_details'),
                [
                    'can_delete' => $this->canBeDeleted(),
                    'deletion_blockers' => $this->getDeletionBlockers(),
                    'modification_restrictions' => $this->getModificationRestrictions(),
                ]
            ),
        ];
    }

    /**
     * Check if this is a system role
     */
    private function isSystemRole(): bool
    {
        $systemRoles = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
        return in_array($this->name, $systemRoles);
    }

    /**
     * Get system role level
     */
    private function getSystemRoleLevel(): ?int
    {
        $levels = [
            'super_admin' => 1,
            'admin' => 2,
            'supervisor' => 3,
            'inspector' => 4,
            'maintenance_tech' => 4,
            'operator' => 5,
            'viewer' => 6,
        ];

        return $levels[$this->name] ?? null;
    }

    /**
     * Get system role description
     */
    private function getSystemRoleDescription(): ?string
    {
        $descriptions = [
            'super_admin' => 'Full system access with all permissions',
            'admin' => 'Administrative access to manage system and users',
            'supervisor' => 'Supervisory access to oversee operations',
            'inspector' => 'Inspection and quality control responsibilities',
            'maintenance_tech' => 'Maintenance and repair operations',
            'operator' => 'Equipment operation and basic functions',
            'viewer' => 'Read-only access to system information',
        ];

        return $descriptions[$this->name] ?? null;
    }

    /**
     * Check if system role can be modified
     */
    private function isSystemRoleModifiable(): bool
    {
        // System roles can have permissions updated but not structure
        return $this->isSystemRole();
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
            'reports' => 'Reporting',
            'sessions' => 'Session Management',
        ];

        foreach ($categories as $prefix => $category) {
            if (str_starts_with($permissionName, $prefix . '.')) {
                return $category;
            }
        }

        return 'General';
    }

    /**
     * Group permissions by category
     */
    private function groupPermissionsByCategory($permissions): array
    {
        $grouped = [];
        
        foreach ($permissions as $permission) {
            $category = $this->getPermissionCategory($permission->name);
            if (!isset($grouped[$category])) {
                $grouped[$category] = 0;
            }
            $grouped[$category]++;
        }

        return $grouped;
    }

    /**
     * Get critical permissions
     */
    private function getCriticalPermissions($permissions): array
    {
        $criticalPatterns = [
            'system.backup',
            'system.maintenance', 
            'users.delete',
            'roles.delete',
            'permissions.assign',
        ];

        return $permissions->filter(function ($permission) use ($criticalPatterns) {
            return in_array($permission->name, $criticalPatterns);
        })->pluck('name')->toArray();
    }

    /**
     * Check if role can be deleted
     */
    private function canBeDeleted(): bool
    {
        // System roles cannot be deleted
        if ($this->isSystemRole()) {
            return false;
        }

        // Roles with users cannot be deleted
        if ($this->users_count > 0) {
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

        if ($this->isSystemRole()) {
            $blockers[] = 'System role cannot be deleted';
        }

        if ($this->users_count > 0) {
            $blockers[] = "{$this->users_count} user(s) assigned to this role";
        }

        return $blockers;
    }

    /**
     * Get modification restrictions
     */
    private function getModificationRestrictions(): array
    {
        $restrictions = [];

        if ($this->isSystemRole()) {
            $restrictions[] = 'Role name and guard cannot be modified';
            $restrictions[] = 'Permissions can be updated';
        }

        return $restrictions;
    }
}