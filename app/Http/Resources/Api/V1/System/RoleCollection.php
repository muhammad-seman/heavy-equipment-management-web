<?php

namespace App\Http\Resources\Api\V1\System;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RoleCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($role) use ($request) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'guard_name' => $role->guard_name,
                    'created_at' => $role->created_at->toISOString(),
                    'updated_at' => $role->updated_at->toISOString(),
                    
                    // System role identification
                    'is_system_role' => $this->isSystemRole($role->name),
                    'system_level' => $this->getSystemRoleLevel($role->name),
                    
                    // Counts
                    'users_count' => $role->users_count ?? 0,
                    'permissions_count' => $role->permissions_count ?? 0,
                    
                    // Status indicators
                    'status_indicators' => [
                        'is_system_role' => $this->isSystemRole($role->name),
                        'has_users' => ($role->users_count ?? 0) > 0,
                        'has_permissions' => ($role->permissions_count ?? 0) > 0,
                        'can_be_deleted' => $this->canBeDeleted($role),
                        'can_be_modified' => $this->canBeModified($role),
                    ],
                    
                    // Quick permission summary (basic info only)
                    'permission_summary' => $this->when(
                        $role->relationLoaded('permissions'),
                        function () use ($role) {
                            return [
                                'total' => $role->permissions->count(),
                                'categories' => $this->getPermissionCategories($role->permissions),
                            ];
                        }
                    ),
                ];
            }),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        $systemRoles = $this->collection->filter(function ($role) {
            return $this->isSystemRole($role->name);
        });

        $customRoles = $this->collection->filter(function ($role) {
            return !$this->isSystemRole($role->name);
        });

        return [
            'meta' => [
                'total_roles' => $this->collection->count(),
                'system_roles' => $systemRoles->count(),
                'custom_roles' => $customRoles->count(),
                'roles_with_users' => $this->collection->filter(function ($role) {
                    return ($role->users_count ?? 0) > 0;
                })->count(),
                'roles_without_permissions' => $this->collection->filter(function ($role) {
                    return ($role->permissions_count ?? 0) === 0;
                })->count(),
                'deletable_roles' => $this->collection->filter(function ($role) {
                    return $this->canBeDeleted($role);
                })->count(),
            ],
            'system_role_hierarchy' => [
                'super_admin' => 1,
                'admin' => 2,
                'supervisor' => 3,
                'inspector' => 4,
                'maintenance_tech' => 4,
                'operator' => 5,
                'viewer' => 6,
            ],
        ];
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
     * Get system role level
     */
    private function getSystemRoleLevel(string $roleName): ?int
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

        return $levels[$roleName] ?? null;
    }

    /**
     * Check if role can be deleted
     */
    private function canBeDeleted($role): bool
    {
        // System roles cannot be deleted
        if ($this->isSystemRole($role->name)) {
            return false;
        }

        // Roles with users cannot be deleted
        if (($role->users_count ?? 0) > 0) {
            return false;
        }

        return true;
    }

    /**
     * Check if role can be modified
     */
    private function canBeModified($role): bool
    {
        // System roles have restrictions but can be partially modified
        return true;
    }

    /**
     * Get permission categories for a role
     */
    private function getPermissionCategories($permissions): array
    {
        $categories = [];
        
        foreach ($permissions as $permission) {
            $category = $this->getPermissionCategory($permission->name);
            if (!in_array($category, $categories)) {
                $categories[] = $category;
            }
        }

        return array_values($categories);
    }

    /**
     * Get permission category
     */
    private function getPermissionCategory(string $permissionName): string
    {
        $categories = [
            'equipment' => 'Equipment',
            'inspection' => 'Inspection',
            'maintenance' => 'Maintenance',
            'users' => 'Users',
            'roles' => 'Roles',
            'permissions' => 'Permissions',
            'system' => 'System',
            'reports' => 'Reports',
            'sessions' => 'Sessions',
        ];

        foreach ($categories as $prefix => $category) {
            if (str_starts_with($permissionName, $prefix . '.')) {
                return $category;
            }
        }

        return 'General';
    }
}