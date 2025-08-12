<?php

namespace App\Http\Resources\Api\V1\Inspections;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inspection_id' => $this->inspection_id,
            'inspection_item_id' => $this->inspection_item_id,
            'result_value' => $this->result_value,
            'result_status' => $this->result_status,
            'result_notes' => $this->result_notes,
            'measured_value' => $this->measured_value,
            'photo_path' => $this->photo_path,
            'signature_data' => $this->signature_data,
            'is_within_tolerance' => $this->is_within_tolerance,
            'deviation_percentage' => $this->deviation_percentage,
            'requires_action' => $this->requires_action,
            'action_required' => $this->action_required,
            'priority_level' => $this->priority_level,
            'inspector_notes' => $this->inspector_notes,
            'timestamp_checked' => $this->timestamp_checked?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Formatted attributes
            'formatted_status' => $this->formatted_status,
            'formatted_priority' => $this->formatted_priority,
            'formatted_action' => $this->formatted_action,
            'has_photo' => $this->has_photo,
            'has_signature' => $this->has_signature,
            'photo_url' => $this->photo_url,
            'is_problematic' => $this->is_problematic,
            'severity_level' => $this->severity_level,

            // Inspection item details
            'inspection_item' => $this->when(
                $this->relationLoaded('inspectionItem'),
                [
                    'id' => $this->inspectionItem->id,
                    'item_name' => $this->inspectionItem->item_name,
                    'category' => $this->inspectionItem->category,
                    'item_type' => $this->inspectionItem->item_type,
                    'is_required' => $this->inspectionItem->is_required,
                    'safety_critical' => $this->inspectionItem->safety_critical,
                    'expected_condition' => $this->inspectionItem->expected_condition,
                    'unit_of_measure' => $this->inspectionItem->unit_of_measure,
                    'min_value' => $this->inspectionItem->min_value,
                    'max_value' => $this->inspectionItem->max_value,
                ]
            ),

            // Creator information
            'creator' => $this->when(
                $this->relationLoaded('creator') && $this->creator,
                [
                    'id' => $this->creator->id,
                    'full_name' => $this->creator->full_name,
                ]
            ),

            // Analysis and validation
            'analysis' => [
                'is_pass' => $this->result_status === 'pass',
                'is_fail' => $this->result_status === 'fail',
                'is_warning' => $this->result_status === 'warning',
                'is_pending' => $this->result_status === 'pending',
                'needs_attention' => in_array($this->result_status, ['fail', 'warning', 'requires_recheck']),
                'within_tolerance' => $this->is_within_tolerance,
                'deviation' => $this->deviation_percentage,
                'action_needed' => $this->requires_action,
                'action_urgency' => $this->priority_level,
            ],

            // Compliance information
            'compliance' => $this->when(
                $this->relationLoaded('inspectionItem'),
                function () {
                    $item = $this->inspectionItem;
                    $compliance = [
                        'meets_requirements' => $this->result_status === 'pass',
                        'safety_compliant' => !($item->safety_critical && in_array($this->result_status, ['fail', 'warning'])),
                    ];

                    // Numeric validation compliance
                    if ($item->has_numeric_validation && $this->measured_value !== null) {
                        $value = $this->measured_value;
                        $compliance['within_min_range'] = $item->min_value === null || $value >= $item->min_value;
                        $compliance['within_max_range'] = $item->max_value === null || $value <= $item->max_value;
                        $compliance['within_numeric_tolerance'] = $compliance['within_min_range'] && $compliance['within_max_range'];
                    }

                    return $compliance;
                }
            ),

            // Action details
            'action_details' => $this->when(
                $this->requires_action,
                [
                    'action_type' => $this->action_required,
                    'priority' => $this->priority_level,
                    'urgency_score' => $this->getUrgencyScore(),
                    'estimated_time' => $this->getEstimatedActionTime(),
                    'safety_impact' => $this->getSafetyImpact(),
                    'cost_impact' => $this->getCostImpact(),
                ]
            ),

            // Quality metrics
            'quality_metrics' => [
                'result_confidence' => $this->getResultConfidence(),
                'data_completeness' => $this->getDataCompleteness(),
                'inspector_experience' => $this->when(
                    $this->relationLoaded('creator'),
                    $this->creator->certification_level ?? 'unknown'
                ),
                'timestamp_accuracy' => $this->timestamp_checked ? 'recorded' : 'estimated',
            ],

            // Historical context (if available)
            'historical_context' => $this->when(
                $request->get('include_history', false),
                [
                    'previous_results_count' => 0, // Would need additional query
                    'trend' => 'stable', // Would need historical analysis
                    'last_failure_date' => null, // Would need additional query
                    'failure_frequency' => 0, // Would need additional query
                ]
            ),
        ];
    }

    /**
     * Calculate urgency score for action items
     */
    private function getUrgencyScore(): int
    {
        $score = 0;

        // Priority level base score
        $priorityScores = [
            'low' => 10,
            'medium' => 30,
            'high' => 60,
            'critical' => 90,
        ];
        $score += $priorityScores[$this->priority_level] ?? 0;

        // Safety critical items get extra urgency
        if ($this->relationLoaded('inspectionItem') && $this->inspectionItem->safety_critical) {
            $score += 20;
        }

        // Failed items get extra urgency
        if ($this->result_status === 'fail') {
            $score += 15;
        }

        // Shutdown actions are most urgent
        if ($this->action_required === 'shutdown') {
            $score = 100;
        }

        return min($score, 100);
    }

    /**
     * Get estimated action time in hours
     */
    private function getEstimatedActionTime(): ?int
    {
        $actionTimes = [
            'monitor' => 1,
            'adjust' => 2,
            'clean' => 3,
            'lubricate' => 2,
            'tighten' => 1,
            'investigate' => 4,
            'repair' => 8,
            'replace' => 16,
            'shutdown' => 0, // Immediate action
        ];

        return $actionTimes[$this->action_required] ?? null;
    }

    /**
     * Get safety impact level
     */
    private function getSafetyImpact(): string
    {
        if ($this->action_required === 'shutdown') {
            return 'critical';
        }

        if ($this->relationLoaded('inspectionItem') && $this->inspectionItem->safety_critical) {
            return $this->result_status === 'fail' ? 'high' : 'medium';
        }

        return 'low';
    }

    /**
     * Get cost impact level
     */
    private function getCostImpact(): string
    {
        $costLevels = [
            'monitor' => 'low',
            'adjust' => 'low',
            'clean' => 'low',
            'lubricate' => 'low',
            'tighten' => 'low',
            'investigate' => 'medium',
            'repair' => 'medium',
            'replace' => 'high',
            'shutdown' => 'high', // Due to downtime
        ];

        return $costLevels[$this->action_required] ?? 'medium';
    }

    /**
     * Get result confidence level
     */
    private function getResultConfidence(): string
    {
        $confidence = 'high';

        // Lower confidence if no notes provided for problematic results
        if ($this->is_problematic && empty($this->result_notes)) {
            $confidence = 'medium';
        }

        // Lower confidence if measured value is significantly different from expected
        if ($this->deviation_percentage && abs($this->deviation_percentage) > 20) {
            $confidence = 'medium';
        }

        // Higher confidence if photo evidence provided
        if ($this->has_photo && $this->is_problematic) {
            $confidence = 'high';
        }

        return $confidence;
    }

    /**
     * Calculate data completeness percentage
     */
    private function getDataCompleteness(): float
    {
        $totalFields = 5; // result_status, result_value, notes, photo, signature
        $completedFields = 0;

        if ($this->result_status && $this->result_status !== 'pending') {
            $completedFields++;
        }
        if ($this->result_value) {
            $completedFields++;
        }
        if ($this->result_notes) {
            $completedFields++;
        }
        if ($this->photo_path) {
            $completedFields++;
        }
        if ($this->signature_data) {
            $completedFields++;
        }

        return round(($completedFields / $totalFields) * 100, 2);
    }
}