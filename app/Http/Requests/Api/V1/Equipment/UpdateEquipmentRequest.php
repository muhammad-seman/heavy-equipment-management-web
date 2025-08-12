<?php

namespace App\Http\Requests\Api\V1\Equipment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('equipment.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $equipmentId = $this->route('equipment');

        return [
            'equipment_type_id' => 'required|exists:equipment_types,id',
            'manufacturer_id' => 'required|exists:manufacturers,id',
            'asset_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('equipment', 'asset_number')->ignore($equipmentId)
            ],
            'serial_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('equipment', 'serial_number')->ignore($equipmentId)
            ],
            'model' => 'required|string|max:100',
            'year_manufactured' => 'required|integer|min:1980|max:' . (date('Y') + 1),
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'warranty_expiry' => 'nullable|date|after:purchase_date',
            
            // Technical specifications
            'engine_model' => 'nullable|string|max:100',
            'engine_serial' => 'nullable|string|max:100',
            'operating_weight' => 'nullable|numeric|min:0|max:999999.99',
            'engine_power' => 'nullable|numeric|min:0|max:99999.99',
            'bucket_capacity' => 'nullable|numeric|min:0|max:999.999',
            'max_digging_depth' => 'nullable|numeric|min:0|max:99.99',
            'max_reach' => 'nullable|numeric|min:0|max:99.99',
            'travel_speed' => 'nullable|numeric|min:0|max:999.99',
            'fuel_capacity' => 'nullable|numeric|min:0|max:9999.99',
            
            // Operational data
            'total_operating_hours' => 'nullable|numeric|min:0|max:99999.9',
            'total_distance_km' => 'nullable|numeric|min:0|max:9999999999.99',
            'last_service_hours' => 'nullable|numeric|min:0|max:99999.9',
            'next_service_hours' => 'nullable|numeric|min:0|max:99999.9|gte:last_service_hours',
            
            // Status and location  
            'status' => ['required', Rule::in(['active', 'maintenance', 'repair', 'standby', 'retired', 'disposal'])],
            'status_notes' => 'nullable|string|max:1000',
            
            // Ownership and assignment
            'ownership_type' => ['required', Rule::in(['owned', 'leased', 'rented'])],
            'lease_start_date' => 'nullable|date|required_if:ownership_type,leased,rented',
            'lease_end_date' => 'nullable|date|after:lease_start_date|required_if:ownership_type,leased,rented',
            'lease_cost_monthly' => 'nullable|numeric|min:0|max:999999999999.99|required_if:ownership_type,leased,rented',
            'assigned_to_user' => 'nullable|exists:users,id',
            'assigned_to_site' => 'nullable|string|max:100',
            'current_location_lat' => 'nullable|numeric|between:-90,90',
            'current_location_lng' => 'nullable|numeric|between:-180,180',
            'current_location_address' => 'nullable|string|max:500',
            
            // Financial data
            'purchase_price' => 'nullable|numeric|min:0|max:999999999999999.99',
            'current_book_value' => 'nullable|numeric|min:0|max:999999999999999.99',
            'depreciation_rate' => 'nullable|numeric|min:0|max:100.00',
            'insurance_policy' => 'nullable|string|max:100',
            'insurance_expiry' => 'nullable|date|after:today',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'equipment_type_id.required' => 'Equipment type is required.',
            'equipment_type_id.exists' => 'The selected equipment type is invalid.',
            'manufacturer_id.required' => 'Manufacturer is required.',
            'manufacturer_id.exists' => 'The selected manufacturer is invalid.',
            'asset_number.required' => 'Asset number is required.',
            'asset_number.unique' => 'This asset number already exists.',
            'serial_number.required' => 'Serial number is required.',
            'serial_number.unique' => 'This serial number already exists.',
            'year_manufactured.min' => 'Year manufactured must be 1980 or later.',
            'year_manufactured.max' => 'Year manufactured cannot be in the future.',
            'warranty_expiry.after' => 'Warranty expiry must be after the purchase date.',
            'next_service_hours.gte' => 'Next service hours must be greater than or equal to last service hours.',
            'lease_start_date.required_if' => 'Lease start date is required for leased/rented equipment.',
            'lease_end_date.required_if' => 'Lease end date is required for leased/rented equipment.',
            'lease_cost_monthly.required_if' => 'Monthly lease cost is required for leased/rented equipment.',
            'current_location_lat.between' => 'Latitude must be between -90 and 90.',
            'current_location_lng.between' => 'Longitude must be between -180 and 180.',
            'insurance_expiry.after' => 'Insurance expiry must be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'equipment_type_id' => 'equipment type',
            'manufacturer_id' => 'manufacturer',
            'asset_number' => 'asset number',
            'serial_number' => 'serial number',
            'year_manufactured' => 'year manufactured',
            'purchase_date' => 'purchase date',
            'warranty_expiry' => 'warranty expiry',
            'operating_weight' => 'operating weight',
            'engine_power' => 'engine power',
            'bucket_capacity' => 'bucket capacity',
            'total_operating_hours' => 'total operating hours',
            'last_service_hours' => 'last service hours',
            'next_service_hours' => 'next service hours',
            'current_location_lat' => 'latitude',
            'current_location_lng' => 'longitude',
            'lease_cost_monthly' => 'monthly lease cost',
        ];
    }
}