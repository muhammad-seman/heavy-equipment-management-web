<?php

namespace App\Http\Resources\Api\V1\Inspections;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'inspector_id' => $this->inspector_id,
            'inspection_type' => $this->inspection_type,
            'inspection_date' => $this->inspection_date?->toISOString(),
            'scheduled_date' => $this->scheduled_date->toISOString(),
            'status' => $this->status,
            'overall_result' => $this->overall_result,
            'notes' => $this->notes,
            'signature_data' => $this->signature_data,
            'completion_time' => $this->completion_time?->toISOString(),
            'weather_conditions' => $this->weather_conditions,
            'operating_hours_before' => $this->operating_hours_before,
            'operating_hours_after' => $this->operating_hours_after,
            'fuel_level_before' => $this->fuel_level_before,
            'fuel_level_after' => $this->fuel_level_after,
            'location' => $this->location,
            'temperature' => $this->temperature,
            'humidity' => $this->humidity,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Formatted attributes
            'formatted_type' => $this->formatted_type,
            'formatted_status' => $this->formatted_status,
            'formatted_result' => $this->formatted_result,
            'is_overdue' => $this->is_overdue,
            'duration' => $this->duration,

            // Relationships
            'equipment' => $this->when(
                $this->relationLoaded('equipment'),
                function () {
                    return [
                        'id' => $this->equipment->id,
                        'name' => $this->equipment->name,
                        'serial_number' => $this->equipment->serial_number,
                        'model' => $this->equipment->model,
                        'status' => $this->equipment->status,
                        'equipment_type' => $this->when(
                            $this->equipment->relationLoaded('equipmentType'),
                            [
                                'id' => $this->equipment->equipmentType->id,
                                'name' => $this->equipment->equipmentType->name,
                            ]
                        ),
                        'manufacturer' => $this->when(
                            $this->equipment->relationLoaded('manufacturer'),
                            [
                                'id' => $this->equipment->manufacturer->id,
                                'name' => $this->equipment->manufacturer->name,
                            ]
                        ),
                    ];
                }
            ),

            'inspector' => $this->when(
                $this->relationLoaded('inspector'),
                [
                    'id' => $this->inspector->id,
                    'first_name' => $this->inspector->first_name,
                    'last_name' => $this->inspector->last_name,
                    'full_name' => $this->inspector->full_name,
                    'employee_id' => $this->inspector->employee_id,
                    'certification_level' => $this->inspector->certification_level,
                ]
            ),

            'creator' => $this->when(
                $this->relationLoaded('creator') && $this->creator,
                [
                    'id' => $this->creator->id,
                    'full_name' => $this->creator->full_name,
                ]
            ),

            'updater' => $this->when(
                $this->relationLoaded('updater') && $this->updater,
                [
                    'id' => $this->updater->id,
                    'full_name' => $this->updater->full_name,
                ]
            ),

            // Inspection items
            'inspection_items' => $this->when(
                $this->relationLoaded('inspectionItems'),
                InspectionItemResource::collection($this->inspectionItems)
            ),

            'inspection_items_count' => $this->when(
                isset($this->inspection_items_count),
                $this->inspection_items_count
            ),

            // Inspection results
            'inspection_results' => $this->when(
                $this->relationLoaded('inspectionResults'),
                InspectionResultResource::collection($this->inspectionResults)
            ),

            'inspection_results_count' => $this->when(
                isset($this->inspection_results_count),
                $this->inspection_results_count
            ),

            // Statistics and analysis
            'statistics' => $this->when(
                $this->relationLoaded('inspectionResults'),
                function () {
                    $results = $this->inspectionResults;
                    $total = $results->count();
                    
                    if ($total === 0) {
                        return [
                            'total_items' => $this->inspection_items_count ?? 0,
                            'completed_items' => 0,
                            'pending_items' => $this->inspection_items_count ?? 0,
                            'completion_percentage' => 0,
                            'pass_count' => 0,
                            'fail_count' => 0,
                            'warning_count' => 0,
                            'requires_action_count' => 0,
                            'critical_issues_count' => 0,
                        ];
                    }

                    $passCount = $results->where('result_status', 'pass')->count();
                    $failCount = $results->where('result_status', 'fail')->count();
                    $warningCount = $results->where('result_status', 'warning')->count();
                    $requiresActionCount = $results->where('requires_action', true)->count();
                    $criticalIssuesCount = $results->where('priority_level', 'critical')->count();
                    $completedCount = $results->whereNotIn('result_status', ['pending'])->count();

                    return [
                        'total_items' => $this->inspection_items_count ?? $total,
                        'completed_items' => $completedCount,
                        'pending_items' => ($this->inspection_items_count ?? $total) - $completedCount,
                        'completion_percentage' => $total > 0 ? round(($completedCount / ($this->inspection_items_count ?? $total)) * 100, 2) : 0,
                        'pass_count' => $passCount,
                        'fail_count' => $failCount,
                        'warning_count' => $warningCount,
                        'requires_action_count' => $requiresActionCount,
                        'critical_issues_count' => $criticalIssuesCount,
                        'pass_percentage' => $total > 0 ? round(($passCount / $total) * 100, 2) : 0,
                        'fail_percentage' => $total > 0 ? round(($failCount / $total) * 100, 2) : 0,
                        'warning_percentage' => $total > 0 ? round(($warningCount / $total) * 100, 2) : 0,
                    ];
                }
            ),

            // Action items summary
            'action_items' => $this->when(
                $this->relationLoaded('inspectionResults') && $request->user()->can('inspection.view'),
                function () {
                    $actionResults = $this->inspectionResults->where('requires_action', true);
                    
                    return $actionResults->map(function ($result) {
                        return [
                            'id' => $result->id,
                            'item_name' => $result->inspectionItem->item_name ?? 'Unknown Item',
                            'category' => $result->inspectionItem->category ?? 'unknown',
                            'result_status' => $result->result_status,
                            'action_required' => $result->action_required,
                            'priority_level' => $result->priority_level,
                            'inspector_notes' => $result->inspector_notes,
                            'formatted_priority' => $result->formatted_priority,
                            'formatted_action' => $result->formatted_action,
                        ];
                    })->values();
                }
            ),

            // Progress indicators
            'progress' => [
                'is_scheduled' => $this->status === 'scheduled',
                'is_in_progress' => $this->status === 'in_progress',
                'is_completed' => $this->status === 'completed',
                'is_overdue' => $this->is_overdue,
                'is_cancelled' => $this->status === 'cancelled',
                'can_start' => $this->status === 'scheduled',
                'can_complete' => in_array($this->status, ['scheduled', 'in_progress']),
                'can_cancel' => $this->status !== 'completed',
                'can_edit' => $this->status !== 'completed',
                'has_issues' => $this->when(
                    $this->relationLoaded('inspectionResults'),
                    $this->inspectionResults->whereIn('result_status', ['fail', 'warning'])->isNotEmpty()
                ),
                'requires_action' => $this->when(
                    $this->relationLoaded('inspectionResults'),
                    $this->inspectionResults->where('requires_action', true)->isNotEmpty()
                ),
            ],

            // Environmental data summary
            'environmental_summary' => $this->when(
                $this->temperature || $this->humidity || $this->weather_conditions,
                [
                    'temperature' => $this->temperature ? "{$this->temperature}°C" : null,
                    'humidity' => $this->humidity ? "{$this->humidity}%" : null,
                    'weather' => $this->weather_conditions,
                    'location' => $this->location,
                ]
            ),

            // Equipment metrics summary
            'equipment_metrics' => $this->when(
                $this->operating_hours_before || $this->operating_hours_after || $this->fuel_level_before || $this->fuel_level_after,
                [
                    'operating_hours' => [
                        'before' => $this->operating_hours_before,
                        'after' => $this->operating_hours_after,
                        'difference' => $this->operating_hours_after && $this->operating_hours_before 
                            ? round($this->operating_hours_after - $this->operating_hours_before, 2) 
                            : null,
                    ],
                    'fuel_level' => [
                        'before' => $this->fuel_level_before ? "{$this->fuel_level_before}%" : null,
                        'after' => $this->fuel_level_after ? "{$this->fuel_level_after}%" : null,
                        'consumption' => $this->fuel_level_before && $this->fuel_level_after 
                            ? ($this->fuel_level_before - $this->fuel_level_after) 
                            : null,
                    ],
                ]
            ),

            // Audit information
            'audit' => [
                'created_by' => $this->created_by,
                'updated_by' => $this->updated_by,
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString(),
                'days_since_creation' => $this->created_at->diffInDays(now()),
                'last_modified_by' => $this->when(
                    $this->relationLoaded('updater') && $this->updater,
                    $this->updater->full_name
                ),
            ],
        ];
    }
}