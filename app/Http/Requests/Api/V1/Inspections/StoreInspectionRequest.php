<?php

namespace App\Http\Requests\Api\V1\Inspections;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Inspection;

class StoreInspectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('inspection.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'exists:equipment,id'],
            'inspector_id' => ['required', 'exists:users,id'],
            'inspection_type' => ['required', 'in:' . implode(',', array_keys(Inspection::getInspectionTypes()))],
            'scheduled_date' => ['required', 'date', 'after_or_equal:today'],
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
            
            // Optional: auto-generate inspection items from template
            'generate_from_template' => ['sometimes', 'boolean'],
            'frequency' => ['sometimes', 'string', 'in:daily,weekly,monthly,quarterly,semi_annual,annual,pre_operation,post_operation,maintenance'],
            
            // Custom inspection items
            'inspection_items' => ['sometimes', 'array'],
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
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'equipment_id.required' => 'Equipment is required for inspection',
            'equipment_id.exists' => 'Selected equipment does not exist',
            'inspector_id.required' => 'Inspector is required for inspection',
            'inspector_id.exists' => 'Selected inspector does not exist',
            'inspection_type.required' => 'Inspection type is required',
            'inspection_type.in' => 'Invalid inspection type selected',
            'scheduled_date.required' => 'Scheduled date is required',
            'scheduled_date.after_or_equal' => 'Scheduled date cannot be in the past',
            'operating_hours_after.gte' => 'Operating hours after must be greater than or equal to hours before',
            'fuel_level_before.between' => 'Fuel level before must be between 0 and 100 percent',
            'fuel_level_after.between' => 'Fuel level after must be between 0 and 100 percent',
            'temperature.between' => 'Temperature must be between -50°C and 80°C',
            'humidity.between' => 'Humidity must be between 0 and 100 percent',
            'inspection_items.*.item_name.required_with' => 'Item name is required',
            'inspection_items.*.category.required_with' => 'Item category is required',
            'inspection_items.*.item_type.required_with' => 'Item type is required',
            'inspection_items.*.max_value.gte' => 'Maximum value must be greater than or equal to minimum value',
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
            'generate_from_template' => 'generate from template',
            'inspection_items' => 'inspection items',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that inspector has permission to inspect this equipment type
            if ($this->equipment_id && $this->inspector_id) {
                $equipment = \App\Models\Equipment::find($this->equipment_id);
                $inspector = \App\Models\User::find($this->inspector_id);
                
                if ($equipment && $inspector && !$inspector->can('inspection.create')) {
                    $validator->errors()->add('inspector_id', 'Selected inspector does not have permission to perform inspections');
                }
            }

            // Validate scheduled date is not too far in the future
            if ($this->scheduled_date) {
                $scheduledDate = \Carbon\Carbon::parse($this->scheduled_date);
                if ($scheduledDate->gt(now()->addYear())) {
                    $validator->errors()->add('scheduled_date', 'Scheduled date cannot be more than 1 year in the future');
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

            // Validate status consistency
            if ($this->status === 'completed' && !$this->completion_time) {
                $validator->errors()->add('completion_time', 'Completion time is required when status is completed');
            }

            if ($this->status === 'in_progress' && !$this->inspection_date) {
                $validator->errors()->add('inspection_date', 'Inspection date is required when status is in progress');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge(['status' => 'scheduled']);
        }

        // Set default overall result if not provided
        if (!$this->has('overall_result')) {
            $this->merge(['overall_result' => 'pending']);
        }

        // Set inspection date if status is in_progress and not provided
        if ($this->status === 'in_progress' && !$this->has('inspection_date')) {
            $this->merge(['inspection_date' => now()->toISOString()]);
        }

        // Set completion time if status is completed and not provided
        if ($this->status === 'completed' && !$this->has('completion_time')) {
            $this->merge(['completion_time' => now()->toISOString()]);
        }

        // Set default frequency for template generation
        if ($this->generate_from_template && !$this->has('frequency')) {
            $this->merge(['frequency' => 'monthly']);
        }
    }
}