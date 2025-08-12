<?php

namespace App\Services\Maintenance;

use App\Models\MaintenanceRecord;
use App\Models\MaintenancePart;
use App\Models\MaintenanceSchedule;
use App\Models\Equipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MaintenanceService
{
    /**
     * Get all maintenance records with filtering and pagination
     */
    public function getAllMaintenanceRecords(array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['equipment', 'technician', 'supervisor', 'maintenanceParts'])
            ->withCount(['maintenanceParts']);

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get maintenance record by ID with relationships
     */
    public function getMaintenanceRecordById(int $maintenanceRecordId): MaintenanceRecord
    {
        return MaintenanceRecord::with([
            'equipment.equipmentType',
            'equipment.manufacturer',
            'technician',
            'supervisor',
            'approver',
            'creator',
            'updater',
            'maintenanceParts',
            'maintenanceSchedules'
        ])->findOrFail($maintenanceRecordId);
    }

    /**
     * Create a new maintenance record
     */
    public function createMaintenanceRecord(array $data): MaintenanceRecord
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();
            
            // Generate work order number if not provided
            if (!isset($data['work_order_number'])) {
                $data['work_order_number'] = $this->generateWorkOrderNumber();
            }

            // Set default values
            if (!isset($data['status'])) {
                $data['status'] = MaintenanceRecord::STATUS_SCHEDULED;
            }

            // Set approval requirement based on cost or type
            if (!isset($data['approval_required'])) {
                $data['approval_required'] = $this->requiresApproval($data);
            }

            $maintenanceRecord = MaintenanceRecord::create($data);

            // Create maintenance parts if provided
            if (isset($data['parts'])) {
                $this->createMaintenanceParts($maintenanceRecord, $data['parts']);
            }

            // Link to maintenance schedules if provided
            if (isset($data['schedule_ids'])) {
                $maintenanceRecord->maintenanceSchedules()->attach($data['schedule_ids']);
            }

            return $maintenanceRecord->load(['equipment', 'technician', 'maintenanceParts']);
        });
    }

    /**
     * Update an existing maintenance record
     */
    public function updateMaintenanceRecord(int $maintenanceRecordId, array $data): MaintenanceRecord
    {
        return DB::transaction(function () use ($maintenanceRecordId, $data) {
            $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
            
            $data['updated_by'] = Auth::id();

            // Handle status transitions
            if (isset($data['status']) && $data['status'] !== $maintenanceRecord->status) {
                $this->handleStatusTransition($maintenanceRecord, $data['status'], $data);
            }

            $maintenanceRecord->update($data);

            // Update maintenance parts if provided
            if (isset($data['parts'])) {
                $this->updateMaintenanceParts($maintenanceRecord, $data['parts']);
            }

            // Calculate total cost if parts were updated
            if (isset($data['parts']) || isset($data['labor_cost']) || isset($data['external_cost'])) {
                $this->calculateTotalCost($maintenanceRecord);
            }

            return $maintenanceRecord->load(['equipment', 'technician', 'maintenanceParts']);
        });
    }

    /**
     * Delete a maintenance record (soft delete)
     */
    public function deleteMaintenanceRecord(int $maintenanceRecordId): bool
    {
        $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
        
        // Check if maintenance can be deleted
        if ($maintenanceRecord->status === MaintenanceRecord::STATUS_COMPLETED) {
            throw new \Exception('Cannot delete completed maintenance records');
        }

        if ($maintenanceRecord->status === MaintenanceRecord::STATUS_IN_PROGRESS) {
            throw new \Exception('Cannot delete maintenance records in progress');
        }

        return $maintenanceRecord->delete();
    }

    /**
     * Restore a soft-deleted maintenance record
     */
    public function restoreMaintenanceRecord(int $maintenanceRecordId): MaintenanceRecord
    {
        $maintenanceRecord = MaintenanceRecord::withTrashed()->findOrFail($maintenanceRecordId);
        $maintenanceRecord->restore();
        
        return $maintenanceRecord->load(['equipment', 'technician']);
    }

    /**
     * Start a maintenance record
     */
    public function startMaintenance(int $maintenanceRecordId): MaintenanceRecord
    {
        return DB::transaction(function () use ($maintenanceRecordId) {
            $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
            
            if (!in_array($maintenanceRecord->status, [MaintenanceRecord::STATUS_SCHEDULED, MaintenanceRecord::STATUS_APPROVED])) {
                throw new \Exception('Only scheduled or approved maintenance can be started');
            }

            // Check approval requirement
            if ($maintenanceRecord->approval_required && !$maintenanceRecord->is_approved) {
                throw new \Exception('Maintenance requires approval before starting');
            }

            $maintenanceRecord->update([
                'status' => MaintenanceRecord::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'updated_by' => Auth::id()
            ]);

            return $maintenanceRecord->load(['equipment', 'technician']);
        });
    }

    /**
     * Complete a maintenance record
     */
    public function completeMaintenance(int $maintenanceRecordId, array $data = []): MaintenanceRecord
    {
        return DB::transaction(function () use ($maintenanceRecordId, $data) {
            $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
            
            if ($maintenanceRecord->status !== MaintenanceRecord::STATUS_IN_PROGRESS) {
                throw new \Exception('Only maintenance in progress can be completed');
            }

            $updateData = [
                'status' => MaintenanceRecord::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_by' => Auth::id()
            ];

            // Add completion data
            if (isset($data['work_performed'])) {
                $updateData['work_performed'] = $data['work_performed'];
            }
            if (isset($data['operating_hours_after'])) {
                $updateData['operating_hours_after'] = $data['operating_hours_after'];
            }
            if (isset($data['next_service_hours'])) {
                $updateData['next_service_hours'] = $data['next_service_hours'];
            }
            if (isset($data['next_service_date'])) {
                $updateData['next_service_date'] = $data['next_service_date'];
            }
            if (isset($data['quality_check_passed'])) {
                $updateData['quality_check_passed'] = $data['quality_check_passed'];
            }

            // Calculate actual duration
            if ($maintenanceRecord->started_at) {
                $updateData['actual_duration'] = $maintenanceRecord->started_at->diffInMinutes(now());
            }

            $maintenanceRecord->update($updateData);

            // Update related maintenance schedules
            $this->updateMaintenanceSchedules($maintenanceRecord);

            // Calculate final costs
            $this->calculateTotalCost($maintenanceRecord);

            return $maintenanceRecord->load(['equipment', 'technician', 'maintenanceParts']);
        });
    }

    /**
     * Put maintenance on hold
     */
    public function holdMaintenance(int $maintenanceRecordId, ?string $reason = null): MaintenanceRecord
    {
        $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
        
        if ($maintenanceRecord->status !== MaintenanceRecord::STATUS_IN_PROGRESS) {
            throw new \Exception('Only maintenance in progress can be put on hold');
        }

        $updateData = [
            'status' => MaintenanceRecord::STATUS_ON_HOLD,
            'updated_by' => Auth::id()
        ];

        if ($reason) {
            $updateData['description'] = ($maintenanceRecord->description ? $maintenanceRecord->description . "\n\n" : '') . "Put on hold: " . $reason;
        }

        $maintenanceRecord->update($updateData);

        return $maintenanceRecord->load(['equipment', 'technician']);
    }

    /**
     * Resume maintenance from hold
     */
    public function resumeMaintenance(int $maintenanceRecordId): MaintenanceRecord
    {
        $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
        
        if ($maintenanceRecord->status !== MaintenanceRecord::STATUS_ON_HOLD) {
            throw new \Exception('Only maintenance on hold can be resumed');
        }

        $maintenanceRecord->update([
            'status' => MaintenanceRecord::STATUS_IN_PROGRESS,
            'updated_by' => Auth::id()
        ]);

        return $maintenanceRecord->load(['equipment', 'technician']);
    }

    /**
     * Cancel a maintenance record
     */
    public function cancelMaintenance(int $maintenanceRecordId, ?string $reason = null): MaintenanceRecord
    {
        $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
        
        if ($maintenanceRecord->status === MaintenanceRecord::STATUS_COMPLETED) {
            throw new \Exception('Cannot cancel completed maintenance');
        }

        $updateData = [
            'status' => MaintenanceRecord::STATUS_CANCELLED,
            'updated_by' => Auth::id()
        ];

        if ($reason) {
            $updateData['description'] = ($maintenanceRecord->description ? $maintenanceRecord->description . "\n\n" : '') . "Cancelled: " . $reason;
        }

        $maintenanceRecord->update($updateData);

        return $maintenanceRecord->load(['equipment', 'technician']);
    }

    /**
     * Approve a maintenance record
     */
    public function approveMaintenance(int $maintenanceRecordId): MaintenanceRecord
    {
        $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
        
        if ($maintenanceRecord->status !== MaintenanceRecord::STATUS_PENDING_APPROVAL) {
            throw new \Exception('Only maintenance pending approval can be approved');
        }

        $maintenanceRecord->update([
            'status' => MaintenanceRecord::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_by' => Auth::id()
        ]);

        return $maintenanceRecord->load(['equipment', 'technician', 'approver']);
    }

    /**
     * Reject a maintenance record
     */
    public function rejectMaintenance(int $maintenanceRecordId, ?string $reason = null): MaintenanceRecord
    {
        $maintenanceRecord = MaintenanceRecord::findOrFail($maintenanceRecordId);
        
        if ($maintenanceRecord->status !== MaintenanceRecord::STATUS_PENDING_APPROVAL) {
            throw new \Exception('Only maintenance pending approval can be rejected');
        }

        $updateData = [
            'status' => MaintenanceRecord::STATUS_REJECTED,
            'updated_by' => Auth::id()
        ];

        if ($reason) {
            $updateData['description'] = ($maintenanceRecord->description ? $maintenanceRecord->description . "\n\n" : '') . "Rejected: " . $reason;
        }

        $maintenanceRecord->update($updateData);

        return $maintenanceRecord->load(['equipment', 'technician']);
    }

    /**
     * Get maintenance records by equipment
     */
    public function getMaintenanceByEquipment(int $equipmentId, array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['technician', 'maintenanceParts'])
            ->where('equipment_id', $equipmentId)
            ->withCount(['maintenanceParts']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get maintenance records by technician
     */
    public function getMaintenanceByTechnician(int $technicianId, array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['equipment', 'maintenanceParts'])
            ->where('technician_id', $technicianId)
            ->withCount(['maintenanceParts']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get today's maintenance
     */
    public function getTodaysMaintenance(array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['equipment', 'technician'])
            ->today()
            ->withCount(['maintenanceParts']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'asc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get this week's maintenance
     */
    public function getThisWeeksMaintenance(array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['equipment', 'technician'])
            ->thisWeek()
            ->withCount(['maintenanceParts']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'asc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get overdue maintenance
     */
    public function getOverdueMaintenance(array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['equipment', 'technician'])
            ->overdue()
            ->withCount(['maintenanceParts']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'asc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get emergency maintenance
     */
    public function getEmergencyMaintenance(array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['equipment', 'technician'])
            ->where('priority_level', MaintenanceRecord::PRIORITY_EMERGENCY)
            ->withCount(['maintenanceParts']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get maintenance pending approval
     */
    public function getMaintenancePendingApproval(array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['equipment', 'technician'])
            ->pendingApproval()
            ->withCount(['maintenanceParts']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'asc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get maintenance statistics
     */
    public function getMaintenanceStatistics(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30);
        $dateTo = $filters['date_to'] ?? now();

        return [
            'total_maintenance' => MaintenanceRecord::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'completed_maintenance' => MaintenanceRecord::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', MaintenanceRecord::STATUS_COMPLETED)->count(),
            'overdue_maintenance' => MaintenanceRecord::overdue()->count(),
            'todays_maintenance' => MaintenanceRecord::today()->count(),
            'this_weeks_maintenance' => MaintenanceRecord::thisWeek()->count(),
            'emergency_maintenance' => MaintenanceRecord::where('priority_level', MaintenanceRecord::PRIORITY_EMERGENCY)->count(),
            'pending_approval' => MaintenanceRecord::pendingApproval()->count(),
            'maintenance_by_status' => $this->getMaintenanceByStatus($dateFrom, $dateTo),
            'maintenance_by_type' => $this->getMaintenanceByType($dateFrom, $dateTo),
            'maintenance_by_priority' => $this->getMaintenanceByPriority($dateFrom, $dateTo),
            'total_cost' => $this->getTotalMaintenanceCost($dateFrom, $dateTo),
            'average_completion_time' => $this->getAverageCompletionTime($dateFrom, $dateTo),
            'most_active_technicians' => $this->getMostActiveTechnicians($dateFrom, $dateTo),
            'equipment_maintenance_frequency' => $this->getEquipmentMaintenanceFrequency($dateFrom, $dateTo),
        ];
    }

    /**
     * Search maintenance records
     */
    public function searchMaintenanceRecords(array $filters = []): LengthAwarePaginator
    {
        $query = MaintenanceRecord::with(['equipment', 'technician'])
            ->withCount(['maintenanceParts']);

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('work_order_number', 'like', "%{$search}%")
                  ->orWhereHas('equipment', function ($eq) use ($search) {
                      $eq->where('name', 'like', "%{$search}%")
                         ->orWhere('serial_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('technician', function ($tech) use ($search) {
                      $tech->where('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get maintenance summary for dashboard
     */
    public function getMaintenanceSummary(array $filters = []): array
    {
        return [
            'today' => [
                'scheduled' => MaintenanceRecord::today()->where('status', MaintenanceRecord::STATUS_SCHEDULED)->count(),
                'in_progress' => MaintenanceRecord::today()->where('status', MaintenanceRecord::STATUS_IN_PROGRESS)->count(),
                'completed' => MaintenanceRecord::today()->where('status', MaintenanceRecord::STATUS_COMPLETED)->count(),
                'emergency' => MaintenanceRecord::today()->where('priority_level', MaintenanceRecord::PRIORITY_EMERGENCY)->count(),
            ],
            'this_week' => [
                'scheduled' => MaintenanceRecord::thisWeek()->where('status', MaintenanceRecord::STATUS_SCHEDULED)->count(),
                'completed' => MaintenanceRecord::thisWeek()->where('status', MaintenanceRecord::STATUS_COMPLETED)->count(),
                'overdue' => MaintenanceRecord::overdue()->count(),
                'pending_approval' => MaintenanceRecord::pendingApproval()->count(),
            ],
            'recent_completed' => MaintenanceRecord::with(['equipment', 'technician'])
                ->where('status', MaintenanceRecord::STATUS_COMPLETED)
                ->orderBy('completed_at', 'desc')
                ->limit(5)
                ->get(),
            'upcoming' => MaintenanceRecord::with(['equipment', 'technician'])
                ->where('status', MaintenanceRecord::STATUS_SCHEDULED)
                ->where('scheduled_date', '>', now())
                ->orderBy('scheduled_date', 'asc')
                ->limit(5)
                ->get(),
            'high_priority' => MaintenanceRecord::with(['equipment', 'technician'])
                ->highPriority()
                ->whereIn('status', [MaintenanceRecord::STATUS_SCHEDULED, MaintenanceRecord::STATUS_IN_PROGRESS])
                ->orderBy('scheduled_date', 'asc')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Duplicate a maintenance record
     */
    public function duplicateMaintenanceRecord(int $maintenanceRecordId): MaintenanceRecord
    {
        return DB::transaction(function () use ($maintenanceRecordId) {
            $originalRecord = MaintenanceRecord::with(['maintenanceParts'])->findOrFail($maintenanceRecordId);
            
            $newRecordData = $originalRecord->toArray();
            unset($newRecordData['id'], $newRecordData['created_at'], $newRecordData['updated_at'], $newRecordData['deleted_at']);
            
            // Reset status and dates
            $newRecordData['status'] = MaintenanceRecord::STATUS_SCHEDULED;
            $newRecordData['scheduled_date'] = now()->addDay();
            $newRecordData['started_at'] = null;
            $newRecordData['completed_at'] = null;
            $newRecordData['approved_at'] = null;
            $newRecordData['approved_by'] = null;
            $newRecordData['work_order_number'] = $this->generateWorkOrderNumber();
            $newRecordData['created_by'] = Auth::id();
            $newRecordData['updated_by'] = Auth::id();

            $newRecord = MaintenanceRecord::create($newRecordData);

            // Duplicate maintenance parts
            foreach ($originalRecord->maintenanceParts as $part) {
                $partData = $part->toArray();
                unset($partData['id'], $partData['maintenance_record_id'], $partData['created_at'], $partData['updated_at'], $partData['deleted_at']);
                
                $partData['maintenance_record_id'] = $newRecord->id;
                $partData['created_by'] = Auth::id();
                $partData['updated_by'] = Auth::id();
                
                MaintenancePart::create($partData);
            }

            return $newRecord->load(['equipment', 'technician', 'maintenanceParts']);
        });
    }

    /**
     * Generate work order number
     */
    public function generateWorkOrderNumber(): string
    {
        $year = now()->year;
        $month = now()->format('m');
        
        // Get the last work order number for this month
        $lastRecord = MaintenanceRecord::where('work_order_number', 'like', "WO{$year}{$month}%")
            ->orderBy('work_order_number', 'desc')
            ->first();

        if ($lastRecord) {
            $lastNumber = (int) substr($lastRecord->work_order_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf("WO%s%s%04d", $year, $month, $nextNumber);
    }

    /**
     * Get maintenance cost analysis
     */
    public function getMaintenanceCostAnalysis(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subYear();
        $dateTo = $filters['date_to'] ?? now();

        $totalCosts = MaintenanceRecord::whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->selectRaw('
                SUM(labor_cost) as total_labor_cost,
                SUM(parts_cost) as total_parts_cost,
                SUM(external_cost) as total_external_cost,
                SUM(total_cost) as total_maintenance_cost,
                AVG(total_cost) as average_maintenance_cost,
                COUNT(*) as total_maintenance_count
            ')
            ->first();

        return [
            'total_costs' => $totalCosts,
            'cost_by_equipment_type' => $this->getCostByEquipmentType($dateFrom, $dateTo),
            'cost_by_maintenance_type' => $this->getCostByMaintenanceType($dateFrom, $dateTo),
            'monthly_cost_trend' => $this->getMonthlyCostTrend($dateFrom, $dateTo),
            'cost_variance_analysis' => $this->getCostVarianceAnalysis($dateFrom, $dateTo),
        ];
    }

    /**
     * Get equipment maintenance history
     */
    public function getEquipmentMaintenanceHistory(int $equipmentId, array $filters = []): LengthAwarePaginator
    {
        return $this->getMaintenanceByEquipment($equipmentId, $filters);
    }

    /**
     * Get technician performance metrics
     */
    public function getTechnicianPerformance(int $technicianId, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subMonths(3);
        $dateTo = $filters['date_to'] ?? now();

        $completedMaintenance = MaintenanceRecord::where('technician_id', $technicianId)
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED);

        return [
            'total_completed' => $completedMaintenance->count(),
            'average_completion_time' => $completedMaintenance->avg('actual_duration'),
            'on_time_completion_rate' => $this->getOnTimeCompletionRate($technicianId, $dateFrom, $dateTo),
            'quality_score' => $this->getQualityScore($technicianId, $dateFrom, $dateTo),
            'maintenance_by_type' => $completedMaintenance->groupBy('maintenance_type')->map->count(),
            'monthly_performance' => $this->getMonthlyPerformance($technicianId, $dateFrom, $dateTo),
        ];
    }

    /**
     * Get maintenance trends
     */
    public function getMaintenanceTrends(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subYear();
        $dateTo = $filters['date_to'] ?? now();

        return [
            'monthly_maintenance_count' => $this->getMonthlyMaintenanceCount($dateFrom, $dateTo),
            'maintenance_type_trends' => $this->getMaintenanceTypeTrends($dateFrom, $dateTo),
            'cost_trends' => $this->getMonthlyCostTrend($dateFrom, $dateTo),
            'equipment_reliability_trends' => $this->getEquipmentReliabilityTrends($dateFrom, $dateTo),
        ];
    }

    /**
     * Generate maintenance report
     */
    public function generateMaintenanceReport(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subMonth();
        $dateTo = $filters['date_to'] ?? now();

        return [
            'summary' => $this->getMaintenanceStatistics($filters),
            'cost_analysis' => $this->getMaintenanceCostAnalysis($filters),
            'trends' => $this->getMaintenanceTrends($filters),
            'equipment_performance' => $this->getEquipmentPerformanceReport($dateFrom, $dateTo),
            'technician_performance' => $this->getAllTechniciansPerformance($dateFrom, $dateTo),
            'recommendations' => $this->getMaintenanceRecommendations($dateFrom, $dateTo),
        ];
    }

    /**
     * Apply filters to maintenance query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['equipment_id'])) {
            $query->where('equipment_id', $filters['equipment_id']);
        }

        if (isset($filters['technician_id'])) {
            $query->where('technician_id', $filters['technician_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['maintenance_type'])) {
            $query->where('maintenance_type', $filters['maintenance_type']);
        }

        if (isset($filters['priority_level'])) {
            $query->where('priority_level', $filters['priority_level']);
        }

        if (isset($filters['date_from'])) {
            $query->where('scheduled_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('scheduled_date', '<=', $filters['date_to']);
        }
    }

    /**
     * Check if maintenance requires approval
     */
    private function requiresApproval(array $data): bool
    {
        // Require approval for emergency maintenance
        if (isset($data['priority_level']) && $data['priority_level'] === MaintenanceRecord::PRIORITY_EMERGENCY) {
            return true;
        }

        // Require approval for high-cost maintenance
        if (isset($data['estimated_cost']) && $data['estimated_cost'] > 10000) {
            return true;
        }

        // Require approval for external vendors
        if (isset($data['external_vendor']) && !empty($data['external_vendor'])) {
            return true;
        }

        return false;
    }

    /**
     * Handle status transitions
     */
    private function handleStatusTransition(MaintenanceRecord $record, string $newStatus, array &$data): void
    {
        switch ($newStatus) {
            case MaintenanceRecord::STATUS_IN_PROGRESS:
                if (!$record->started_at) {
                    $data['started_at'] = now();
                }
                break;

            case MaintenanceRecord::STATUS_COMPLETED:
                if (!$record->completed_at) {
                    $data['completed_at'] = now();
                }
                if ($record->started_at && !isset($data['actual_duration'])) {
                    $data['actual_duration'] = $record->started_at->diffInMinutes(now());
                }
                break;

            case MaintenanceRecord::STATUS_PENDING_APPROVAL:
                // No special handling needed
                break;
        }
    }

    /**
     * Create maintenance parts
     */
    private function createMaintenanceParts(MaintenanceRecord $record, array $parts): void
    {
        foreach ($parts as $partData) {
            $partData['maintenance_record_id'] = $record->id;
            $partData['created_by'] = Auth::id();
            $partData['updated_by'] = Auth::id();
            
            MaintenancePart::create($partData);
        }
    }

    /**
     * Update maintenance parts
     */
    private function updateMaintenanceParts(MaintenanceRecord $record, array $parts): void
    {
        // Delete existing parts not in the update
        $partIds = collect($parts)->pluck('id')->filter();
        $record->maintenanceParts()->whereNotIn('id', $partIds)->delete();

        foreach ($parts as $partData) {
            if (isset($partData['id'])) {
                $part = MaintenancePart::where('maintenance_record_id', $record->id)
                    ->findOrFail($partData['id']);
                $partData['updated_by'] = Auth::id();
                $part->update($partData);
            } else {
                $partData['maintenance_record_id'] = $record->id;
                $partData['created_by'] = Auth::id();
                $partData['updated_by'] = Auth::id();
                MaintenancePart::create($partData);
            }
        }
    }

    /**
     * Calculate total cost for maintenance record
     */
    private function calculateTotalCost(MaintenanceRecord $record): void
    {
        $partsCost = $record->maintenanceParts()->sum('total_cost');
        $laborCost = $record->labor_cost ?? 0;
        $externalCost = $record->external_cost ?? 0;

        $record->update([
            'parts_cost' => $partsCost,
            'total_cost' => $partsCost + $laborCost + $externalCost
        ]);
    }

    /**
     * Update maintenance schedules after completion
     */
    private function updateMaintenanceSchedules(MaintenanceRecord $record): void
    {
        foreach ($record->maintenanceSchedules as $schedule) {
            $schedule->updateLastPerformed(
                $record->completed_at,
                $record->operating_hours_after,
                $record->equipment->kilometers ?? null
            );
        }
    }

    /**
     * Get maintenance by status for statistics
     */
    private function getMaintenanceByStatus($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get maintenance by type for statistics
     */
    private function getMaintenanceByType($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('maintenance_type')
            ->selectRaw('maintenance_type, count(*) as count')
            ->pluck('count', 'maintenance_type')
            ->toArray();
    }

    /**
     * Get maintenance by priority for statistics
     */
    private function getMaintenanceByPriority($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('priority_level')
            ->selectRaw('priority_level, count(*) as count')
            ->pluck('count', 'priority_level')
            ->toArray();
    }

    /**
     * Get total maintenance cost
     */
    private function getTotalMaintenanceCost($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->selectRaw('
                SUM(total_cost) as total,
                SUM(labor_cost) as labor,
                SUM(parts_cost) as parts,
                SUM(external_cost) as external
            ')
            ->first()
            ->toArray();
    }

    /**
     * Get average completion time
     */
    private function getAverageCompletionTime($dateFrom, $dateTo): ?float
    {
        return MaintenanceRecord::whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->whereNotNull('actual_duration')
            ->avg('actual_duration');
    }

    /**
     * Get most active technicians
     */
    private function getMostActiveTechnicians($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::with('technician')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('technician_id')
            ->selectRaw('technician_id, count(*) as maintenance_count')
            ->orderByDesc('maintenance_count')
            ->limit(5)
            ->get()
            ->map(function ($record) {
                return [
                    'technician' => $record->technician->full_name,
                    'maintenance_count' => $record->maintenance_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get equipment maintenance frequency
     */
    private function getEquipmentMaintenanceFrequency($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::with('equipment')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('equipment_id')
            ->selectRaw('equipment_id, count(*) as maintenance_count')
            ->orderByDesc('maintenance_count')
            ->limit(10)
            ->get()
            ->map(function ($record) {
                return [
                    'equipment' => $record->equipment->name,
                    'maintenance_count' => $record->maintenance_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get cost by equipment type
     */
    private function getCostByEquipmentType($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::join('equipment', 'maintenance_records.equipment_id', '=', 'equipment.id')
            ->join('equipment_types', 'equipment.equipment_type_id', '=', 'equipment_types.id')
            ->whereBetween('maintenance_records.completed_at', [$dateFrom, $dateTo])
            ->where('maintenance_records.status', MaintenanceRecord::STATUS_COMPLETED)
            ->groupBy('equipment_types.name')
            ->selectRaw('equipment_types.name, SUM(maintenance_records.total_cost) as total_cost')
            ->pluck('total_cost', 'name')
            ->toArray();
    }

    /**
     * Get cost by maintenance type
     */
    private function getCostByMaintenanceType($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->groupBy('maintenance_type')
            ->selectRaw('maintenance_type, SUM(total_cost) as total_cost')
            ->pluck('total_cost', 'maintenance_type')
            ->toArray();
    }

    /**
     * Get monthly cost trend
     */
    private function getMonthlyCostTrend($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->groupByRaw('YEAR(completed_at), MONTH(completed_at)')
            ->selectRaw('YEAR(completed_at) as year, MONTH(completed_at) as month, SUM(total_cost) as total_cost')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($record) {
                return [
                    'period' => "{$record->year}-{$record->month}",
                    'total_cost' => $record->total_cost,
                ];
            })
            ->toArray();
    }

    /**
     * Get cost variance analysis
     */
    private function getCostVarianceAnalysis($dateFrom, $dateTo): array
    {
        $records = MaintenanceRecord::whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->whereNotNull('estimated_cost')
            ->selectRaw('
                SUM(estimated_cost) as total_estimated,
                SUM(total_cost) as total_actual,
                AVG(estimated_cost) as avg_estimated,
                AVG(total_cost) as avg_actual
            ')
            ->first();

        $variance = $records->total_actual - $records->total_estimated;
        $variancePercentage = $records->total_estimated > 0 
            ? ($variance / $records->total_estimated) * 100 
            : 0;

        return [
            'total_estimated' => $records->total_estimated,
            'total_actual' => $records->total_actual,
            'variance' => $variance,
            'variance_percentage' => round($variancePercentage, 2),
            'avg_estimated' => $records->avg_estimated,
            'avg_actual' => $records->avg_actual,
        ];
    }

    /**
     * Get monthly maintenance count
     */
    private function getMonthlyMaintenanceCount($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($record) {
                return [
                    'period' => "{$record->year}-{$record->month}",
                    'count' => $record->count,
                ];
            })
            ->toArray();
    }

    /**
     * Get maintenance type trends
     */
    private function getMaintenanceTypeTrends($dateFrom, $dateTo): array
    {
        return MaintenanceRecord::whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupByRaw('YEAR(created_at), MONTH(created_at), maintenance_type')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, maintenance_type, COUNT(*) as count')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->groupBy(function ($record) {
                return "{$record->year}-{$record->month}";
            })
            ->map(function ($records, $period) {
                return [
                    'period' => $period,
                    'types' => $records->groupBy('maintenance_type')->map->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get equipment reliability trends
     */
    private function getEquipmentReliabilityTrends($dateFrom, $dateTo): array
    {
        // This would require more complex analysis of failure rates, MTBF, etc.
        // For now, return breakdown maintenance frequency
        return MaintenanceRecord::with('equipment')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('maintenance_type', MaintenanceRecord::TYPE_BREAKDOWN)
            ->groupBy('equipment_id')
            ->selectRaw('equipment_id, COUNT(*) as breakdown_count')
            ->orderByDesc('breakdown_count')
            ->limit(10)
            ->get()
            ->map(function ($record) {
                return [
                    'equipment' => $record->equipment->name,
                    'breakdown_count' => $record->breakdown_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get on-time completion rate for technician
     */
    private function getOnTimeCompletionRate(int $technicianId, $dateFrom, $dateTo): float
    {
        $completedMaintenance = MaintenanceRecord::where('technician_id', $technicianId)
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED);

        $total = $completedMaintenance->count();
        $onTime = $completedMaintenance->whereRaw('completed_at <= scheduled_date')->count();

        return $total > 0 ? round(($onTime / $total) * 100, 2) : 0;
    }

    /**
     * Get quality score for technician
     */
    private function getQualityScore(int $technicianId, $dateFrom, $dateTo): float
    {
        $qualityChecks = MaintenanceRecord::where('technician_id', $technicianId)
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->whereNotNull('quality_check_passed');

        $total = $qualityChecks->count();
        $passed = $qualityChecks->where('quality_check_passed', true)->count();

        return $total > 0 ? round(($passed / $total) * 100, 2) : 0;
    }

    /**
     * Get monthly performance for technician
     */
    private function getMonthlyPerformance(int $technicianId, $dateFrom, $dateTo): array
    {
        return MaintenanceRecord::where('technician_id', $technicianId)
            ->whereBetween('completed_at', [$dateFrom, $dateTo])
            ->where('status', MaintenanceRecord::STATUS_COMPLETED)
            ->groupByRaw('YEAR(completed_at), MONTH(completed_at)')
            ->selectRaw('
                YEAR(completed_at) as year, 
                MONTH(completed_at) as month, 
                COUNT(*) as completed_count,
                AVG(actual_duration) as avg_duration
            ')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($record) {
                return [
                    'period' => "{$record->year}-{$record->month}",
                    'completed_count' => $record->completed_count,
                    'avg_duration' => round($record->avg_duration, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get equipment performance report
     */
    private function getEquipmentPerformanceReport($dateFrom, $dateTo): array
    {
        return Equipment::with(['maintenanceRecords' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }])
        ->get()
        ->map(function ($equipment) {
            return [
                'equipment' => $equipment->name,
                'total_maintenance' => $equipment->maintenanceRecords->count(),
                'preventive_maintenance' => $equipment->maintenanceRecords->where('maintenance_type', MaintenanceRecord::TYPE_PREVENTIVE)->count(),
                'breakdown_maintenance' => $equipment->maintenanceRecords->where('maintenance_type', MaintenanceRecord::TYPE_BREAKDOWN)->count(),
                'total_cost' => $equipment->maintenanceRecords->sum('total_cost'),
                'avg_cost' => $equipment->maintenanceRecords->avg('total_cost'),
            ];
        })
        ->toArray();
    }

    /**
     * Get all technicians performance
     */
    private function getAllTechniciansPerformance($dateFrom, $dateTo): array
    {
        return User::whereHas('maintenanceRecords', function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        })
        ->withCount(['maintenanceRecords' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('completed_at', [$dateFrom, $dateTo])
                  ->where('status', MaintenanceRecord::STATUS_COMPLETED);
        }])
        ->get()
        ->map(function ($technician) use ($dateFrom, $dateTo) {
            return [
                'technician' => $technician->full_name,
                'completed_count' => $technician->maintenance_records_count,
                'on_time_rate' => $this->getOnTimeCompletionRate($technician->id, $dateFrom, $dateTo),
                'quality_score' => $this->getQualityScore($technician->id, $dateFrom, $dateTo),
            ];
        })
        ->toArray();
    }

    /**
     * Get maintenance recommendations
     */
    private function getMaintenanceRecommendations($dateFrom, $dateTo): array
    {
        $recommendations = [];

        // Check for equipment with high breakdown rates
        $highBreakdownEquipment = MaintenanceRecord::with('equipment')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('maintenance_type', MaintenanceRecord::TYPE_BREAKDOWN)
            ->groupBy('equipment_id')
            ->selectRaw('equipment_id, COUNT(*) as breakdown_count')
            ->having('breakdown_count', '>', 3)
            ->get();

        foreach ($highBreakdownEquipment as $record) {
            $recommendations[] = [
                'type' => 'high_breakdown_rate',
                'priority' => 'high',
                'equipment' => $record->equipment->name,
                'message' => "Equipment has {$record->breakdown_count} breakdowns. Consider increasing preventive maintenance frequency.",
            ];
        }

        // Check for overdue preventive maintenance
        $overdueMaintenance = MaintenanceSchedule::overdue()->with('equipment')->get();
        foreach ($overdueMaintenance as $schedule) {
            $recommendations[] = [
                'type' => 'overdue_maintenance',
                'priority' => 'medium',
                'equipment' => $schedule->equipment->name ?? 'Multiple Equipment',
                'message' => "Preventive maintenance is overdue: {$schedule->title}",
            ];
        }

        return $recommendations;
    }
}