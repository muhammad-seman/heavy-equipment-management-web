<?php

namespace App\Http\Resources\Api\V1\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\V1\Equipment\EquipmentResource;
use App\Http\Resources\Api\V1\Users\UserResource;

class MaintenanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_order_number' => $this->work_order_number,
            'title' => $this->title,
            'description' => $this->description,
            
            // Core information
            'maintenance_type' => $this->maintenance_type,
            'maintenance_type_label' => $this->getMaintenanceTypeLabel(),
            'priority_level' => $this->priority_level,
            'priority_level_label' => $this->getPriorityLevelLabel(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'status_changed_at' => $this->status_changed_at?->toISOString(),
            
            // Relationships
            'equipment' => new EquipmentResource($this->whenLoaded('equipment')),
            'equipment_id' => $this->equipment_id,
            'maintenance_schedule_id' => $this->maintenance_schedule_id,
            'assigned_technician' => new UserResource($this->whenLoaded('assignedTechnician')),
            'assigned_technician_id' => $this->assigned_technician_id,
            'supervisor' => new UserResource($this->whenLoaded('supervisor')),
            'supervisor_id' => $this->supervisor_id,
            
            // Scheduling information
            'scheduled_start_date' => $this->scheduled_start_date?->toISOString(),
            'scheduled_end_date' => $this->scheduled_end_date?->toISOString(),
            'actual_start_date' => $this->actual_start_date?->toISOString(),
            'actual_end_date' => $this->actual_end_date?->toISOString(),
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'actual_duration_minutes' => $this->actual_duration_minutes,
            
            // Location and environment
            'location' => $this->location,
            'work_environment' => $this->work_environment,
            'safety_requirements' => $this->safety_requirements,
            
            // Pre-maintenance readings
            'pre_operating_hours' => $this->when($this->pre_operating_hours !== null, $this->pre_operating_hours),
            'pre_odometer_reading' => $this->when($this->pre_odometer_reading !== null, $this->pre_odometer_reading),
            'pre_fuel_level' => $this->when($this->pre_fuel_level !== null, $this->pre_fuel_level),
            'pre_fluid_levels' => $this->when($this->pre_fluid_levels !== null, $this->pre_fluid_levels),
            
            // Post-maintenance readings
            'post_operating_hours' => $this->when($this->post_operating_hours !== null, $this->post_operating_hours),
            'post_odometer_reading' => $this->when($this->post_odometer_reading !== null, $this->post_odometer_reading),
            'post_fuel_level' => $this->when($this->post_fuel_level !== null, $this->post_fuel_level),
            'post_fluid_levels' => $this->when($this->post_fluid_levels !== null, $this->post_fluid_levels),
            
            // Requirements and instructions
            'required_skills' => $this->required_skills,
            'required_tools' => $this->required_tools,
            'required_parts' => $this->required_parts,
            'work_instructions' => $this->work_instructions,
            
            // Cost information
            'estimated_labor_cost' => $this->when($this->estimated_labor_cost !== null, number_format($this->estimated_labor_cost, 2)),
            'estimated_parts_cost' => $this->when($this->estimated_parts_cost !== null, number_format($this->estimated_parts_cost, 2)),
            'estimated_external_cost' => $this->when($this->estimated_external_cost !== null, number_format($this->estimated_external_cost, 2)),
            'estimated_total_cost' => $this->when($this->estimated_total_cost !== null, number_format($this->estimated_total_cost, 2)),
            'actual_labor_cost' => $this->when($this->actual_labor_cost !== null, number_format($this->actual_labor_cost, 2)),
            'actual_parts_cost' => $this->when($this->actual_parts_cost !== null, number_format($this->actual_parts_cost, 2)),
            'actual_external_cost' => $this->when($this->actual_external_cost !== null, number_format($this->actual_external_cost, 2)),
            'actual_total_cost' => $this->when($this->actual_total_cost !== null, number_format($this->actual_total_cost, 2)),
            
            // Cost analysis
            'cost_variance' => $this->when(
                $this->actual_total_cost !== null && $this->estimated_total_cost !== null,
                function () {
                    $variance = $this->actual_total_cost - $this->estimated_total_cost;
                    return [
                        'amount' => number_format($variance, 2),
                        'percentage' => $this->estimated_total_cost > 0 
                            ? number_format(($variance / $this->estimated_total_cost) * 100, 2)
                            : null,
                        'status' => $variance > 0 ? 'over_budget' : ($variance < 0 ? 'under_budget' : 'on_budget')
                    ];
                }
            ),
            
            // Approval information
            'approval_required' => $this->approval_required,
            'approval_threshold' => $this->when($this->approval_threshold !== null, number_format($this->approval_threshold, 2)),
            'approved_by' => new UserResource($this->whenLoaded('approvedBy')),
            'approved_at' => $this->approved_at?->toISOString(),
            'rejected_by' => new UserResource($this->whenLoaded('rejectedBy')),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            
            // Completion information
            'completion_percentage' => $this->completion_percentage,
            'quality_rating' => $this->quality_rating,
            'completion_notes' => $this->completion_notes,
            
            // Additional information
            'warranty_work' => $this->warranty_work,
            'warranty_claim_number' => $this->warranty_claim_number,
            'external_contractor' => $this->external_contractor,
            'contractor_contact' => $this->contractor_contact,
            'special_instructions' => $this->special_instructions,
            'maintenance_notes' => $this->maintenance_notes,
            
            // Operational impact
            'downtime_start' => $this->downtime_start?->toISOString(),
            'downtime_end' => $this->downtime_end?->toISOString(),
            'downtime_duration_minutes' => $this->downtime_duration_minutes,
            'downtime_duration_formatted' => $this->when(
                $this->downtime_duration_minutes,
                $this->formatDuration($this->downtime_duration_minutes)
            ),
            'impact_on_operations' => $this->impact_on_operations,
            
            // Follow-up information
            'follow_up_required' => $this->follow_up_required,
            'follow_up_date' => $this->follow_up_date?->toISOString(),
            'follow_up_instructions' => $this->follow_up_instructions,
            
            // Documentation flags
            'documentation_complete' => $this->documentation_complete,
            'photos_attached' => $this->photos_attached,
            'reports_generated' => $this->reports_generated,
            
            // Parts information
            'parts' => MaintenancePartResource::collection($this->whenLoaded('maintenanceParts')),
            'parts_count' => $this->when($this->relationLoaded('maintenanceParts'), $this->maintenanceParts->count()),
            'total_parts_cost' => $this->when(
                $this->relationLoaded('maintenanceParts'),
                number_format($this->maintenanceParts->sum('total_cost'), 2)
            ),
            
            // Performance metrics
            'efficiency_rating' => $this->when(
                $this->actual_duration_minutes && $this->estimated_duration_minutes,
                function () {
                    $efficiency = ($this->estimated_duration_minutes / $this->actual_duration_minutes) * 100;
                    return [
                        'percentage' => number_format($efficiency, 2),
                        'status' => $efficiency >= 100 ? 'efficient' : 'delayed',
                        'time_variance_minutes' => $this->actual_duration_minutes - $this->estimated_duration_minutes
                    ];
                }
            ),
            
            // Status indicators
            'is_overdue' => $this->getIsOverdueAttribute(),
            'is_emergency' => $this->maintenance_type === 'emergency',
            'is_high_priority' => in_array($this->priority_level, ['high', 'critical', 'emergency']),
            'is_approved' => $this->status === 'approved',
            'is_completed' => $this->status === 'completed',
            'is_in_progress' => $this->status === 'in_progress',
            'is_on_hold' => $this->status === 'on_hold',
            'is_cancelled' => $this->status === 'cancelled',
            'is_pending_approval' => $this->status === 'pending_approval',
            
            // Days and time calculations
            'days_since_scheduled' => $this->when(
                $this->scheduled_start_date,
                now()->diffInDays($this->scheduled_start_date, false)
            ),
            'days_until_due' => $this->when(
                $this->scheduled_end_date,
                $this->scheduled_end_date->diffInDays(now(), false)
            ),
            'duration_formatted' => $this->when(
                $this->actual_duration_minutes,
                $this->formatDuration($this->actual_duration_minutes)
            ),
            'estimated_duration_formatted' => $this->when(
                $this->estimated_duration_minutes,
                $this->formatDuration($this->estimated_duration_minutes)
            ),
            
            // Audit information
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at?->toISOString()),
            
            // Additional computed fields for dashboard/summary views
            'summary' => $this->when(
                $request->query('include_summary'),
                function () {
                    return [
                        'status_color' => $this->getStatusColor(),
                        'priority_color' => $this->getPriorityColor(),
                        'progress_percentage' => $this->completion_percentage ?? 0,
                        'cost_status' => $this->getCostStatus(),
                        'timeline_status' => $this->getTimelineStatus(),
                        'next_action' => $this->getNextAction(),
                    ];
                }
            ),
        ];
    }

    /**
     * Format duration in minutes to human readable format
     */
    private function formatDuration(?int $minutes): ?string
    {
        if (!$minutes) {
            return null;
        }

        $days = intval($minutes / 1440);
        $hours = intval(($minutes % 1440) / 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
        }
        if ($hours > 0) {
            $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        if ($mins > 0) {
            $parts[] = $mins . ' minute' . ($mins > 1 ? 's' : '');
        }

        return implode(', ', $parts) ?: '0 minutes';
    }

    /**
     * Get status color for UI display
     */
    private function getStatusColor(): string
    {
        return match ($this->status) {
            'scheduled' => 'blue',
            'pending_approval' => 'yellow',
            'approved' => 'green',
            'in_progress' => 'orange',
            'on_hold' => 'gray',
            'completed' => 'green',
            'cancelled' => 'red',
            'rejected' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get priority color for UI display
     */
    private function getPriorityColor(): string
    {
        return match ($this->priority_level) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            'emergency' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get cost status indicator
     */
    private function getCostStatus(): string
    {
        if (!$this->actual_total_cost || !$this->estimated_total_cost) {
            return 'unknown';
        }

        $variance = ($this->actual_total_cost - $this->estimated_total_cost) / $this->estimated_total_cost;
        
        if ($variance > 0.1) {
            return 'over_budget';
        } elseif ($variance < -0.1) {
            return 'under_budget';
        } else {
            return 'on_budget';
        }
    }

    /**
     * Get timeline status indicator
     */
    private function getTimelineStatus(): string
    {
        if (!$this->scheduled_end_date) {
            return 'unknown';
        }

        if ($this->status === 'completed') {
            if ($this->actual_end_date && $this->actual_end_date <= $this->scheduled_end_date) {
                return 'on_time';
            } else {
                return 'delayed';
            }
        } else {
            if (now() > $this->scheduled_end_date) {
                return 'overdue';
            } elseif (now()->addDays(1) > $this->scheduled_end_date) {
                return 'due_soon';
            } else {
                return 'on_track';
            }
        }
    }

    /**
     * Get next action required for this maintenance
     */
    private function getNextAction(): ?string
    {
        return match ($this->status) {
            'scheduled' => 'Start maintenance work',
            'pending_approval' => 'Awaiting approval',
            'approved' => 'Begin scheduled work',
            'in_progress' => 'Continue work and update progress',
            'on_hold' => 'Resume when ready',
            'completed' => $this->follow_up_required ? 'Schedule follow-up' : null,
            'cancelled' => null,
            'rejected' => 'Address rejection reason and resubmit',
            default => null,
        };
    }
}