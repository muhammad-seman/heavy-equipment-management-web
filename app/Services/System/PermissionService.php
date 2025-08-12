<?php

namespace App\Services\System;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionService
{
    /**
     * Get filtered and paginated permissions
     */
    public function getFilteredPermissions(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Permission::with(['roles'])->withCount(['roles']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Create new permission with validation
     */
    public function createPermission(array $data, User $createdBy): Permission
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validatePermissionData($data);

            // Normalize data
            $data = $this->normalizePermissionData($data);

            // Create permission
            $permission = Permission::create($data);

            DB::commit();

            return $permission->load(['roles'])->loadCount(['roles']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update permission with business logic
     */
    public function updatePermission(Permission $permission, array $data, User $updatedBy): Permission
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validatePermissionData($data, $permission);

            // Check if this is a system permission
            $this->validateSystemPermissionUpdate($permission, $data);

            // Normalize data
            $data = $this->normalizePermissionData($data);

            // Update permission
            $permission->update($data);

            DB::commit();

            return $permission->fresh(['roles'])->loadCount(['roles']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete permission with safety checks
     */
    public function deletePermission(Permission $permission, User $deletedBy): bool
    {
        DB::beginTransaction();

        try {
            // Check if this is a system permission
            $this->validateSystemPermissionDelete($permission);

            // Check if permission has any roles assigned
            $roleCount = $permission->roles()->count();
            if ($roleCount > 0) {
                throw new \InvalidArgumentException(
                    "Cannot delete permission. {$roleCount} role(s) have this permission assigned."
                );
            }

            $permission->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get permission with complete details
     */
    public function getPermissionWithDetails(Permission $permission): Permission
    {
        return $permission->load(['roles.users'])
                          ->loadCount(['roles']);
    }

    /**
     * Get permissions grouped by modules/categories
     */
    public function getPermissionsByModules(): array
    {
        $permissions = Permission::with(['roles'])->withCount(['roles'])->get();
        
        $modules = [];
        
        foreach ($permissions as $permission) {
            $category = $this->getPermissionCategory($permission->name);
            
            if (!isset($modules[$category])) {
                $modules[$category] = [
                    'name' => $category,
                    'display_name' => $this->getCategoryDisplayName($category),
                    'permissions' => [],
                    'total_permissions' => 0,
                    'assigned_permissions' => 0,
                ];
            }
            
            $modules[$category]['permissions'][] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'description' => $this->getPermissionDescription($permission->name),
                'roles_count' => $permission->roles_count,
                'is_system_permission' => $this->isSystemPermission($permission->name),
            ];
            
            $modules[$category]['total_permissions']++;
            if ($permission->roles_count > 0) {
                $modules[$category]['assigned_permissions']++;
            }
        }
        
        return array_values($modules);
    }

    /**
     * Get permission statistics for dashboard
     */
    public function getPermissionStatistics(): array
    {
        return [
            'total_permissions' => Permission::count(),
            'system_permissions' => $this->getSystemPermissionsCount(),
            'custom_permissions' => Permission::count() - $this->getSystemPermissionsCount(),
            'permissions_by_category' => $this->getAllPermissionsByCategory(),
            'unassigned_permissions' => Permission::doesntHave('roles')->count(),
            'most_used_permissions' => $this->getMostUsedPermissions(),
            'recently_created' => Permission::where('created_at', '>=', now()->subDays(7))->count(),
            'usage_distribution' => $this->getPermissionUsageDistribution(),
        ];
    }

    /**
     * Get roles assigned to a permission
     */
    public function getPermissionRoles(Permission $permission): array
    {
        return $permission->roles()
            ->withCount(['users'])
            ->get(['id', 'name', 'display_name', 'description'])
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'users_count' => $role->users_count,
                    'is_system_role' => $this->isSystemRole($role->name),
                ];
            })
            ->toArray();
    }

    /**
     * Search permissions with basic info
     */
    public function searchPermissions(string $query, int $limit = 20): array
    {
        return Permission::where('name', 'like', '%' . $query . '%')
            ->orWhere('display_name', 'like', '%' . $query . '%')
            ->limit($limit)
            ->get(['id', 'name', 'display_name'])
            ->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'display_name' => $permission->display_name,
                    'category' => $this->getPermissionCategory($permission->name),
                    'description' => $this->getPermissionDescription($permission->name),
                ];
            })
            ->toArray();
    }

    /**
     * Get permissions by category
     */
    public function getPermissionsByCategory(string $category): array
    {
        $permissions = Permission::with(['roles'])->withCount(['roles'])->get();
        
        return $permissions->filter(function ($permission) use ($category) {
            return $this->getPermissionCategory($permission->name) === $category;
        })->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'description' => $this->getPermissionDescription($permission->name),
                'roles_count' => $permission->roles_count,
                'is_system_permission' => $this->isSystemPermission($permission->name),
            ];
        })->values()->toArray();
    }

    /**
     * Sync system permissions with available features
     */
    public function syncSystemPermissions(User $user): array
    {
        DB::beginTransaction();

        try {
            $systemPermissions = $this->getSystemPermissionsList();
            $existingPermissions = Permission::pluck('name')->toArray();
            
            $created = [];
            $skipped = [];
            
            foreach ($systemPermissions as $permissionData) {
                if (!in_array($permissionData['name'], $existingPermissions)) {
                    Permission::create([
                        'name' => $permissionData['name'],
                        'display_name' => $permissionData['display_name'],
                        'guard_name' => 'web',
                    ]);
                    $created[] = $permissionData['name'];
                } else {
                    $skipped[] = $permissionData['name'];
                }
            }

            DB::commit();

            return [
                'created_count' => count($created),
                'skipped_count' => count($skipped),
                'created_permissions' => $created,
                'total_system_permissions' => count($systemPermissions),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get permission usage analysis
     */
    public function getPermissionUsageAnalysis(Permission $permission): array
    {
        $roles = $permission->roles()->withCount(['users'])->get();
        $totalUsers = $roles->sum('users_count');
        
        return [
            'permission_info' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'category' => $this->getPermissionCategory($permission->name),
                'is_system_permission' => $this->isSystemPermission($permission->name),
            ],
            'usage_stats' => [
                'assigned_to_roles' => $permission->roles()->count(),
                'total_users_affected' => $totalUsers,
                'roles_distribution' => $roles->map(function ($role) {
                    return [
                        'role_name' => $role->name,
                        'role_display_name' => $role->display_name,
                        'users_count' => $role->users_count,
                        'is_system_role' => $this->isSystemRole($role->name),
                    ];
                }),
                'usage_level' => $this->calculateUsageLevel($permission->roles()->count(), $totalUsers),
            ],
        ];
    }

    /**
     * Bulk assign permissions to roles
     */
    public function bulkAssignPermissions(array $permissionIds, array $roleIds, User $user): array
    {
        DB::beginTransaction();

        try {
            $permissions = Permission::whereIn('id', $permissionIds)->get();
            $roles = Role::whereIn('id', $roleIds)->get();
            
            $assignments = [];
            
            foreach ($roles as $role) {
                foreach ($permissions as $permission) {
                    if (!$role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                        $assignments[] = [
                            'role' => $role->name,
                            'permission' => $permission->name,
                        ];
                    }
                }
            }

            DB::commit();

            return [
                'assigned_count' => count($assignments),
                'roles_affected' => $roles->count(),
                'permissions_assigned' => $permissions->count(),
                'assignments' => $assignments,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Apply filters to permissions query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('display_name', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['guard_name'])) {
            $query->where('guard_name', $filters['guard_name']);
        }

        if (!empty($filters['category'])) {
            $query->where('name', 'like', $filters['category'] . '.%');
        }

        if (isset($filters['has_roles']) && $filters['has_roles'] !== null) {
            if ($filters['has_roles']) {
                $query->has('roles');
            } else {
                $query->doesntHave('roles');
            }
        }
    }

    /**
     * Apply sorting to permissions query
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $allowedSorts = ['name', 'display_name', 'created_at', 'roles_count'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    /**
     * Validate permission data
     */
    private function validatePermissionData(array $data, ?Permission $permission = null): void
    {
        // Validate permission name format
        if (isset($data['name']) && !preg_match('/^[a-z_]+\.[a-z_]+$/', $data['name'])) {
            throw new \InvalidArgumentException('Permission name must follow format: module.action (e.g., users.create)');
        }
    }

    /**
     * Normalize permission data
     */
    private function normalizePermissionData(array $data): array
    {
        // Normalize permission name to lowercase
        if (isset($data['name'])) {
            $data['name'] = strtolower($data['name']);
        }

        // Set default guard_name
        if (!isset($data['guard_name'])) {
            $data['guard_name'] = 'web';
        }

        // Generate display_name if not provided
        if (!isset($data['display_name']) && isset($data['name'])) {
            $data['display_name'] = $this->generateDisplayName($data['name']);
        }

        return $data;
    }

    /**
     * Validate system permission updates
     */
    private function validateSystemPermissionUpdate(Permission $permission, array $data): void
    {
        if ($this->isSystemPermission($permission->name)) {
            // Allow display_name changes but restrict name changes
            $restrictedFields = ['name', 'guard_name'];
            $hasRestrictedChanges = array_intersect_key($data, array_flip($restrictedFields));
            
            if (!empty($hasRestrictedChanges)) {
                throw new \InvalidArgumentException('Cannot modify system permission structure: ' . $permission->name);
            }
        }
    }

    /**
     * Validate system permission deletion
     */
    private function validateSystemPermissionDelete(Permission $permission): void
    {
        if ($this->isSystemPermission($permission->name)) {
            throw new \InvalidArgumentException('Cannot delete system permission: ' . $permission->name);
        }
    }

    /**
     * Check if permission is a system permission
     */
    private function isSystemPermission(string $permissionName): bool
    {
        $systemPermissions = array_column($this->getSystemPermissionsList(), 'name');
        return in_array($permissionName, $systemPermissions);
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
     * Get permission category from name
     */
    private function getPermissionCategory(string $permissionName): string
    {
        $parts = explode('.', $permissionName);
        return $parts[0] ?? 'general';
    }

    /**
     * Get category display name
     */
    private function getCategoryDisplayName(string $category): string
    {
        $displayNames = [
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

        return $displayNames[$category] ?? ucwords(str_replace('_', ' ', $category));
    }

    /**
     * Get permission description
     */
    private function getPermissionDescription(string $permissionName): string
    {
        $descriptions = [
            'equipment.view' => 'View equipment information',
            'equipment.create' => 'Create new equipment records',
            'equipment.edit' => 'Modify equipment information',
            'equipment.delete' => 'Delete equipment records',
            'equipment.operate' => 'Operate equipment',
            'equipment.assign' => 'Assign equipment to users',
            'users.view' => 'View user information',
            'users.create' => 'Create new user accounts',
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

    /**
     * Generate display name from permission name
     */
    private function generateDisplayName(string $permissionName): string
    {
        $parts = explode('.', $permissionName);
        $module = ucwords(str_replace('_', ' ', $parts[0]));
        $action = ucwords(str_replace('_', ' ', $parts[1] ?? ''));
        
        return "{$action} {$module}";
    }

    /**
     * Get system permissions count
     */
    private function getSystemPermissionsCount(): int
    {
        return count($this->getSystemPermissionsList());
    }

    /**
     * Get permissions grouped by category
     */
    private function getAllPermissionsByCategory(): array
    {
        return Permission::get()
            ->groupBy(function ($permission) {
                return $this->getPermissionCategory($permission->name);
            })
            ->map->count()
            ->toArray();
    }

    /**
     * Get most used permissions
     */
    private function getMostUsedPermissions(): array
    {
        return Permission::withCount('roles')
            ->orderBy('roles_count', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'display_name', 'roles_count'])
            ->toArray();
    }

    /**
     * Get permission usage distribution
     */
    private function getPermissionUsageDistribution(): array
    {
        return [
            'high_usage' => Permission::has('roles', '>=', 5)->count(),
            'medium_usage' => Permission::has('roles', '>=', 2)->has('roles', '<', 5)->count(),
            'low_usage' => Permission::has('roles', '=', 1)->count(),
            'unused' => Permission::doesntHave('roles')->count(),
        ];
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
     * Get system permissions list
     */
    private function getSystemPermissionsList(): array
    {
        return [
            // Equipment Management
            ['name' => 'equipment.view', 'display_name' => 'View Equipment'],
            ['name' => 'equipment.create', 'display_name' => 'Create Equipment'],
            ['name' => 'equipment.edit', 'display_name' => 'Edit Equipment'],
            ['name' => 'equipment.delete', 'display_name' => 'Delete Equipment'],
            ['name' => 'equipment.operate', 'display_name' => 'Operate Equipment'],
            ['name' => 'equipment.assign', 'display_name' => 'Assign Equipment'],
            
            // Inspection System
            ['name' => 'inspection.view', 'display_name' => 'View Inspections'],
            ['name' => 'inspection.create', 'display_name' => 'Create Inspections'],
            ['name' => 'inspection.edit', 'display_name' => 'Edit Inspections'],
            ['name' => 'inspection.delete', 'display_name' => 'Delete Inspections'],
            ['name' => 'inspection.approve', 'display_name' => 'Approve Inspections'],
            ['name' => 'inspection.templates', 'display_name' => 'Manage Inspection Templates'],
            
            // Maintenance System
            ['name' => 'maintenance.view', 'display_name' => 'View Maintenance'],
            ['name' => 'maintenance.create', 'display_name' => 'Create Maintenance'],
            ['name' => 'maintenance.edit', 'display_name' => 'Edit Maintenance'],
            ['name' => 'maintenance.delete', 'display_name' => 'Delete Maintenance'],
            ['name' => 'maintenance.approve', 'display_name' => 'Approve Maintenance'],
            ['name' => 'maintenance.schedule', 'display_name' => 'Schedule Maintenance'],
            
            // User Management
            ['name' => 'users.view', 'display_name' => 'View Users'],
            ['name' => 'users.create', 'display_name' => 'Create Users'],
            ['name' => 'users.edit', 'display_name' => 'Edit Users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users'],
            ['name' => 'users.manage_roles', 'display_name' => 'Manage User Roles'],
            
            // Role Management
            ['name' => 'roles.view', 'display_name' => 'View Roles'],
            ['name' => 'roles.create', 'display_name' => 'Create Roles'],
            ['name' => 'roles.edit', 'display_name' => 'Edit Roles'],
            ['name' => 'roles.delete', 'display_name' => 'Delete Roles'],
            
            // Permission Management
            ['name' => 'permissions.view', 'display_name' => 'View Permissions'],
            ['name' => 'permissions.assign', 'display_name' => 'Assign Permissions'],
            
            // System Administration
            ['name' => 'system.view', 'display_name' => 'View System Settings'],
            ['name' => 'system.edit', 'display_name' => 'Edit System Settings'],
            ['name' => 'system.backup', 'display_name' => 'System Backup'],
            ['name' => 'system.maintenance', 'display_name' => 'System Maintenance'],
            
            // Reporting
            ['name' => 'reports.view', 'display_name' => 'View Reports'],
            ['name' => 'reports.export', 'display_name' => 'Export Reports'],
            ['name' => 'reports.advanced', 'display_name' => 'Advanced Reports'],
            
            // Session Management
            ['name' => 'sessions.view', 'display_name' => 'View Sessions'],
            ['name' => 'sessions.create', 'display_name' => 'Create Sessions'],
            ['name' => 'sessions.edit', 'display_name' => 'Edit Sessions'],
            ['name' => 'sessions.delete', 'display_name' => 'Delete Sessions'],
        ];
    }
}