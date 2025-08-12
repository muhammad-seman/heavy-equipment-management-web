<?php

namespace App\Services\Equipment;

use App\Models\Equipment;
use App\Models\EquipmentStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EquipmentService
{
    /**
     * Get filtered and paginated equipment list
     */
    public function getFilteredEquipment(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Equipment::with(['category', 'type', 'manufacturer', 'assignedUser']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Create new equipment with business logic validation
     */
    public function createEquipment(array $data, User $user): Equipment
    {
        DB::beginTransaction();

        try {
            // Business logic validations
            $this->validateEquipmentData($data);

            // Set created by user
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;
            $data['status_changed_by'] = $user->id;
            $data['status_changed_at'] = now();

            // Create equipment
            $equipment = Equipment::create($data);

            // Log initial status
            $this->logStatusChange($equipment, null, $data['status'], $user, 'Equipment created');

            // Auto-assign if specified
            if (isset($data['assigned_to_user'])) {
                $this->assignToUser($equipment, $data['assigned_to_user'], $user);
            }

            DB::commit();

            return $equipment->load(['category', 'type', 'manufacturer', 'assignedUser']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update equipment with business logic
     */
    public function updateEquipment(Equipment $equipment, array $data, User $user): Equipment
    {
        DB::beginTransaction();

        try {
            $originalStatus = $equipment->status;
            $originalAssignment = $equipment->assigned_to_user;

            // Business logic validations
            $this->validateEquipmentData($data, $equipment);

            // Update tracking
            $data['updated_by'] = $user->id;

            // Handle status change
            if (isset($data['status']) && $data['status'] !== $originalStatus) {
                $this->validateStatusTransition($equipment, $data['status']);
                $data['status_changed_by'] = $user->id;
                $data['status_changed_at'] = now();
            }

            // Update equipment
            $equipment->update($data);

            // Log status change if changed
            if (isset($data['status']) && $data['status'] !== $originalStatus) {
                $this->logStatusChange(
                    $equipment, 
                    $originalStatus, 
                    $data['status'], 
                    $user, 
                    $data['status_notes'] ?? 'Status updated'
                );
            }

            // Handle assignment change
            if (isset($data['assigned_to_user']) && $data['assigned_to_user'] !== $originalAssignment) {
                if ($data['assigned_to_user']) {
                    $this->assignToUser($equipment, $data['assigned_to_user'], $user);
                } else {
                    $this->unassignFromUser($equipment, $user);
                }
            }

            DB::commit();

            return $equipment->fresh(['category', 'type', 'manufacturer', 'assignedUser']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Change equipment status with validation
     */
    public function changeStatus(Equipment $equipment, string $newStatus, User $user, ?string $notes = null): Equipment
    {
        DB::beginTransaction();

        try {
            $oldStatus = $equipment->status;

            // Validate status transition
            $this->validateStatusTransition($equipment, $newStatus);

            // Update status
            $equipment->update([
                'status' => $newStatus,
                'status_changed_by' => $user->id,
                'status_changed_at' => now(),
                'status_notes' => $notes,
                'updated_by' => $user->id,
            ]);

            // Log status change
            $this->logStatusChange($equipment, $oldStatus, $newStatus, $user, $notes);

            // Handle status-specific business logic
            $this->handleStatusChange($equipment, $oldStatus, $newStatus, $user);

            DB::commit();

            return $equipment->fresh(['category', 'type', 'manufacturer', 'assignedUser']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Assign equipment to user
     */
    public function assignToUser(Equipment $equipment, int $userId, User $assignedBy): Equipment
    {
        // Validate user can operate this equipment type
        $user = User::findOrFail($userId);
        $this->validateUserCanOperateEquipment($user, $equipment);

        // Check if user already has too many assignments
        $this->validateUserAssignmentLimit($user, $equipment);

        $equipment->update([
            'assigned_to_user' => $userId,
            'updated_by' => $assignedBy->id,
        ]);

        // Could add notification logic here

        return $equipment->fresh(['assignedUser']);
    }

    /**
     * Unassign equipment from user
     */
    public function unassignFromUser(Equipment $equipment, User $unassignedBy): Equipment
    {
        $equipment->update([
            'assigned_to_user' => null,
            'updated_by' => $unassignedBy->id,
        ]);

        return $equipment->fresh();
    }

    /**
     * Get equipment maintenance status
     */
    public function getMaintenanceStatus(Equipment $equipment): array
    {
        return [
            'hours_since_last_service' => $equipment->total_operating_hours - $equipment->last_service_hours,
            'hours_until_next_service' => $equipment->next_service_hours ? 
                max(0, $equipment->next_service_hours - $equipment->total_operating_hours) : null,
            'is_service_due' => $equipment->next_service_hours ? 
                $equipment->total_operating_hours >= $equipment->next_service_hours : false,
            'is_service_overdue' => $equipment->next_service_hours ?
                $equipment->total_operating_hours > ($equipment->next_service_hours + 50) : false, // 50 hours grace
            'warranty_status' => $equipment->warranty_status,
        ];
    }

    /**
     * Get equipment utilization metrics
     */
    public function getUtilizationMetrics(Equipment $equipment, int $days = 30): array
    {
        // This would typically query actual usage logs
        // For now, providing calculated metrics
        
        $hoursPerDay = $equipment->total_operating_hours / 
            max(1, now()->diffInDays($equipment->created_at ?: now()));
        
        return [
            'average_daily_hours' => round($hoursPerDay, 2),
            'utilization_percentage' => min(100, round(($hoursPerDay / 10) * 100, 2)), // Assuming 10 hours/day target
            'total_operating_hours' => $equipment->total_operating_hours,
            'estimated_remaining_life' => $this->estimateRemainingLife($equipment),
        ];
    }

    /**
     * Apply filters to equipment query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['manufacturer_id'])) {
            $query->where('manufacturer_id', $filters['manufacturer_id']);
        }

        if (!empty($filters['equipment_type_id'])) {
            $query->where('equipment_type_id', $filters['equipment_type_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereHas('type', function ($q) use ($filters) {
                $q->where('category_id', $filters['category_id']);
            });
        }

        if (!empty($filters['assigned_to_user'])) {
            $query->where('assigned_to_user', $filters['assigned_to_user']);
        }

        if (!empty($filters['ownership_type'])) {
            $query->where('ownership_type', $filters['ownership_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('asset_number', 'like', '%' . $search . '%')
                  ->orWhere('serial_number', 'like', '%' . $search . '%')
                  ->orWhere('model', 'like', '%' . $search . '%');
            });
        }

        // Date filters
        if (!empty($filters['purchase_date_from'])) {
            $query->where('purchase_date', '>=', $filters['purchase_date_from']);
        }

        if (!empty($filters['purchase_date_to'])) {
            $query->where('purchase_date', '<=', $filters['purchase_date_to']);
        }

        // Service due filter
        if (!empty($filters['service_due'])) {
            $query->whereRaw('total_operating_hours >= next_service_hours');
        }
    }

    /**
     * Apply sorting to equipment query
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $allowedSorts = [
            'asset_number', 'model', 'status', 'total_operating_hours',
            'purchase_date', 'created_at', 'updated_at'
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    /**
     * Validate equipment data for business rules
     */
    private function validateEquipmentData(array $data, ?Equipment $equipment = null): void
    {
        // Check if asset number pattern matches company standards
        if (isset($data['asset_number']) && !preg_match('/^[A-Z]{3}-[A-Z]{3}-\d{3}$/', $data['asset_number'])) {
            throw new \InvalidArgumentException('Asset number must follow format: ABC-DEF-123');
        }

        // Check purchase date vs year manufactured
        if (isset($data['purchase_date']) && isset($data['year_manufactured'])) {
            $purchaseYear = date('Y', strtotime($data['purchase_date']));
            if ($purchaseYear < $data['year_manufactured']) {
                throw new \InvalidArgumentException('Purchase date cannot be before year manufactured');
            }
        }

        // Validate lease dates for leased equipment
        if (isset($data['ownership_type']) && in_array($data['ownership_type'], ['leased', 'rented'])) {
            if (empty($data['lease_start_date']) || empty($data['lease_end_date'])) {
                throw new \InvalidArgumentException('Lease dates are required for leased/rented equipment');
            }
        }
    }

    /**
     * Validate status transitions based on business rules
     */
    private function validateStatusTransition(Equipment $equipment, string $newStatus): void
    {
        $currentStatus = $equipment->status;
        
        // Define allowed transitions
        $allowedTransitions = [
            'active' => ['maintenance', 'repair', 'standby', 'retired'],
            'maintenance' => ['active', 'repair', 'retired'],
            'repair' => ['active', 'maintenance', 'retired', 'disposal'],
            'standby' => ['active', 'maintenance', 'repair', 'retired'],
            'retired' => ['disposal'],
            'disposal' => [], // No transitions from disposal
        ];

        if (!isset($allowedTransitions[$currentStatus]) || 
            !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw new \InvalidArgumentException(
                "Invalid status transition from '{$currentStatus}' to '{$newStatus}'"
            );
        }
    }

    /**
     * Log status change
     */
    private function logStatusChange(Equipment $equipment, ?string $fromStatus, string $toStatus, User $user, ?string $notes): void
    {
        EquipmentStatusLog::create([
            'equipment_id' => $equipment->id,
            'previous_status' => $fromStatus,
            'new_status' => $toStatus,
            'changed_by' => $user->id,
            'changed_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Handle status change side effects
     */
    private function handleStatusChange(Equipment $equipment, string $oldStatus, string $newStatus, User $user): void
    {
        // Unassign user if equipment goes to maintenance/repair/retired
        if (in_array($newStatus, ['maintenance', 'repair', 'retired', 'disposal']) && $equipment->assigned_to_user) {
            $this->unassignFromUser($equipment, $user);
        }

        // Add business-specific logic here
        // - Send notifications
        // - Create maintenance schedules
        // - Update reporting systems
    }

    /**
     * Validate user can operate equipment
     */
    private function validateUserCanOperateEquipment(User $user, Equipment $equipment): void
    {
        // Check if user has required certification level
        if (!$user->hasActiveCertification()) {
            throw new \InvalidArgumentException('User does not have active certification');
        }

        // Check user's role permissions
        if (!$user->can('equipment.operate')) {
            throw new \InvalidArgumentException('User does not have permission to operate equipment');
        }
    }

    /**
     * Validate user assignment limits
     */
    private function validateUserAssignmentLimit(User $user, Equipment $equipment): void
    {
        $currentAssignments = Equipment::where('assigned_to_user', $user->id)
            ->whereIn('status', ['active', 'maintenance'])
            ->count();

        $maxAssignments = 3; // Business rule: max 3 active assignments per user

        if ($currentAssignments >= $maxAssignments) {
            throw new \InvalidArgumentException("User already has maximum of {$maxAssignments} equipment assignments");
        }
    }

    /**
     * Estimate remaining equipment life
     */
    private function estimateRemainingLife(Equipment $equipment): array
    {
        $hoursPerYear = 2000; // Industry average
        $maxLifeHours = 15000; // Typical heavy equipment life

        $yearsInService = now()->diffInYears($equipment->purchase_date ?? $equipment->created_at);
        $remainingHours = max(0, $maxLifeHours - $equipment->total_operating_hours);
        $remainingYears = $remainingHours / $hoursPerYear;

        return [
            'remaining_hours' => $remainingHours,
            'estimated_remaining_years' => round($remainingYears, 1),
            'life_percentage_used' => min(100, round(($equipment->total_operating_hours / $maxLifeHours) * 100, 1)),
        ];
    }
}