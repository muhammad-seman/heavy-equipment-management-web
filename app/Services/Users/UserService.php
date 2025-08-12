<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * Get filtered and paginated users
     */
    public function getFilteredUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with(['roles'])->withCount(['assignedEquipment']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Create new user with validation
     */
    public function createUser(array $data, User $createdBy): User
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateUserData($data);

            // Normalize data
            $data = $this->normalizeUserData($data);

            // Create user
            $user = User::create(array_merge($data, [
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $createdBy->id,
            ]));

            // Assign roles if provided
            if (isset($data['roles']) && is_array($data['roles'])) {
                $user->assignRole($data['roles']);
            }

            DB::commit();

            return $user->load(['roles', 'permissions'])->loadCount(['assignedEquipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update user with business logic
     */
    public function updateUser(User $user, array $data, User $updatedBy): User
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateUserData($data, $user);

            // Normalize data
            $data = $this->normalizeUserData($data);

            // Check if deactivating user with assigned equipment
            if (isset($data['is_active']) && !$data['is_active'] && $user->is_active) {
                $this->validateUserDeactivation($user);
            }

            // Update user (exclude roles)
            $userUpdateData = array_merge($data, ['updated_by' => $updatedBy->id]);
            unset($userUpdateData['roles']);
            $user->update($userUpdateData);

            // Update roles if provided
            if (isset($data['roles']) && is_array($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            DB::commit();

            return $user->fresh(['roles', 'permissions'])->loadCount(['assignedEquipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete user with safety checks
     */
    public function deleteUser(User $user, User $deletedBy): bool
    {
        DB::beginTransaction();

        try {
            // Check if user has assigned equipment
            $assignedEquipmentCount = $user->assignedEquipment()->count();
            if ($assignedEquipmentCount > 0) {
                throw new \InvalidArgumentException(
                    "Cannot delete user. User has {$assignedEquipmentCount} equipment assigned."
                );
            }

            // Soft delete user
            $user->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Restore soft-deleted user
     */
    public function restoreUser(int $userId, User $restoredBy): User
    {
        $user = User::withTrashed()->findOrFail($userId);
        
        DB::beginTransaction();

        try {
            $user->restore();
            $user->update(['updated_by' => $restoredBy->id]);

            DB::commit();

            return $user->fresh(['roles', 'permissions'])->loadCount(['assignedEquipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(User $user, User $updatedBy): User
    {
        DB::beginTransaction();

        try {
            $newStatus = !$user->is_active;

            // If deactivating, validate
            if (!$newStatus) {
                $this->validateUserDeactivation($user);
            }

            $user->update([
                'is_active' => $newStatus,
                'updated_by' => $updatedBy->id,
            ]);

            DB::commit();

            return $user->fresh(['roles', 'permissions'])->loadCount(['assignedEquipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update user roles
     */
    public function updateUserRoles(User $user, array $roles, User $updatedBy): User
    {
        DB::beginTransaction();

        try {
            // Validate roles exist
            $validRoles = Role::whereIn('name', $roles)->pluck('name')->toArray();
            $invalidRoles = array_diff($roles, $validRoles);
            
            if (!empty($invalidRoles)) {
                throw new \InvalidArgumentException('Invalid roles: ' . implode(', ', $invalidRoles));
            }

            $user->syncRoles($roles);
            $user->update(['updated_by' => $updatedBy->id]);

            DB::commit();

            return $user->fresh(['roles', 'permissions'])->loadCount(['assignedEquipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get user with complete details
     */
    public function getUserWithDetails(User $user): User
    {
        return $user->load(['roles.permissions', 'assignedEquipment.type', 'assignedEquipment.manufacturer'])
                   ->loadCount(['assignedEquipment']);
    }

    /**
     * Get user profile data
     */
    public function getUserProfile(User $user): User
    {
        return $user->load(['roles.permissions'])
                   ->loadCount(['assignedEquipment']);
    }

    /**
     * Update user profile (limited fields)
     */
    public function updateUserProfile(User $user, array $data): User
    {
        DB::beginTransaction();

        try {
            $allowedFields = [
                'first_name', 'last_name', 'phone', 
                'emergency_contact_name', 'emergency_contact_phone'
            ];

            $updateData = array_intersect_key($data, array_flip($allowedFields));
            $user->update($updateData);

            DB::commit();

            return $user->fresh(['roles.permissions'])->loadCount(['assignedEquipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get user activity summary
     */
    public function getUserActivitySummary(User $user, int $days = 30): array
    {
        $fromDate = now()->subDays($days);

        return [
            'last_login' => $user->last_login,
            'active_sessions' => $user->tokens()->count(),
            'assigned_equipment_count' => $user->assignedEquipment()->count(),
            'certification_status' => [
                'level' => $user->certification_level,
                'expiry' => $user->certification_expiry,
                'is_active' => $user->hasActiveCertification(),
                'is_expiring_soon' => $user->isCertificationExpiringSoon(),
            ],
            'profile_completeness' => $this->calculateProfileCompleteness($user),
            'roles' => $user->getRoleNames(),
            'permissions_count' => $user->getAllPermissions()->count(),
        ];
    }

    /**
     * Get user's assigned equipment
     */
    public function getUserEquipment(User $user): array
    {
        return $user->assignedEquipment()
            ->with(['type', 'manufacturer', 'category'])
            ->get()
            ->map(function ($equipment) {
                return [
                    'id' => $equipment->id,
                    'asset_number' => $equipment->asset_number,
                    'model' => $equipment->model,
                    'status' => $equipment->status,
                    'type' => $equipment->type?->name,
                    'category' => $equipment->category?->name,
                    'manufacturer' => $equipment->manufacturer?->name,
                    'operating_hours' => $equipment->total_operating_hours,
                    'next_service_hours' => $equipment->next_service_hours,
                ];
            })
            ->toArray();
    }

    /**
     * Get user statistics for dashboard
     */
    public function getUserStatistics(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'users_by_role' => $this->getUsersByRole(),
            'users_by_department' => $this->getUsersByDepartment(),
            'users_by_certification_level' => $this->getUsersByCertificationLevel(),
            'certification_expiring_soon' => $this->getUsersWithExpiringCertification(),
            'recent_logins' => $this->getRecentLoginStats(),
        ];
    }

    /**
     * Search users with basic info
     */
    public function searchUsers(string $query, int $limit = 10): array
    {
        return User::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', '%' . $query . '%')
                  ->orWhere('last_name', 'like', '%' . $query . '%')
                  ->orWhere('email', 'like', '%' . $query . '%')
                  ->orWhere('employee_id', 'like', '%' . $query . '%');
            })
            ->limit($limit)
            ->get(['id', 'first_name', 'last_name', 'email', 'employee_id', 'position'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'position' => $user->position,
                ];
            })
            ->toArray();
    }

    /**
     * Apply filters to users query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%')
                  ->orWhere('employee_id', 'like', '%' . $search . '%')
                  ->orWhere('position', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }

        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (!empty($filters['certification_level'])) {
            $query->where('certification_level', $filters['certification_level']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['has_certification']) && $filters['has_certification'] !== null) {
            if ($filters['has_certification']) {
                $query->whereNotNull('certification_expiry')
                      ->where('certification_expiry', '>', now());
            } else {
                $query->where(function ($q) {
                    $q->whereNull('certification_expiry')
                      ->orWhere('certification_expiry', '<=', now());
                });
            }
        }
    }

    /**
     * Apply sorting to users query
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'first_name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $allowedSorts = [
            'first_name', 'last_name', 'email', 'employee_id', 
            'department', 'position', 'certification_level', 
            'created_at', 'last_login'
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    /**
     * Validate user data
     */
    private function validateUserData(array $data, ?User $user = null): void
    {
        // Validate employee ID format
        if (isset($data['employee_id']) && !preg_match('/^[A-Z]{2,3}\d{3,4}$/', $data['employee_id'])) {
            throw new \InvalidArgumentException('Employee ID must follow format: AB123 or ABC1234');
        }

        // Validate certification expiry is in future
        if (isset($data['certification_expiry']) && $data['certification_expiry']) {
            $expiryDate = new \DateTime($data['certification_expiry']);
            if ($expiryDate <= now()) {
                throw new \InvalidArgumentException('Certification expiry must be in the future');
            }
        }

        // Validate phone number format
        if (isset($data['phone']) && $data['phone'] && !preg_match('/^\+?[\d\s\-\(\)]{10,20}$/', $data['phone'])) {
            throw new \InvalidArgumentException('Invalid phone number format');
        }
    }

    /**
     * Normalize user data
     */
    private function normalizeUserData(array $data): array
    {
        // Normalize employee ID to uppercase
        if (isset($data['employee_id'])) {
            $data['employee_id'] = strtoupper($data['employee_id']);
        }

        // Ensure is_active is boolean
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        return $data;
    }

    /**
     * Validate user can be deactivated
     */
    private function validateUserDeactivation(User $user): void
    {
        $assignedEquipmentCount = $user->assignedEquipment()->count();
        
        if ($assignedEquipmentCount > 0) {
            throw new \InvalidArgumentException(
                "Cannot deactivate user. User has {$assignedEquipmentCount} equipment assigned."
            );
        }
    }

    /**
     * Calculate profile completeness
     */
    private function calculateProfileCompleteness(User $user): array
    {
        $fields = [
            'first_name' => !empty($user->first_name),
            'last_name' => !empty($user->last_name),
            'email' => !empty($user->email),
            'phone' => !empty($user->phone),
            'employee_id' => !empty($user->employee_id),
            'department' => !empty($user->department),
            'position' => !empty($user->position),
            'certification_level' => !empty($user->certification_level),
            'emergency_contact' => !empty($user->emergency_contact_name) && !empty($user->emergency_contact_phone),
        ];

        $totalFields = count($fields);
        $completedFields = array_sum($fields);
        $percentage = round(($completedFields / $totalFields) * 100, 1);

        return [
            'percentage' => $percentage,
            'completed_fields' => $completedFields,
            'total_fields' => $totalFields,
            'missing_fields' => array_keys(array_filter($fields, fn($completed) => !$completed)),
        ];
    }

    /**
     * Get users grouped by role
     */
    private function getUsersByRole(): array
    {
        return User::with('roles')
            ->get()
            ->groupBy(function ($user) {
                return $user->roles->first()?->name ?? 'no_role';
            })
            ->map->count()
            ->toArray();
    }

    /**
     * Get users grouped by department
     */
    private function getUsersByDepartment(): array
    {
        return User::select('department')
            ->selectRaw('count(*) as count')
            ->groupBy('department')
            ->pluck('count', 'department')
            ->toArray();
    }

    /**
     * Get users grouped by certification level
     */
    private function getUsersByCertificationLevel(): array
    {
        return User::select('certification_level')
            ->selectRaw('count(*) as count')
            ->groupBy('certification_level')
            ->pluck('count', 'certification_level')
            ->toArray();
    }

    /**
     * Get users with expiring certification
     */
    private function getUsersWithExpiringCertification(): int
    {
        return User::where('certification_expiry', '>', now())
            ->where('certification_expiry', '<=', now()->addDays(30))
            ->count();
    }

    /**
     * Get recent login statistics
     */
    private function getRecentLoginStats(): array
    {
        $last7Days = User::where('last_login', '>=', now()->subDays(7))->count();
        $last30Days = User::where('last_login', '>=', now()->subDays(30))->count();

        return [
            'last_7_days' => $last7Days,
            'last_30_days' => $last30Days,
        ];
    }
}