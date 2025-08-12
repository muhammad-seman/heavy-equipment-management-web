<?php

namespace App\Http\Requests\Api\V1\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\MaintenanceRecord;

class StoreMaintenanceRequest extends FormRequest
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
        return [
            // Core relationships
            'equipment_id' => ['required', 'integer', 'exists:equipment,id'],
            'maintenance_schedule_id' => ['nullable', 'integer', 'exists:maintenance_schedules,id'],
            'assigned_technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],

            // Basic maintenance information
            'work_order_number' => [
                'nullable',
                'string',
                'max:100',
                'unique:maintenance_records,work_order_number'
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'maintenance_type' => [
                'required',
                'string',
                Rule::in(MaintenanceRecord::getMaintenanceTypeValues())
            ],
            'priority_level' => [
                'required',
                'string',
                Rule::in(MaintenanceRecord::getPriorityLevelValues())
            ],

            // Scheduling
            'scheduled_start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'scheduled_end_date' => ['nullable', 'date', 'after_or_equal:scheduled_start_date'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:43200'], // Max 30 days

            // Location and environment
            'location' => ['nullable', 'string', 'max:500'],
            'work_environment' => ['nullable', 'string', 'max:1000'],
            'safety_requirements' => ['nullable', 'array'],
            'safety_requirements.*' => ['string', 'max:255'],

            // Pre-maintenance readings
            'pre_operating_hours' => ['nullable', 'numeric', 'min:0'],
            'pre_odometer_reading' => ['nullable', 'numeric', 'min:0'],
            'pre_fuel_level' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pre_fluid_levels' => ['nullable', 'array'],
            'pre_fluid_levels.engine_oil' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pre_fluid_levels.hydraulic_fluid' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pre_fluid_levels.coolant' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pre_fluid_levels.brake_fluid' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Requirements and instructions
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:255'],
            'required_tools' => ['nullable', 'array'],
            'required_tools.*' => ['string', 'max:255'],
            'required_parts' => ['nullable', 'array'],
            'required_parts.*' => ['string', 'max:255'],
            'work_instructions' => ['nullable', 'array'],
            'work_instructions.*' => ['string', 'max:1000'],

            // Cost estimation
            'estimated_labor_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'estimated_parts_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'estimated_external_cost' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'approval_required' => ['boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],

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

            // Parts array for creating parts in the same request
            'parts' => ['nullable', 'array'],
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
            'parts.*.supplier' => ['nullable', 'string', 'max:255'],
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
            'parts.*.is_critical_part' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'equipment_id.required' => 'Equipment selection is required for maintenance records.',
            'equipment_id.exists' => 'The selected equipment does not exist.',
            'assigned_technician_id.exists' => 'The selected technician does not exist.',
            'supervisor_id.exists' => 'The selected supervisor does not exist.',
            'work_order_number.unique' => 'This work order number is already in use.',
            'title.required' => 'Maintenance title is required.',
            'maintenance_type.required' => 'Maintenance type is required.',
            'maintenance_type.in' => 'Invalid maintenance type selected.',
            'priority_level.required' => 'Priority level is required.',
            'priority_level.in' => 'Invalid priority level selected.',
            'scheduled_start_date.after_or_equal' => 'Scheduled start date cannot be in the past.',
            'scheduled_end_date.after_or_equal' => 'Scheduled end date must be after or equal to start date.',
            'estimated_duration_minutes.max' => 'Estimated duration cannot exceed 30 days (43,200 minutes).',
            'pre_fuel_level.max' => 'Fuel level cannot exceed 100%.',
            'warranty_claim_number.required_if' => 'Warranty claim number is required for warranty work.',
            'parts.*.part_number.required_with' => 'Part number is required when adding parts.',
            'parts.*.part_name.required_with' => 'Part name is required when adding parts.',
            'parts.*.category.required_with' => 'Part category is required when adding parts.',
            'parts.*.category.in' => 'Invalid part category selected.',
            'parts.*.quantity_used.required_with' => 'Quantity is required when adding parts.',
            'parts.*.quantity_used.min' => 'Part quantity must be greater than 0.',
            'parts.*.unit_cost.required_with' => 'Unit cost is required when adding parts.',
            'parts.*.unit_cost.min' => 'Unit cost cannot be negative.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate emergency maintenance requirements
            if ($this->maintenance_type === MaintenanceRecord::TYPE_EMERGENCY) {
                if ($this->priority_level !== MaintenanceRecord::PRIORITY_EMERGENCY && 
                    $this->priority_level !== MaintenanceRecord::PRIORITY_CRITICAL) {
                    $validator->errors()->add('priority_level', 'Emergency maintenance must have emergency or critical priority.');
                }
            }

            // Validate preventive maintenance scheduling
            if ($this->maintenance_type === MaintenanceRecord::TYPE_PREVENTIVE) {
                if (empty($this->scheduled_start_date)) {
                    $validator->errors()->add('scheduled_start_date', 'Preventive maintenance requires a scheduled start date.');
                }
            }

            // Validate warranty work requirements
            if ($this->warranty_work && empty($this->warranty_claim_number)) {
                $validator->errors()->add('warranty_claim_number', 'Warranty claim number is required for warranty work.');
            }

            // Validate cost approval requirements
            $totalEstimatedCost = ($this->estimated_labor_cost ?? 0) + 
                                ($this->estimated_parts_cost ?? 0) + 
                                ($this->estimated_external_cost ?? 0);
            
            if ($this->approval_threshold && $totalEstimatedCost > $this->approval_threshold) {
                if (!$this->approval_required) {
                    $validator->errors()->add('approval_required', 'Approval is required when estimated cost exceeds threshold.');
                }
            }

            // Validate parts total cost calculation
            if ($this->has('parts') && is_array($this->parts)) {
                foreach ($this->parts as $index => $part) {
                    if (isset($part['quantity_used']) && isset($part['unit_cost'])) {
                        $expectedTotal = $part['quantity_used'] * $part['unit_cost'];
                        if (isset($part['total_cost']) && abs($part['total_cost'] - $expectedTotal) > 0.01) {
                            $validator->errors()->add("parts.{$index}.total_cost", 'Total cost calculation is incorrect.');
                        }
                    }
                }
            }

            // Validate equipment operational status for non-emergency maintenance
            if ($this->maintenance_type !== MaintenanceRecord::TYPE_EMERGENCY) {
                // Additional validation could be added here to check equipment status
                // This would require querying the equipment model
            }

            // Validate technician qualifications for specific maintenance types
            if ($this->assigned_technician_id && in_array($this->maintenance_type, [
                MaintenanceRecord::TYPE_OVERHAUL,
                MaintenanceRecord::TYPE_PREDICTIVE
            ])) {
                // Additional validation for technician qualifications could be added here
            }
        });
    }

    /**
     * Get the validated data from the request with computed values.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated($key, $default);

        // Auto-generate work order number if not provided
        if (empty($validated['work_order_number'])) {
            $validated['work_order_number'] = $this->generateWorkOrderNumber();
        }

        // Calculate total estimated cost
        $validated['estimated_total_cost'] = 
            ($validated['estimated_labor_cost'] ?? 0) + 
            ($validated['estimated_parts_cost'] ?? 0) + 
            ($validated['estimated_external_cost'] ?? 0);

        // Set approval requirements based on cost and type
        if (!isset($validated['approval_required'])) {
            $validated['approval_required'] = $this->determineApprovalRequirement($validated);
        }

        // Set default status based on maintenance type
        $validated['status'] = $this->getDefaultStatus($validated['maintenance_type']);

        // Add created_by user
        $validated['created_by'] = auth()->id();

        return $validated;
    }

    /**
     * Generate a unique work order number
     */
    private function generateWorkOrderNumber(): string
    {
        $prefix = 'WO';
        $date = now()->format('Ymd');
        $sequence = str_pad(MaintenanceRecord::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$date}-{$sequence}";
    }

    /**
     * Determine if approval is required based on business rules
     */
    private function determineApprovalRequirement(array $data): bool
    {
        // Emergency maintenance always requires approval
        if ($data['maintenance_type'] === MaintenanceRecord::TYPE_EMERGENCY) {
            return true;
        }

        // High cost maintenance requires approval
        if (($data['estimated_total_cost'] ?? 0) > 5000) {
            return true;
        }

        // Critical priority requires approval
        if (in_array($data['priority_level'], [MaintenanceRecord::PRIORITY_CRITICAL, MaintenanceRecord::PRIORITY_EMERGENCY])) {
            return true;
        }

        // External contractor work requires approval
        if (!empty($data['external_contractor'])) {
            return true;
        }

        return false;
    }

    /**
     * Get default status based on maintenance type
     */
    private function getDefaultStatus(string $maintenanceType): string
    {
        return match ($maintenanceType) {
            MaintenanceRecord::TYPE_EMERGENCY => MaintenanceRecord::STATUS_PENDING_APPROVAL,
            MaintenanceRecord::TYPE_CORRECTIVE => MaintenanceRecord::STATUS_PENDING_APPROVAL,
            default => MaintenanceRecord::STATUS_SCHEDULED,
        };
    }
}