<?php

namespace App\Services\System;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleService
{
    /**
     * Get filtered and paginated roles
     */
    public function getFilteredRoles(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Role::with(['permissions'])->withCount(['users', 'permissions']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Create new role with validation
     */
    public function createRole(array $data, User $createdBy): Role
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateRoleData($data);

            // Normalize data
            $data = $this->normalizeRoleData($data);

            // Create role
            $role = Role::create($data);

            // Assign permissions if provided
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            DB::commit();

            return $role->load(['permissions'])->loadCount(['users', 'permissions']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update role with business logic
     */
    public function updateRole(Role $role, array $data, User $updatedBy): Role
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateRoleData($data, $role);

            // Check if this is a system role
            $this->validateSystemRoleUpdate($role, $data);

            // Normalize data
            $data = $this->normalizeRoleData($data);

            // Update role (exclude permissions)
            $roleUpdateData = $data;
            unset($roleUpdateData['permissions']);
            $role->update($roleUpdateData);

            // Update permissions if provided
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            DB::commit();

            return $role->fresh(['permissions'])->loadCount(['users', 'permissions']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete role with safety checks
     */
    public function deleteRole(Role $role, User $deletedBy): bool
    {
        DB::beginTransaction();

        try {
            // Check if this is a system role
            $this->validateSystemRoleDelete($role);

            // Check if role has any users assigned
            $userCount = $role->users()->count();
            if ($userCount > 0) {
                throw new \InvalidArgumentException(
                    "Cannot delete role. {$userCount} user(s) are assigned to this role."
                );
            }

            $role->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update role permissions
     */
    public function updateRolePermissions(Role $role, array $permissions, User $updatedBy): Role
    {
        DB::beginTransaction();

        try {
            // Check if this is a system role
            $this->validateSystemRoleUpdate($role, ['permissions' => $permissions]);

            // Validate permissions exist
            $validPermissions = Permission::whereIn('name', $permissions)->pluck('name')->toArray();
            $invalidPermissions = array_diff($permissions, $validPermissions);
            
            if (!empty($invalidPermissions)) {
                throw new \InvalidArgumentException('Invalid permissions: ' . implode(', ', $invalidPermissions));
            }

            $role->syncPermissions($permissions);

            DB::commit();

            return $role->fresh(['permissions'])->loadCount(['users', 'permissions']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get role with complete details
     */
    public function getRoleWithDetails(Role $role): Role
    {
        return $role->load(['permissions', 'users'])
                   ->loadCount(['users', 'permissions']);
    }

    /**
     * Get role statistics for dashboard
     */
    public function getRoleStatistics(): array
    {
        return [
            'total_roles' => Role::count(),
            'system_roles' => $this->getSystemRolesCount(),
            'custom_roles' => Role::count() - $this->getSystemRolesCount(),
            'roles_by_user_count' => $this->getRolesByUserCount(),
            'most_used_roles' => $this->getMostUsedRoles(),
            'roles_without_users' => Role::doesntHave('users')->count(),
            'permissions_distribution' => $this->getPermissionsDistribution(),
        ];
    }

    /**
     * Get users assigned to a role
     */
    public function getRoleUsers(Role $role): array
    {
        return $role->users()
            ->with(['roles'])
            ->get(['id', 'first_name', 'last_name', 'email', 'employee_id', 'is_active'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'is_active' => $user->is_active,
                    'other_roles' => $user->roles->where('id', '!=', $user->id)->pluck('name'),
                ];
            })
            ->toArray();
    }

    /**
     * Search roles with basic info
     */
    public function searchRoles(string $query, int $limit = 10): array
    {
        return Role::where('name', 'like', '%' . $query . '%')
            ->orWhere('display_name', 'like', '%' . $query . '%')
            ->limit($limit)
            ->get(['id', 'name', 'display_name', 'description'])
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                ];
            })
            ->toArray();
    }

    /**
     * Get role hierarchy and structure
     */
    public function getRoleHierarchy(): array
    {
        $systemRoles = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
        
        $roles = Role::with(['permissions'])->withCount(['users'])->get();

        return [
            'system_roles' => $roles->whereIn('name', $systemRoles)->values(),
            'custom_roles' => $roles->whereNotIn('name', $systemRoles)->values(),
            'hierarchy_levels' => [
                'super_admin' => ['level' => 1, 'description' => 'Full system access'],
                'admin' => ['level' => 2, 'description' => 'Administrative access'],
                'supervisor' => ['level' => 3, 'description' => 'Supervisory access'],
                'inspector' => ['level' => 4, 'description' => 'Inspection and quality control'],
                'maintenance_tech' => ['level' => 4, 'description' => 'Maintenance operations'],
                'operator' => ['level' => 5, 'description' => 'Equipment operation'],
                'viewer' => ['level' => 6, 'description' => 'Read-only access'],
            ],
        ];
    }

    /**
     * Clone a role with all its permissions
     */
    public function cloneRole(Role $sourceRole, array $data, User $createdBy): Role
    {
        DB::beginTransaction();

        try {
            // Create new role with provided data
            $newRole = Role::create([
                'name' => $data['name'],
                'display_name' => $data['display_name'] ?? $sourceRole->display_name . ' (Copy)',
                'description' => $data['description'] ?? $sourceRole->description,
                'guard_name' => $sourceRole->guard_name,
            ]);

            // Copy all permissions from source role
            $permissions = $sourceRole->permissions()->pluck('name')->toArray();
            if (!empty($permissions)) {
                $newRole->syncPermissions($permissions);
            }

            DB::commit();

            return $newRole->load(['permissions'])->loadCount(['users', 'permissions']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Apply filters to roles query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('display_name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['guard_name'])) {
            $query->where('guard_name', $filters['guard_name']);
        }

        if (isset($filters['has_users']) && $filters['has_users'] !== null) {
            if ($filters['has_users']) {
                $query->has('users');
            } else {
                $query->doesntHave('users');
            }
        }

        if (isset($filters['has_permissions']) && $filters['has_permissions'] !== null) {
            if ($filters['has_permissions']) {
                $query->has('permissions');
            } else {
                $query->doesntHave('permissions');
            }
        }
    }

    /**
     * Apply sorting to roles query
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $allowedSorts = [
            'name', 'display_name', 'created_at', 'users_count', 'permissions_count'
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    /**
     * Validate role data
     */
    private function validateRoleData(array $data, ?Role $role = null): void
    {
        // Validate role name format
        if (isset($data['name']) && !preg_match('/^[a-z_]+$/', $data['name'])) {
            throw new \InvalidArgumentException('Role name must contain only lowercase letters and underscores');
        }

        // Check for reserved role names
        $reservedNames = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
        if (isset($data['name']) && in_array($data['name'], $reservedNames) && (!$role || $role->name !== $data['name'])) {
            throw new \InvalidArgumentException('Cannot create or modify system role: ' . $data['name']);
        }
    }

    /**
     * Normalize role data
     */
    private function normalizeRoleData(array $data): array
    {
        // Normalize role name to lowercase with underscores
        if (isset($data['name'])) {
            $data['name'] = strtolower(str_replace(' ', '_', $data['name']));
        }

        // Set default guard_name
        if (!isset($data['guard_name'])) {
            $data['guard_name'] = 'web';
        }

        return $data;
    }

    /**
     * Validate system role updates
     */
    private function validateSystemRoleUpdate(Role $role, array $data): void
    {
        $systemRoles = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
        
        if (in_array($role->name, $systemRoles)) {
            // Allow permission updates but restrict other changes
            $restrictedFields = ['name', 'guard_name'];
            $hasRestrictedChanges = array_intersect_key($data, array_flip($restrictedFields));
            
            if (!empty($hasRestrictedChanges)) {
                throw new \InvalidArgumentException('Cannot modify system role structure: ' . $role->name);
            }
        }
    }

    /**
     * Validate system role deletion
     */
    private function validateSystemRoleDelete(Role $role): void
    {
        $systemRoles = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
        
        if (in_array($role->name, $systemRoles)) {
            throw new \InvalidArgumentException('Cannot delete system role: ' . $role->name);
        }
    }

    /**
     * Get count of system roles
     */
    private function getSystemRolesCount(): int
    {
        $systemRoles = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
        return Role::whereIn('name', $systemRoles)->count();
    }

    /**
     * Get roles grouped by user count
     */
    private function getRolesByUserCount(): array
    {
        return Role::withCount('users')
            ->get()
            ->groupBy(function ($role) {
                if ($role->users_count === 0) return 'no_users';
                if ($role->users_count <= 5) return '1_to_5_users';
                if ($role->users_count <= 20) return '6_to_20_users';
                return 'more_than_20_users';
            })
            ->map->count()
            ->toArray();
    }

    /**
     * Get most used roles
     */
    private function getMostUsedRoles(): array
    {
        return Role::withCount('users')
            ->orderBy('users_count', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'display_name', 'users_count'])
            ->toArray();
    }

    /**
     * Get permissions distribution across roles
     */
    private function getPermissionsDistribution(): array
    {
        return [
            'total_permissions' => Permission::count(),
            'unused_permissions' => Permission::doesntHave('roles')->count(),
            'average_permissions_per_role' => round(
                Role::withCount('permissions')->avg('permissions_count'), 1
            ),
            'roles_with_all_permissions' => Role::withCount('permissions')
                ->having('permissions_count', '=', Permission::count())
                ->count(),
        ];
    }
}