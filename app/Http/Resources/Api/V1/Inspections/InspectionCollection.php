<?php

namespace App\Http\Resources\Api\V1\Inspections;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InspectionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($inspection) use ($request) {
                return [
                    'id' => $inspection->id,
                    'equipment_id' => $inspection->equipment_id,
                    'inspector_id' => $inspection->inspector_id,
                    'inspection_type' => $inspection->inspection_type,
                    'formatted_type' => $inspection->formatted_type,
                    'inspection_date' => $inspection->inspection_date?->toISOString(),
                    'scheduled_date' => $inspection->scheduled_date->toISOString(),
                    'status' => $inspection->status,
                    'formatted_status' => $inspection->formatted_status,
                    'overall_result' => $inspection->overall_result,
                    'formatted_result' => $inspection->formatted_result,
                    'completion_time' => $inspection->completion_time?->toISOString(),
                    'is_overdue' => $inspection->is_overdue,
                    'duration' => $inspection->duration,
                    'location' => $inspection->location,
                    'created_at' => $inspection->created_at->toISOString(),

                    // Equipment summary
                    'equipment' => $this->when(
                        $inspection->relationLoaded('equipment'),
                        [
                            'id' => $inspection->equipment->id,
                            'name' => $inspection->equipment->name,
                            'serial_number' => $inspection->equipment->serial_number,
                            'model' => $inspection->equipment->model,
                            'status' => $inspection->equipment->status,
                            'equipment_type_name' => $inspection->equipment->equipmentType->name ?? null,
                            'manufacturer_name' => $inspection->equipment->manufacturer->name ?? null,
                        ]
                    ),

                    // Inspector summary
                    'inspector' => $this->when(
                        $inspection->relationLoaded('inspector'),
                        [
                            'id' => $inspection->inspector->id,
                            'full_name' => $inspection->inspector->full_name,
                            'employee_id' => $inspection->inspector->employee_id,
                            'certification_level' => $inspection->inspector->certification_level,
                        ]
                    ),

                    // Quick statistics
                    'statistics' => [
                        'items_count' => $inspection->inspection_items_count ?? 0,
                        'results_count' => $inspection->inspection_results_count ?? 0,
                        'completion_percentage' => $this->calculateCompletionPercentage($inspection),
                        'has_issues' => $this->hasIssues($inspection),
                        'requires_action' => $this->requiresAction($inspection),
                        'critical_issues_count' => $this->getCriticalIssuesCount($inspection),
                    ],

                    // Status indicators
                    'indicators' => [
                        'is_scheduled' => $inspection->status === 'scheduled',
                        'is_in_progress' => $inspection->status === 'in_progress',
                        'is_completed' => $inspection->status === 'completed',
                        'is_overdue' => $inspection->is_overdue,
                        'is_cancelled' => $inspection->status === 'cancelled',
                        'priority_level' => $this->getPriorityLevel($inspection),
                        'urgency_score' => $this->getUrgencyScore($inspection),
                    ],

                    // Quick actions available
                    'actions' => [
                        'can_start' => $inspection->status === 'scheduled',
                        'can_complete' => in_array($inspection->status, ['scheduled', 'in_progress']),
                        'can_cancel' => $inspection->status !== 'completed',
                        'can_edit' => $inspection->status !== 'completed',
                        'can_duplicate' => true,
                        'can_view_details' => $request->user()->can('inspection.view'),
                    ],

                    // Time information
                    'timing' => [
                        'scheduled_at' => $inspection->scheduled_date->toISOString(),
                        'started_at' => $inspection->inspection_date?->toISOString(),
                        'completed_at' => $inspection->completion_time?->toISOString(),
                        'days_until_scheduled' => $inspection->scheduled_date->diffInDays(now(), false),
                        'days_since_completion' => $inspection->completion_time 
                            ? $inspection->completion_time->diffInDays(now()) 
                            : null,
                        'is_due_today' => $inspection->scheduled_date->isToday(),
                        'is_due_this_week' => $inspection->scheduled_date->isCurrentWeek(),
                    ],

                    // Environmental conditions summary
                    'environment' => $this->when(
                        $inspection->temperature || $inspection->humidity || $inspection->weather_conditions,
                        [
                            'temperature' => $inspection->temperature ? "{$inspection->temperature}°C" : null,
                            'humidity' => $inspection->humidity ? "{$inspection->humidity}%" : null,
                            'conditions' => $inspection->weather_conditions,
                        ]
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
        // Calculate summary statistics for the collection
        $totalInspections = $this->collection->count();
        $completedInspections = $this->collection->where('status', 'completed')->count();
        $overdueInspections = $this->collection->where('is_overdue', true)->count();
        $inProgressInspections = $this->collection->where('status', 'in_progress')->count();
        $scheduledInspections = $this->collection->where('status', 'scheduled')->count();

        // Calculate inspection results summary
        $inspectionsWithIssues = $this->collection->filter(function ($inspection) {
            return $this->hasIssues($inspection);
        })->count();

        $inspectionsRequiringAction = $this->collection->filter(function ($inspection) {
            return $this->requiresAction($inspection);
        })->count();

        // Group by type
        $byType = $this->collection->groupBy('inspection_type')->map(function ($inspections, $type) {
            return [
                'type' => $type,
                'count' => $inspections->count(),
                'completed' => $inspections->where('status', 'completed')->count(),
                'overdue' => $inspections->where('is_overdue', true)->count(),
            ];
        })->values();

        // Group by status
        $byStatus = $this->collection->groupBy('status')->map(function ($inspections, $status) {
            return [
                'status' => $status,
                'count' => $inspections->count(),
                'percentage' => $this->collection->count() > 0 
                    ? round(($inspections->count() / $this->collection->count()) * 100, 2) 
                    : 0,
            ];
        })->values();

        // Group by result
        $byResult = $this->collection->where('status', 'completed')
            ->groupBy('overall_result')->map(function ($inspections, $result) {
                return [
                    'result' => $result,
                    'count' => $inspections->count(),
                ];
            })->values();

        // Equipment with most inspections
        $equipmentFrequency = $this->collection->filter(function ($inspection) {
            return $inspection->relationLoaded('equipment');
        })->groupBy('equipment.id')->map(function ($inspections, $equipmentId) {
            $equipment = $inspections->first()->equipment;
            return [
                'equipment_id' => $equipmentId,
                'equipment_name' => $equipment->name,
                'inspection_count' => $inspections->count(),
                'last_inspection' => $inspections->sortByDesc('scheduled_date')->first()->scheduled_date->toISOString(),
            ];
        })->sortByDesc('inspection_count')->take(5)->values();

        // Inspector workload
        $inspectorWorkload = $this->collection->filter(function ($inspection) {
            return $inspection->relationLoaded('inspector');
        })->groupBy('inspector.id')->map(function ($inspections, $inspectorId) {
            $inspector = $inspections->first()->inspector;
            return [
                'inspector_id' => $inspectorId,
                'inspector_name' => $inspector->full_name,
                'inspection_count' => $inspections->count(),
                'completed_count' => $inspections->where('status', 'completed')->count(),
                'overdue_count' => $inspections->where('is_overdue', true)->count(),
            ];
        })->sortByDesc('inspection_count')->take(5)->values();

        return [
            'meta' => [
                'total_inspections' => $totalInspections,
                'completed_inspections' => $completedInspections,
                'overdue_inspections' => $overdueInspections,
                'in_progress_inspections' => $inProgressInspections,
                'scheduled_inspections' => $scheduledInspections,
                'inspections_with_issues' => $inspectionsWithIssues,
                'inspections_requiring_action' => $inspectionsRequiringAction,
                'completion_rate' => $totalInspections > 0 
                    ? round(($completedInspections / $totalInspections) * 100, 2) 
                    : 0,
                'overdue_rate' => $totalInspections > 0 
                    ? round(($overdueInspections / $totalInspections) * 100, 2) 
                    : 0,
                'issue_rate' => $completedInspections > 0 
                    ? round(($inspectionsWithIssues / $completedInspections) * 100, 2) 
                    : 0,
            ],
            'summary' => [
                'by_type' => $byType,
                'by_status' => $byStatus,
                'by_result' => $byResult,
                'equipment_frequency' => $equipmentFrequency,
                'inspector_workload' => $inspectorWorkload,
            ],
            'alerts' => [
                'overdue_count' => $overdueInspections,
                'critical_issues' => $this->collection->filter(function ($inspection) {
                    return $this->getCriticalIssuesCount($inspection) > 0;
                })->count(),
                'high_priority_actions' => $this->collection->filter(function ($inspection) {
                    return $this->hasHighPriorityActions($inspection);
                })->count(),
            ],
        ];
    }

    /**
     * Calculate completion percentage for an inspection
     */
    private function calculateCompletionPercentage($inspection): float
    {
        $totalItems = $inspection->inspection_items_count ?? 0;
        $completedItems = $inspection->inspection_results_count ?? 0;
        
        return $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 2) : 0;
    }

    /**
     * Check if inspection has issues
     */
    private function hasIssues($inspection): bool
    {
        if (!$inspection->relationLoaded('inspectionResults')) {
            return false;
        }

        return $inspection->inspectionResults
            ->whereIn('result_status', ['fail', 'warning'])
            ->isNotEmpty();
    }

    /**
     * Check if inspection requires action
     */
    private function requiresAction($inspection): bool
    {
        if (!$inspection->relationLoaded('inspectionResults')) {
            return false;
        }

        return $inspection->inspectionResults
            ->where('requires_action', true)
            ->isNotEmpty();
    }

    /**
     * Get critical issues count
     */
    private function getCriticalIssuesCount($inspection): int
    {
        if (!$inspection->relationLoaded('inspectionResults')) {
            return 0;
        }

        return $inspection->inspectionResults
            ->where('priority_level', 'critical')
            ->count();
    }

    /**
     * Get priority level for inspection
     */
    private function getPriorityLevel($inspection): string
    {
        if ($inspection->is_overdue) {
            return 'critical';
        }

        if ($this->getCriticalIssuesCount($inspection) > 0) {
            return 'critical';
        }

        if ($this->hasIssues($inspection)) {
            return 'high';
        }

        if ($inspection->status === 'in_progress') {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Calculate urgency score (0-100)
     */
    private function getUrgencyScore($inspection): int
    {
        $score = 0;

        // Overdue inspections get highest priority
        if ($inspection->is_overdue) {
            $score += 50;
        }

        // Critical issues add significant urgency
        $criticalIssues = $this->getCriticalIssuesCount($inspection);
        $score += min($criticalIssues * 20, 30);

        // In progress inspections need attention
        if ($inspection->status === 'in_progress') {
            $score += 20;
        }

        // Due today gets medium priority
        if ($inspection->scheduled_date->isToday()) {
            $score += 15;
        }

        // Due this week gets low priority
        if ($inspection->scheduled_date->isCurrentWeek()) {
            $score += 5;
        }

        return min($score, 100);
    }

    /**
     * Check if inspection has high priority actions
     */
    private function hasHighPriorityActions($inspection): bool
    {
        if (!$inspection->relationLoaded('inspectionResults')) {
            return false;
        }

        return $inspection->inspectionResults
            ->where('requires_action', true)
            ->whereIn('priority_level', ['high', 'critical'])
            ->isNotEmpty();
    }
}