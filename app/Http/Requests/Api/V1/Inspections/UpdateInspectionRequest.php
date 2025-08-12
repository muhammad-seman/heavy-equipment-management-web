<?php

namespace App\Http\Requests\Api\V1\Inspections;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Inspection;

class UpdateInspectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('inspection.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'equipment_id' => ['sometimes', 'exists:equipment,id'],
            'inspector_id' => ['sometimes', 'exists:users,id'],
            'inspection_type' => ['sometimes', 'in:' . implode(',', array_keys(Inspection::getInspectionTypes()))],
            'scheduled_date' => ['sometimes', 'date'],
            'inspection_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:' . implode(',', array_keys(Inspection::getInspectionStatuses()))],
            'overall_result' => ['sometimes', 'in:' . implode(',', array_keys(Inspection::getInspectionResults()))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_data' => ['nullable', 'json'],
            'completion_time' => ['nullable', 'date'],
            'weather_conditions' => ['nullable', 'json'],
            'operating_hours_before' => ['nullable', 'numeric', 'min:0'],
            'operating_hours_after' => ['nullable', 'numeric', 'min:0', 'gte:operating_hours_before'],
            'fuel_level_before' => ['nullable', 'integer', 'between:0,100'],
            'fuel_level_after' => ['nullable', 'integer', 'between:0,100'],
            'location' => ['nullable', 'string', 'max:255'],
            'temperature' => ['nullable', 'numeric', 'between:-50,80'],
            'humidity' => ['nullable', 'integer', 'between:0,100'],
            
            // Update inspection items
            'inspection_items' => ['sometimes', 'array'],
            'inspection_items.*.id' => ['sometimes', 'exists:inspection_items,id'],
            'inspection_items.*.item_name' => ['required_with:inspection_items', 'string', 'max:255'],
            'inspection_items.*.item_description' => ['nullable', 'string', 'max:1000'],
            'inspection_items.*.category' => ['required_with:inspection_items', 'in:engine,hydraulic,electrical,structural,safety,operational,maintenance,fluids,attachments,documentation'],
            'inspection_items.*.item_type' => ['required_with:inspection_items', 'in:visual,measurement,functional,checklist,photo,signature,text,numeric,boolean'],
            'inspection_items.*.is_required' => ['sometimes', 'boolean'],
            'inspection_items.*.order_sequence' => ['sometimes', 'integer', 'min:1'],
            'inspection_items.*.min_value' => ['nullable', 'numeric'],
            'inspection_items.*.max_value' => ['nullable', 'numeric', 'gte:inspection_items.*.min_value'],
            'inspection_items.*.unit_of_measure' => ['nullable', 'string', 'max:50'],
            'inspection_items.*.expected_condition' => ['nullable', 'string', 'max:255'],
            'inspection_items.*.safety_critical' => ['sometimes', 'boolean'],
            
            // Update inspection results
            'inspection_results' => ['sometimes', 'array'],
            'inspection_results.*.id' => ['sometimes', 'exists:inspection_results,id'],
            'inspection_results.*.inspection_item_id' => ['required_with:inspection_results', 'exists:inspection_items,id'],
            'inspection_results.*.result_value' => ['nullable', 'json'],
            'inspection_results.*.result_status' => ['required_with:inspection_results', 'in:pass,fail,warning,not_applicable,pending,requires_recheck'],
            'inspection_results.*.result_notes' => ['nullable', 'string', 'max:1000'],
            'inspection_results.*.measured_value' => ['nullable', 'numeric'],
            'inspection_results.*.photo_path' => ['nullable', 'string', 'max:255'],
            'inspection_results.*.signature_data' => ['nullable', 'json'],
            'inspection_results.*.is_within_tolerance' => ['nullable', 'boolean'],
            'inspection_results.*.deviation_percentage' => ['nullable', 'numeric', 'between:-100,100'],
            'inspection_results.*.requires_action' => ['sometimes', 'boolean'],
            'inspection_results.*.action_required' => ['nullable', 'in:none,monitor,repair,replace,adjust,clean,lubricate,tighten,investigate,shutdown'],
            'inspection_results.*.priority_level' => ['sometimes', 'in:low,medium,high,critical'],
            'inspection_results.*.inspector_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'equipment_id.exists' => 'Selected equipment does not exist',
            'inspector_id.exists' => 'Selected inspector does not exist',
            'inspection_type.in' => 'Invalid inspection type selected',
            'scheduled_date.date' => 'Invalid scheduled date format',
            'status.in' => 'Invalid status selected',
            'overall_result.in' => 'Invalid overall result selected',
            'operating_hours_after.gte' => 'Operating hours after must be greater than or equal to hours before',
            'fuel_level_before.between' => 'Fuel level before must be between 0 and 100 percent',
            'fuel_level_after.between' => 'Fuel level after must be between 0 and 100 percent',
            'temperature.between' => 'Temperature must be between -50°C and 80°C',
            'humidity.between' => 'Humidity must be between 0 and 100 percent',
            'inspection_items.*.item_name.required_with' => 'Item name is required',
            'inspection_items.*.category.required_with' => 'Item category is required',
            'inspection_items.*.item_type.required_with' => 'Item type is required',
            'inspection_items.*.max_value.gte' => 'Maximum value must be greater than or equal to minimum value',
            'inspection_results.*.inspection_item_id.required_with' => 'Inspection item is required for result',
            'inspection_results.*.inspection_item_id.exists' => 'Selected inspection item does not exist',
            'inspection_results.*.result_status.required_with' => 'Result status is required',
            'inspection_results.*.result_status.in' => 'Invalid result status selected',
            'inspection_results.*.action_required.in' => 'Invalid action required selected',
            'inspection_results.*.priority_level.in' => 'Invalid priority level selected',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'equipment_id' => 'equipment',
            'inspector_id' => 'inspector',
            'inspection_type' => 'inspection type',
            'scheduled_date' => 'scheduled date',
            'inspection_date' => 'inspection date',
            'overall_result' => 'overall result',
            'completion_time' => 'completion time',
            'weather_conditions' => 'weather conditions',
            'operating_hours_before' => 'operating hours before',
            'operating_hours_after' => 'operating hours after',
            'fuel_level_before' => 'fuel level before',
            'fuel_level_after' => 'fuel level after',
            'signature_data' => 'signature data',
            'inspection_items' => 'inspection items',
            'inspection_results' => 'inspection results',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $inspection = $this->route('inspection');
            
            // Check if inspection can be modified
            if ($inspection && $inspection->status === 'completed' && $this->has('equipment_id', 'inspector_id', 'inspection_type', 'scheduled_date')) {
                $validator->errors()->add('status', 'Cannot modify core details of completed inspections');
            }

            // Validate that inspector has permission to inspect this equipment type
            if ($this->equipment_id && $this->inspector_id) {
                $equipment = \App\Models\Equipment::find($this->equipment_id);
                $inspector = \App\Models\User::find($this->inspector_id);
                
                if ($equipment && $inspector && !$inspector->can('inspection.edit')) {
                    $validator->errors()->add('inspector_id', 'Selected inspector does not have permission to modify inspections');
                }
            }

            // Validate completion time is after inspection date
            if ($this->inspection_date && $this->completion_time) {
                $inspectionDate = \Carbon\Carbon::parse($this->inspection_date);
                $completionTime = \Carbon\Carbon::parse($this->completion_time);
                
                if ($completionTime->lt($inspectionDate)) {
                    $validator->errors()->add('completion_time', 'Completion time must be after inspection start time');
                }
            }

            // Validate status transitions
            if ($inspection && $this->status) {
                $this->validateStatusTransition($validator, $inspection->status, $this->status);
            }

            // Validate inspection items belong to this inspection
            if ($this->inspection_items) {
                foreach ($this->inspection_items as $index => $item) {
                    if (isset($item['id'])) {
                        $inspectionItem = \App\Models\InspectionItem::find($item['id']);
                        if ($inspectionItem && $inspectionItem->inspection_id !== $inspection->id) {
                            $validator->errors()->add("inspection_items.{$index}.id", 'Inspection item does not belong to this inspection');
                        }
                    }
                }
            }

            // Validate inspection results belong to this inspection
            if ($this->inspection_results) {
                foreach ($this->inspection_results as $index => $result) {
                    if (isset($result['id'])) {
                        $inspectionResult = \App\Models\InspectionResult::find($result['id']);
                        if ($inspectionResult && $inspectionResult->inspection_id !== $inspection->id) {
                            $validator->errors()->add("inspection_results.{$index}.id", 'Inspection result does not belong to this inspection');
                        }
                    }
                    
                    // Validate inspection item belongs to this inspection
                    if (isset($result['inspection_item_id'])) {
                        $inspectionItem = \App\Models\InspectionItem::find($result['inspection_item_id']);
                        if ($inspectionItem && $inspectionItem->inspection_id !== $inspection->id) {
                            $validator->errors()->add("inspection_results.{$index}.inspection_item_id", 'Inspection item does not belong to this inspection');
                        }
                    }
                }
            }
        });
    }

    /**
     * Validate status transition
     */
    private function validateStatusTransition($validator, string $currentStatus, string $newStatus): void
    {
        $validTransitions = [
            'scheduled' => ['in_progress', 'cancelled', 'completed'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [], // Completed inspections cannot change status
            'overdue' => ['in_progress', 'cancelled', 'completed'],
            'cancelled' => ['scheduled'], // Can reschedule cancelled inspections
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            $validator->errors()->add('status', "Cannot change status from {$currentStatus} to {$newStatus}");
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $inspection = $this->route('inspection');
        
        // Auto-set inspection date when starting inspection
        if ($this->status === 'in_progress' && $inspection && $inspection->status === 'scheduled' && !$this->has('inspection_date')) {
            $this->merge(['inspection_date' => now()->toISOString()]);
        }

        // Auto-set completion time when completing inspection
        if ($this->status === 'completed' && $inspection && $inspection->status !== 'completed' && !$this->has('completion_time')) {
            $this->merge(['completion_time' => now()->toISOString()]);
        }

        // Set timestamp for inspection results
        if ($this->inspection_results) {
            $results = $this->inspection_results;
            foreach ($results as $index => $result) {
                if (!isset($result['timestamp_checked'])) {
                    $results[$index]['timestamp_checked'] = now()->toISOString();
                }
            }
            $this->merge(['inspection_results' => $results]);
        }
    }
}