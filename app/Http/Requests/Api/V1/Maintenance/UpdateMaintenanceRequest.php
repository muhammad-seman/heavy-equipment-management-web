<?php

namespace App\Http\Requests\Api\V1\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\MaintenanceRecord;

class UpdateMaintenanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maintenanceRecord = $this->route('maintenanceRecord');
        
        return [
            // Core relationships (limited updates)
            'assigned_technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],

            // Basic maintenance information
            'work_order_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('maintenance_records', 'work_order_number')->ignore($maintenanceRecord?->id)
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'maintenance_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(MaintenanceRecord::getMaintenanceTypeValues())
            ],
            'priority_level' => [
                'sometimes',
                'required',
                'string',
                Rule::in(MaintenanceRecord::getPriorityLevelValues())
            ],

            // Status updates
            'status' => [
                'sometimes',
                'string',
                Rule::in(MaintenanceRecord::getStatusValues())
            ],

            // Scheduling updates
            'scheduled_start_date' => ['nullable', 'date'],
            'scheduled_end_date' => ['nullable', 'date', 'after_or_equal:scheduled_start_date'],
            'actual_start_date' => ['nullable', 'date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:actual_start_date'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'actual_duration_minutes' => ['nullable', 'integer', 'min:1'],

            // Location and environment
            'location' => ['nullable', 'string', 'max:500'],
            'work_environment' => ['nullable', 'string', 'max:1000'],
            'safety_requirements' => ['nullable', 'array'],
            'safety_requirements.*' => ['string', 'max:255'],

            // Pre-maintenance readings (usually not updated)
            'pre_operating_hours' => ['nullable', 'numeric', 'min:0'],
            'pre_odometer_reading' => ['nullable', 'numeric', 'min:0'],
            'pre_fuel_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pre_fluid_levels' => ['nullable', 'array'],

            // Post-maintenance readings
            'post_operating_hours' => ['nullable', 'numeric', 'min:0'],
            'post_odometer_reading' => ['nullable', 'numeric', 'min:0'],
            'post_fuel_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'post_fluid_levels' => ['nullable', 'array'],
            'post_fluid_levels.engine_oil' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'post_fluid_levels.hydraulic_fluid' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'post_fluid_levels.coolant' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'post_fluid_levels.brake_fluid' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Requirements and instructions
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:255'],
            'required_tools' => ['nullable', 'array'],
            'required_tools.*' => ['string', 'max:255'],
            'required_parts' => ['nullable', 'array'],
            'required_parts.*' => ['string', 'max:255'],
            'work_instructions' => ['nullable', 'array'],
            'work_instructions.*' => ['string', 'max:1000'],

            // Cost tracking
            'estimated_labor_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'estimated_parts_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'estimated_external_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'actual_labor_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'actual_parts_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'actual_external_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'approval_required' => ['boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],

            // Approval tracking
            'approved_by' => ['nullable', 'integer', 'exists:users,id'],
            'approved_at' => ['nullable', 'date'],
            'rejected_by' => ['nullable', 'integer', 'exists:users,id'],
            'rejected_at' => ['nullable', 'date'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],

            // Completion and quality
            'completion_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quality_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'completion_notes' => ['nullable', 'string', 'max:5000'],

            // Additional information
            'warranty_work' => ['boolean'],
            'warranty_claim_number' => [
                'nullable',
                'required_if:warranty_work,true',
                'string',
                'max:100'
            ],
            'external_contractor' => ['nullable', 'string', 'max:255'],
            'contractor_contact' => ['nullable', 'string', 'max:255'],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'maintenance_notes' => ['nullable', 'string', 'max:5000'],

            // Operational impact
            'downtime_start' => ['nullable', 'date'],
            'downtime_end' => ['nullable', 'date', 'after_or_equal:downtime_start'],
            'impact_on_operations' => ['nullable', 'string', 'max:1000'],

            // Follow-up requirements
            'follow_up_required' => ['boolean'],
            'follow_up_date' => ['nullable', 'required_if:follow_up_required,true', 'date', 'after:today'],
            'follow_up_instructions' => ['nullable', 'string', 'max:1000'],

            // Documentation
            'documentation_complete' => ['boolean'],
            'photos_attached' => ['boolean'],
            'reports_generated' => ['boolean'],

            // Parts updates (for existing parts)
            'parts' => ['nullable', 'array'],
            'parts.*.id' => ['nullable', 'integer', 'exists:maintenance_parts,id'],
            'parts.*.part_number' => ['required_with:parts', 'string', 'max:100'],
            'parts.*.part_name' => ['required_with:parts', 'string', 'max:255'],
            'parts.*.part_description' => ['nullable', 'string', 'max:1000'],
            'parts.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'parts.*.category' => [
                'required_with:parts',
                'string',
                Rule::in([
                    'engine', 'hydraulic', 'electrical', 'transmission', 'cooling', 'fuel',
                    'brake', 'track', 'attachment', 'cabin', 'filter', 'bearing', 
                    'seal', 'fastener', 'lubricant', 'consumable'
                ])
            ],
            'parts.*.quantity_used' => ['required_with:parts', 'numeric', 'min:0.01'],
            'parts.*.unit_of_measure' => ['nullable', 'string', 'max:20'],
            'parts.*.unit_cost' => ['required_with:parts', 'numeric', 'min:0'],
            'parts.*.total_cost' => ['required_with:parts', 'numeric', 'min:0'],
            'parts.*.supplier' => ['nullable', 'string', 'max:255'],
            'parts.*.purchase_order_number' => ['nullable', 'string', 'max:100'],
            'parts.*.part_condition' => [
                'nullable',
                'string',
                Rule::in(['new', 'refurbished', 'used', 'core_exchange'])
            ],
            'parts.*.part_source' => [
                'nullable',
                'string',
                Rule::in(['oem', 'aftermarket', 'internal_stock', 'emergency_purchase', 'warranty_replacement'])
            ],
            'parts.*.warranty_period_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'parts.*.warranty_expires_at' => ['nullable', 'date'],
            'parts.*.installation_date' => ['nullable', 'date'],
            'parts.*.installation_notes' => ['nullable', 'string', 'max:1000'],
            'parts.*.is_critical_part' => ['nullable', 'boolean'],
            'parts.*.old_part_condition' => [
                'nullable',
                'string',
                Rule::in(['serviceable', 'repairable', 'scrap', 'core_return', 'disposed'])
            ],
            'parts.*.old_part_disposed' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'assigned_technician_id.exists' => 'The selected technician does not exist.',
            'supervisor_id.exists' => 'The selected supervisor does not exist.',
            'work_order_number.unique' => 'This work order number is already in use.',
            'title.required' => 'Maintenance title is required.',
            'maintenance_type.in' => 'Invalid maintenance type selected.',
            'priority_level.in' => 'Invalid priority level selected.',
            'status.in' => 'Invalid status selected.',
            'scheduled_end_date.after_or_equal' => 'Scheduled end date must be after or equal to start date.',
            'actual_end_date.after_or_equal' => 'Actual end date must be after or equal to start date.',
            'estimated_duration_minutes.max' => 'Estimated duration cannot exceed 30 days (43,200 minutes).',
            'post_fuel_level.max' => 'Fuel level cannot exceed 100%.',
            'completion_percentage.max' => 'Completion percentage cannot exceed 100%.',
            'quality_rating.max' => 'Quality rating cannot exceed 5.',
            'warranty_claim_number.required_if' => 'Warranty claim number is required for warranty work.',
            'follow_up_date.required_if' => 'Follow-up date is required when follow-up is needed.',
            'follow_up_date.after' => 'Follow-up date must be in the future.',
            'downtime_end.after_or_equal' => 'Downtime end must be after or equal to downtime start.',
            'parts.*.part_number.required_with' => 'Part number is required when updating parts.',
            'parts.*.part_name.required_with' => 'Part name is required when updating parts.',
            'parts.*.category.required_with' => 'Part category is required when updating parts.',
            'parts.*.quantity_used.min' => 'Part quantity must be greater than 0.',
            'parts.*.unit_cost.min' => 'Unit cost cannot be negative.',
            'parts.*.total_cost.min' => 'Total cost cannot be negative.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $maintenanceRecord = $this->route('maintenanceRecord');

            // Validate status transitions
            if ($this->has('status') && $maintenanceRecord) {
                $currentStatus = $maintenanceRecord->status;
                $newStatus = $this->status;
                
                if (!$this->isValidStatusTransition($currentStatus, $newStatus)) {
                    $validator->errors()->add('status', "Invalid status transition from {$currentStatus} to {$newStatus}.");
                }
            }

            // Validate completion requirements
            if ($this->status === MaintenanceRecord::STATUS_COMPLETED) {
                if (empty($this->actual_end_date)) {
                    $validator->errors()->add('actual_end_date', 'Actual end date is required when completing maintenance.');
                }
                
                if (empty($this->completion_notes)) {
                    $validator->errors()->add('completion_notes', 'Completion notes are required when completing maintenance.');
                }

                if (($this->completion_percentage ?? 0) < 100) {
                    $validator->errors()->add('completion_percentage', 'Completion percentage must be 100% when maintenance is completed.');
                }
            }

            // Validate approval workflow
            if ($this->status === MaintenanceRecord::STATUS_APPROVED) {
                if (empty($this->approved_by)) {
                    $validator->errors()->add('approved_by', 'Approver is required when approving maintenance.');
                }
                if (empty($this->approved_at)) {
                    $this->merge(['approved_at' => now()]);
                }
            }

            if ($this->status === MaintenanceRecord::STATUS_REJECTED) {
                if (empty($this->rejected_by)) {
                    $validator->errors()->add('rejected_by', 'Rejector is required when rejecting maintenance.');
                }
                if (empty($this->rejection_reason)) {
                    $validator->errors()->add('rejection_reason', 'Rejection reason is required when rejecting maintenance.');
                }
                if (empty($this->rejected_at)) {
                    $this->merge(['rejected_at' => now()]);
                }
            }

            // Validate cost calculations
            if ($this->has('parts') && is_array($this->parts)) {
                foreach ($this->parts as $index => $part) {
                    if (isset($part['quantity_used']) && isset($part['unit_cost']) && isset($part['total_cost'])) {
                        $expectedTotal = $part['quantity_used'] * $part['unit_cost'];
                        if (abs($part['total_cost'] - $expectedTotal) > 0.01) {
                            $validator->errors()->add("parts.{$index}.total_cost", 'Total cost calculation is incorrect.');
                        }
                    }
                }
            }

            // Validate post-maintenance readings are greater than pre-maintenance
            if ($this->has('post_operating_hours') && $maintenanceRecord && $maintenanceRecord->pre_operating_hours) {
                if ($this->post_operating_hours < $maintenanceRecord->pre_operating_hours) {
                    $validator->errors()->add('post_operating_hours', 'Post-maintenance operating hours cannot be less than pre-maintenance hours.');
                }
            }

            if ($this->has('post_odometer_reading') && $maintenanceRecord && $maintenanceRecord->pre_odometer_reading) {
                if ($this->post_odometer_reading < $maintenanceRecord->pre_odometer_reading) {
                    $validator->errors()->add('post_odometer_reading', 'Post-maintenance odometer reading cannot be less than pre-maintenance reading.');
                }
            }

            // Validate downtime calculation
            if ($this->has('downtime_start') && $this->has('downtime_end')) {
                if ($this->downtime_end <= $this->downtime_start) {
                    $validator->errors()->add('downtime_end', 'Downtime end must be after downtime start.');
                }
            }

            // Validate warranty work completion
            if ($this->warranty_work && $this->status === MaintenanceRecord::STATUS_COMPLETED) {
                if (empty($this->warranty_claim_number)) {
                    $validator->errors()->add('warranty_claim_number', 'Warranty claim number is required for completed warranty work.');
                }
            }
        });
    }

    /**
     * Get the validated data from the request with computed values.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Calculate total actual cost if individual costs are provided
        if (isset($validated['actual_labor_cost']) || isset($validated['actual_parts_cost']) || isset($validated['actual_external_cost'])) {
            $validated['actual_total_cost'] = 
                ($validated['actual_labor_cost'] ?? 0) + 
                ($validated['actual_parts_cost'] ?? 0) + 
                ($validated['actual_external_cost'] ?? 0);
        }

        // Calculate downtime duration if both start and end are provided
        if (isset($validated['downtime_start']) && isset($validated['downtime_end'])) {
            $start = \Carbon\Carbon::parse($validated['downtime_start']);
            $end = \Carbon\Carbon::parse($validated['downtime_end']);
            $validated['downtime_duration_minutes'] = $end->diffInMinutes($start);
        }

        // Set status change timestamp
        if (isset($validated['status'])) {
            $validated['status_changed_at'] = now();
        }

        // Add updated_by user
        $validated['updated_by'] = auth()->id();

        return $validated;
    }

    /**
     * Check if status transition is valid
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            MaintenanceRecord::STATUS_SCHEDULED => [
                MaintenanceRecord::STATUS_IN_PROGRESS,
                MaintenanceRecord::STATUS_ON_HOLD,
                MaintenanceRecord::STATUS_CANCELLED,
                MaintenanceRecord::STATUS_PENDING_APPROVAL
            ],
            MaintenanceRecord::STATUS_PENDING_APPROVAL => [
                MaintenanceRecord::STATUS_APPROVED,
                MaintenanceRecord::STATUS_REJECTED,
                MaintenanceRecord::STATUS_CANCELLED
            ],
            MaintenanceRecord::STATUS_APPROVED => [
                MaintenanceRecord::STATUS_IN_PROGRESS,
                MaintenanceRecord::STATUS_SCHEDULED,
                MaintenanceRecord::STATUS_CANCELLED
            ],
            MaintenanceRecord::STATUS_IN_PROGRESS => [
                MaintenanceRecord::STATUS_COMPLETED,
                MaintenanceRecord::STATUS_ON_HOLD,
                MaintenanceRecord::STATUS_CANCELLED
            ],
            MaintenanceRecord::STATUS_ON_HOLD => [
                MaintenanceRecord::STATUS_IN_PROGRESS,
                MaintenanceRecord::STATUS_CANCELLED
            ],
            MaintenanceRecord::STATUS_COMPLETED => [
                // Completed maintenance can only be reopened in special circumstances
                MaintenanceRecord::STATUS_IN_PROGRESS // Only with special permissions
            ],
            MaintenanceRecord::STATUS_CANCELLED => [
                MaintenanceRecord::STATUS_SCHEDULED,
                MaintenanceRecord::STATUS_PENDING_APPROVAL
            ],
            MaintenanceRecord::STATUS_REJECTED => [
                MaintenanceRecord::STATUS_PENDING_APPROVAL,
                MaintenanceRecord::STATUS_CANCELLED
            ],
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
}