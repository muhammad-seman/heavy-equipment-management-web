<?php

namespace App\Services\Inspections;

use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionResult;
use App\Models\InspectionTemplateItem;
use App\Models\Equipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InspectionService
{
    /**
     * Get all inspections with filtering and pagination
     */
    public function getAllInspections(array $filters = []): LengthAwarePaginator
    {
        $query = Inspection::with(['equipment', 'inspector', 'inspectionItems', 'inspectionResults'])
            ->withCount(['inspectionItems', 'inspectionResults']);

        // Apply filters
        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get inspection by ID with relationships
     */
    public function getInspectionById(int $inspectionId): Inspection
    {
        return Inspection::with([
            'equipment.equipmentType',
            'equipment.manufacturer', 
            'inspector',
            'creator',
            'updater',
            'inspectionItems.inspectionResults',
            'inspectionResults.inspectionItem'
        ])->findOrFail($inspectionId);
    }

    /**
     * Create a new inspection
     */
    public function createInspection(array $data): Inspection
    {
        return DB::transaction(function () use ($data) {
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();
            
            // Set inspection date if not provided
            if (!isset($data['inspection_date']) && in_array($data['status'] ?? 'scheduled', ['in_progress', 'completed'])) {
                $data['inspection_date'] = now();
            }

            $inspection = Inspection::create($data);

            // Auto-generate inspection items from template if equipment type is provided
            if (isset($data['equipment_id']) && isset($data['generate_from_template']) && $data['generate_from_template']) {
                $this->generateInspectionItemsFromTemplate($inspection);
            }

            // Create custom inspection items if provided
            if (isset($data['inspection_items'])) {
                $this->createInspectionItems($inspection, $data['inspection_items']);
            }

            return $inspection->load(['equipment', 'inspector', 'inspectionItems']);
        });
    }

    /**
     * Update an existing inspection
     */
    public function updateInspection(int $inspectionId, array $data): Inspection
    {
        return DB::transaction(function () use ($inspectionId, $data) {
            $inspection = Inspection::findOrFail($inspectionId);
            
            $data['updated_by'] = Auth::id();
            
            // Update completion time if completing
            if (isset($data['status']) && $data['status'] === 'completed' && !$inspection->completion_time) {
                $data['completion_time'] = now();
            }

            $inspection->update($data);

            // Update inspection items if provided
            if (isset($data['inspection_items'])) {
                $this->updateInspectionItems($inspection, $data['inspection_items']);
            }

            // Update inspection results if provided
            if (isset($data['inspection_results'])) {
                $this->updateInspectionResults($inspection, $data['inspection_results']);
            }

            // Recalculate overall result
            $this->calculateOverallResult($inspection);

            return $inspection->load(['equipment', 'inspector', 'inspectionItems', 'inspectionResults']);
        });
    }

    /**
     * Delete an inspection (soft delete)
     */
    public function deleteInspection(int $inspectionId): bool
    {
        $inspection = Inspection::findOrFail($inspectionId);
        
        // Check if inspection can be deleted
        if ($inspection->status === 'completed') {
            throw new \Exception('Cannot delete completed inspections');
        }

        return $inspection->delete();
    }

    /**
     * Restore a soft-deleted inspection
     */
    public function restoreInspection(int $inspectionId): Inspection
    {
        $inspection = Inspection::withTrashed()->findOrFail($inspectionId);
        $inspection->restore();
        
        return $inspection->load(['equipment', 'inspector']);
    }

    /**
     * Start an inspection
     */
    public function startInspection(int $inspectionId): Inspection
    {
        return DB::transaction(function () use ($inspectionId) {
            $inspection = Inspection::findOrFail($inspectionId);
            
            if ($inspection->status !== 'scheduled') {
                throw new \Exception('Only scheduled inspections can be started');
            }

            $inspection->update([
                'status' => 'in_progress',
                'inspection_date' => now(),
                'updated_by' => Auth::id()
            ]);

            return $inspection->load(['equipment', 'inspector']);
        });
    }

    /**
     * Complete an inspection
     */
    public function completeInspection(int $inspectionId, array $data = []): Inspection
    {
        return DB::transaction(function () use ($inspectionId, $data) {
            $inspection = Inspection::findOrFail($inspectionId);
            
            if (!in_array($inspection->status, ['scheduled', 'in_progress'])) {
                throw new \Exception('Only scheduled or in-progress inspections can be completed');
            }

            // Validate that all required inspection items have results
            $this->validateInspectionCompletion($inspection);

            $updateData = [
                'status' => 'completed',
                'completion_time' => now(),
                'updated_by' => Auth::id()
            ];

            // Add optional completion data
            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }
            if (isset($data['signature_data'])) {
                $updateData['signature_data'] = $data['signature_data'];
            }
            if (isset($data['operating_hours_after'])) {
                $updateData['operating_hours_after'] = $data['operating_hours_after'];
            }
            if (isset($data['fuel_level_after'])) {
                $updateData['fuel_level_after'] = $data['fuel_level_after'];
            }

            $inspection->update($updateData);

            // Calculate overall result based on inspection results
            $this->calculateOverallResult($inspection);

            return $inspection->load(['equipment', 'inspector', 'inspectionResults']);
        });
    }

    /**
     * Cancel an inspection
     */
    public function cancelInspection(int $inspectionId): Inspection
    {
        $inspection = Inspection::findOrFail($inspectionId);
        
        if ($inspection->status === 'completed') {
            throw new \Exception('Cannot cancel completed inspections');
        }

        $inspection->update([
            'status' => 'cancelled',
            'updated_by' => Auth::id()
        ]);

        return $inspection->load(['equipment', 'inspector']);
    }

    /**
     * Get inspections by equipment
     */
    public function getInspectionsByEquipment(int $equipmentId, array $filters = []): LengthAwarePaginator
    {
        $query = Inspection::with(['inspector', 'inspectionResults'])
            ->where('equipment_id', $equipmentId)
            ->withCount(['inspectionItems', 'inspectionResults']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get inspections by inspector
     */
    public function getInspectionsByInspector(int $inspectorId, array $filters = []): LengthAwarePaginator
    {
        $query = Inspection::with(['equipment', 'inspectionResults'])
            ->where('inspector_id', $inspectorId)
            ->withCount(['inspectionItems', 'inspectionResults']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get today's inspections
     */
    public function getTodaysInspections(array $filters = []): LengthAwarePaginator
    {
        $query = Inspection::with(['equipment', 'inspector'])
            ->today()
            ->withCount(['inspectionItems', 'inspectionResults']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'asc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get this week's inspections
     */
    public function getThisWeeksInspections(array $filters = []): LengthAwarePaginator
    {
        $query = Inspection::with(['equipment', 'inspector'])
            ->thisWeek()
            ->withCount(['inspectionItems', 'inspectionResults']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'asc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get overdue inspections
     */
    public function getOverdueInspections(array $filters = []): LengthAwarePaginator
    {
        $query = Inspection::with(['equipment', 'inspector'])
            ->overdue()
            ->withCount(['inspectionItems', 'inspectionResults']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'asc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get inspections requiring action
     */
    public function getInspectionsRequiringAction(array $filters = []): LengthAwarePaginator
    {
        $query = Inspection::with(['equipment', 'inspector', 'inspectionResults'])
            ->whereHas('inspectionResults', function ($q) {
                $q->where('requires_action', true);
            })
            ->withCount(['inspectionItems', 'inspectionResults']);

        $this->applyFilters($query, $filters);

        return $query->orderBy('completion_time', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get inspection statistics
     */
    public function getInspectionStatistics(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30);
        $dateTo = $filters['date_to'] ?? now();

        return [
            'total_inspections' => Inspection::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'completed_inspections' => Inspection::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', 'completed')->count(),
            'overdue_inspections' => Inspection::overdue()->count(),
            'todays_inspections' => Inspection::today()->count(),
            'this_weeks_inspections' => Inspection::thisWeek()->count(),
            'inspections_by_status' => $this->getInspectionsByStatus($dateFrom, $dateTo),
            'inspections_by_type' => $this->getInspectionsByType($dateFrom, $dateTo),
            'inspections_by_result' => $this->getInspectionsByResult($dateFrom, $dateTo),
            'inspections_requiring_action' => Inspection::whereHas('inspectionResults', function ($q) {
                $q->where('requires_action', true);
            })->count(),
            'average_completion_time' => $this->getAverageCompletionTime($dateFrom, $dateTo),
            'most_active_inspectors' => $this->getMostActiveInspectors($dateFrom, $dateTo),
            'equipment_inspection_frequency' => $this->getEquipmentInspectionFrequency($dateFrom, $dateTo),
        ];
    }

    /**
     * Search inspections
     */
    public function searchInspections(array $filters = []): LengthAwarePaginator
    {
        $query = Inspection::with(['equipment', 'inspector'])
            ->withCount(['inspectionItems', 'inspectionResults']);

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('equipment', function ($eq) use ($search) {
                    $eq->where('name', 'like', "%{$search}%")
                       ->orWhere('serial_number', 'like', "%{$search}%");
                })
                ->orWhereHas('inspector', function ($ins) use ($search) {
                    $ins->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })
                ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $this->applyFilters($query, $filters);

        return $query->orderBy('scheduled_date', 'desc')
                    ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get inspection summary for dashboard
     */
    public function getInspectionSummary(array $filters = []): array
    {
        return [
            'today' => [
                'scheduled' => Inspection::today()->where('status', 'scheduled')->count(),
                'in_progress' => Inspection::today()->where('status', 'in_progress')->count(),
                'completed' => Inspection::today()->where('status', 'completed')->count(),
            ],
            'this_week' => [
                'scheduled' => Inspection::thisWeek()->where('status', 'scheduled')->count(),
                'completed' => Inspection::thisWeek()->where('status', 'completed')->count(),
                'overdue' => Inspection::overdue()->count(),
            ],
            'recent_completed' => Inspection::with(['equipment', 'inspector'])
                ->where('status', 'completed')
                ->orderBy('completion_time', 'desc')
                ->limit(5)
                ->get(),
            'upcoming' => Inspection::with(['equipment', 'inspector'])
                ->where('status', 'scheduled')
                ->where('scheduled_date', '>', now())
                ->orderBy('scheduled_date', 'asc')
                ->limit(5)
                ->get(),
            'requires_action' => Inspection::with(['equipment', 'inspector'])
                ->whereHas('inspectionResults', function ($q) {
                    $q->where('requires_action', true);
                })
                ->orderBy('completion_time', 'desc')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * Duplicate an inspection
     */
    public function duplicateInspection(int $inspectionId): Inspection
    {
        return DB::transaction(function () use ($inspectionId) {
            $originalInspection = Inspection::with(['inspectionItems'])->findOrFail($inspectionId);
            
            $newInspectionData = $originalInspection->toArray();
            unset($newInspectionData['id'], $newInspectionData['created_at'], $newInspectionData['updated_at'], $newInspectionData['deleted_at']);
            
            // Reset status and dates
            $newInspectionData['status'] = 'scheduled';
            $newInspectionData['overall_result'] = 'pending';
            $newInspectionData['inspection_date'] = null;
            $newInspectionData['completion_time'] = null;
            $newInspectionData['scheduled_date'] = now()->addDay();
            $newInspectionData['created_by'] = Auth::id();
            $newInspectionData['updated_by'] = Auth::id();

            $newInspection = Inspection::create($newInspectionData);

            // Duplicate inspection items
            foreach ($originalInspection->inspectionItems as $item) {
                $itemData = $item->toArray();
                unset($itemData['id'], $itemData['inspection_id'], $itemData['created_at'], $itemData['updated_at'], $itemData['deleted_at']);
                
                $itemData['inspection_id'] = $newInspection->id;
                $itemData['created_by'] = Auth::id();
                $itemData['updated_by'] = Auth::id();
                
                InspectionItem::create($itemData);
            }

            return $newInspection->load(['equipment', 'inspector', 'inspectionItems']);
        });
    }

    /**
     * Generate inspection from template
     */
    public function generateInspectionFromTemplate(array $data): Inspection
    {
        return DB::transaction(function () use ($data) {
            $equipment = Equipment::findOrFail($data['equipment_id']);
            
            $inspectionData = [
                'equipment_id' => $data['equipment_id'],
                'inspector_id' => $data['inspector_id'],
                'inspection_type' => $data['inspection_type'],
                'scheduled_date' => $data['scheduled_date'],
                'status' => 'scheduled',
                'overall_result' => 'pending',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ];

            $inspection = Inspection::create($inspectionData);
            
            // Generate inspection items from template
            $this->generateInspectionItemsFromTemplate($inspection, $data['frequency'] ?? null);

            return $inspection->load(['equipment', 'inspector', 'inspectionItems']);
        });
    }

    /**
     * Apply filters to inspection query
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['equipment_id'])) {
            $query->where('equipment_id', $filters['equipment_id']);
        }

        if (isset($filters['inspector_id'])) {
            $query->where('inspector_id', $filters['inspector_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['inspection_type'])) {
            $query->where('inspection_type', $filters['inspection_type']);
        }

        if (isset($filters['overall_result'])) {
            $query->where('overall_result', $filters['overall_result']);
        }

        if (isset($filters['date_from'])) {
            $query->where('scheduled_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('scheduled_date', '<=', $filters['date_to']);
        }
    }

    /**
     * Generate inspection items from template
     */
    private function generateInspectionItemsFromTemplate(Inspection $inspection, ?string $frequency = null): void
    {
        $equipment = $inspection->equipment;
        
        $templateItemsQuery = InspectionTemplateItem::where('equipment_type_id', $equipment->equipment_type_id)
            ->where('is_active', true);

        if ($frequency) {
            $templateItemsQuery->where('frequency', $frequency);
        }

        $templateItems = $templateItemsQuery->ordered()->get();

        foreach ($templateItems as $templateItem) {
            InspectionItem::create([
                'inspection_id' => $inspection->id,
                'inspection_template_item_id' => $templateItem->id,
                'item_name' => $templateItem->item_name,
                'item_description' => $templateItem->item_description,
                'category' => $templateItem->category,
                'item_type' => $templateItem->item_type,
                'is_required' => $templateItem->is_required,
                'order_sequence' => $templateItem->order_sequence,
                'min_value' => $templateItem->min_value,
                'max_value' => $templateItem->max_value,
                'unit_of_measure' => $templateItem->unit_of_measure,
                'expected_condition' => $templateItem->expected_condition,
                'safety_critical' => $templateItem->safety_critical,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        }
    }

    /**
     * Create inspection items
     */
    private function createInspectionItems(Inspection $inspection, array $items): void
    {
        foreach ($items as $itemData) {
            $itemData['inspection_id'] = $inspection->id;
            $itemData['created_by'] = Auth::id();
            $itemData['updated_by'] = Auth::id();
            
            InspectionItem::create($itemData);
        }
    }

    /**
     * Update inspection items
     */
    private function updateInspectionItems(Inspection $inspection, array $items): void
    {
        foreach ($items as $itemData) {
            if (isset($itemData['id'])) {
                $item = InspectionItem::where('inspection_id', $inspection->id)
                    ->findOrFail($itemData['id']);
                $itemData['updated_by'] = Auth::id();
                $item->update($itemData);
            } else {
                $itemData['inspection_id'] = $inspection->id;
                $itemData['created_by'] = Auth::id();
                $itemData['updated_by'] = Auth::id();
                InspectionItem::create($itemData);
            }
        }
    }

    /**
     * Update inspection results
     */
    private function updateInspectionResults(Inspection $inspection, array $results): void
    {
        foreach ($results as $resultData) {
            if (isset($resultData['id'])) {
                $result = InspectionResult::where('inspection_id', $inspection->id)
                    ->findOrFail($resultData['id']);
                $resultData['updated_by'] = Auth::id();
                $result->update($resultData);
            } else {
                $resultData['inspection_id'] = $inspection->id;
                $resultData['created_by'] = Auth::id();
                $resultData['updated_by'] = Auth::id();
                $resultData['timestamp_checked'] = now();
                InspectionResult::create($resultData);
            }
        }
    }

    /**
     * Calculate overall result for inspection
     */
    private function calculateOverallResult(Inspection $inspection): void
    {
        $results = $inspection->inspectionResults()
            ->whereIn('result_status', ['pass', 'fail', 'warning'])
            ->get();

        if ($results->isEmpty()) {
            $inspection->update(['overall_result' => 'pending']);
            return;
        }

        $hasFail = $results->contains('result_status', 'fail');
        $hasWarning = $results->contains('result_status', 'warning');

        if ($hasFail) {
            $overallResult = 'fail';
        } elseif ($hasWarning) {
            $overallResult = 'warning';
        } else {
            $overallResult = 'pass';
        }

        $inspection->update(['overall_result' => $overallResult]);
    }

    /**
     * Validate inspection completion
     */
    private function validateInspectionCompletion(Inspection $inspection): void
    {
        $requiredItems = $inspection->inspectionItems()->where('is_required', true)->count();
        $completedRequiredItems = $inspection->inspectionResults()
            ->whereHas('inspectionItem', function ($q) {
                $q->where('is_required', true);
            })
            ->whereNotIn('result_status', ['pending'])
            ->count();

        if ($completedRequiredItems < $requiredItems) {
            throw new \Exception('All required inspection items must be completed before marking inspection as complete');
        }
    }

    /**
     * Get inspections by status for statistics
     */
    private function getInspectionsByStatus($dateFrom, $dateTo): array
    {
        return Inspection::whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * Get inspections by type for statistics
     */
    private function getInspectionsByType($dateFrom, $dateTo): array
    {
        return Inspection::whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('inspection_type')
            ->selectRaw('inspection_type, count(*) as count')
            ->pluck('count', 'inspection_type')
            ->toArray();
    }

    /**
     * Get inspections by result for statistics
     */
    private function getInspectionsByResult($dateFrom, $dateTo): array
    {
        return Inspection::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->groupBy('overall_result')
            ->selectRaw('overall_result, count(*) as count')
            ->pluck('count', 'overall_result')
            ->toArray();
    }

    /**
     * Get average completion time
     */
    private function getAverageCompletionTime($dateFrom, $dateTo): ?float
    {
        $completedInspections = Inspection::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->whereNotNull('inspection_date')
            ->whereNotNull('completion_time')
            ->get(['inspection_date', 'completion_time']);

        if ($completedInspections->isEmpty()) {
            return null;
        }

        $totalMinutes = $completedInspections->sum(function ($inspection) {
            return $inspection->inspection_date->diffInMinutes($inspection->completion_time);
        });

        return round($totalMinutes / $completedInspections->count(), 2);
    }

    /**
     * Get most active inspectors
     */
    private function getMostActiveInspectors($dateFrom, $dateTo): array
    {
        return Inspection::with('inspector')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('inspector_id')
            ->selectRaw('inspector_id, count(*) as inspection_count')
            ->orderByDesc('inspection_count')
            ->limit(5)
            ->get()
            ->map(function ($inspection) {
                return [
                    'inspector' => $inspection->inspector->full_name,
                    'inspection_count' => $inspection->inspection_count,
                ];
            })
            ->toArray();
    }

    /**
     * Get equipment inspection frequency
     */
    private function getEquipmentInspectionFrequency($dateFrom, $dateTo): array
    {
        return Inspection::with('equipment')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->groupBy('equipment_id')
            ->selectRaw('equipment_id, count(*) as inspection_count')
            ->orderByDesc('inspection_count')
            ->limit(10)
            ->get()
            ->map(function ($inspection) {
                return [
                    'equipment' => $inspection->equipment->name,
                    'inspection_count' => $inspection->inspection_count,
                ];
            })
            ->toArray();
    }
}