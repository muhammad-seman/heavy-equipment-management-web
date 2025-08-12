<?php

namespace App\Http\Resources\Api\V1\Inspections;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inspection_id' => $this->inspection_id,
            'inspection_template_item_id' => $this->inspection_template_item_id,
            'item_name' => $this->item_name,
            'item_description' => $this->item_description,
            'category' => $this->category,
            'item_type' => $this->item_type,
            'is_required' => $this->is_required,
            'order_sequence' => $this->order_sequence,
            'min_value' => $this->min_value,
            'max_value' => $this->max_value,
            'unit_of_measure' => $this->unit_of_measure,
            'expected_condition' => $this->expected_condition,
            'safety_critical' => $this->safety_critical,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Formatted attributes
            'formatted_type' => $this->formatted_type,
            'formatted_category' => $this->formatted_category,
            'has_numeric_validation' => $this->has_numeric_validation,
            'validation_message' => $this->validation_message,

            // Template relationship
            'template_item' => $this->when(
                $this->relationLoaded('inspectionTemplateItem') && $this->inspectionTemplateItem,
                [
                    'id' => $this->inspectionTemplateItem->id,
                    'frequency' => $this->inspectionTemplateItem->frequency,
                    'instructions' => $this->inspectionTemplateItem->instructions,
                ]
            ),

            // Inspection results
            'results' => $this->when(
                $this->relationLoaded('inspectionResults'),
                InspectionResultResource::collection($this->inspectionResults)
            ),

            'latest_result' => $this->when(
                $this->relationLoaded('inspectionResults') && $this->inspectionResults->isNotEmpty(),
                function () {
                    $latestResult = $this->inspectionResults->sortByDesc('timestamp_checked')->first();
                    return new InspectionResultResource($latestResult);
                }
            ),

            // Status indicators
            'status' => [
                'is_completed' => $this->when(
                    $this->relationLoaded('inspectionResults'),
                    $this->inspectionResults->whereNotIn('result_status', ['pending'])->isNotEmpty()
                ),
                'is_required' => $this->is_required,
                'is_safety_critical' => $this->safety_critical,
                'has_results' => $this->when(
                    $this->relationLoaded('inspectionResults'),
                    $this->inspectionResults->isNotEmpty()
                ),
                'requires_attention' => $this->when(
                    $this->relationLoaded('inspectionResults'),
                    $this->inspectionResults->whereIn('result_status', ['fail', 'warning'])->isNotEmpty()
                ),
            ],

            // Validation rules for frontend
            'validation' => [
                'is_numeric' => in_array($this->item_type, ['numeric', 'measurement']),
                'is_boolean' => $this->item_type === 'boolean',
                'requires_photo' => $this->item_type === 'photo',
                'requires_signature' => $this->item_type === 'signature',
                'min_value' => $this->min_value,
                'max_value' => $this->max_value,
                'unit' => $this->unit_of_measure,
                'expected' => $this->expected_condition,
            ],
        ];
    }
}