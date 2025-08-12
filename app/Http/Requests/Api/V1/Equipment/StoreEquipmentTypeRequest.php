<?php

namespace App\Http\Requests\Api\V1\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('equipment.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:equipment_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:equipment_types,code',
            'description' => 'nullable|string|max:1000',
            'specifications' => 'nullable|array',
            'specifications.*' => 'string|max:500',
            'operating_weight_min' => 'nullable|numeric|min:0|max:999999.99',
            'operating_weight_max' => 'nullable|numeric|min:0|max:999999.99|gte:operating_weight_min',
            'engine_power_min' => 'nullable|numeric|min:0|max:99999.99',
            'engine_power_max' => 'nullable|numeric|min:0|max:99999.99|gte:engine_power_min',
            'bucket_capacity_min' => 'nullable|numeric|min:0|max:999.999',
            'bucket_capacity_max' => 'nullable|numeric|min:0|max:999.999|gte:bucket_capacity_min',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Equipment category is required.',
            'category_id.exists' => 'The selected equipment category is invalid.',
            'name.required' => 'Equipment type name is required.',
            'code.required' => 'Equipment type code is required.',
            'code.unique' => 'This equipment type code already exists.',
            'operating_weight_max.gte' => 'Maximum operating weight must be greater than or equal to minimum operating weight.',
            'engine_power_max.gte' => 'Maximum engine power must be greater than or equal to minimum engine power.',
            'bucket_capacity_max.gte' => 'Maximum bucket capacity must be greater than or equal to minimum bucket capacity.',
            'specifications.array' => 'Specifications must be an array.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'equipment category',
            'name' => 'equipment type name',
            'code' => 'equipment type code',
            'operating_weight_min' => 'minimum operating weight',
            'operating_weight_max' => 'maximum operating weight',
            'engine_power_min' => 'minimum engine power',
            'engine_power_max' => 'maximum engine power',
            'bucket_capacity_min' => 'minimum bucket capacity',
            'bucket_capacity_max' => 'maximum bucket capacity',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper($this->code),
            'is_active' => $this->is_active ?? true,
        ]);
    }
}