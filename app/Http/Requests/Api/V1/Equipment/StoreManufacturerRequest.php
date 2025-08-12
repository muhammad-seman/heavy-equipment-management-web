<?php

namespace App\Http\Requests\Api\V1\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class StoreManufacturerRequest extends FormRequest
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
            'name' => 'required|string|max:255|unique:manufacturers,name',
            'code' => 'required|string|max:20|unique:manufacturers,code',
            'country' => 'required|string|max:100',
            'website' => 'nullable|url|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'logo_url' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Manufacturer name is required.',
            'name.unique' => 'This manufacturer name already exists.',
            'code.required' => 'Manufacturer code is required.',
            'code.unique' => 'This manufacturer code already exists.',
            'country.required' => 'Country is required.',
            'website.url' => 'Website must be a valid URL.',
            'contact_email.email' => 'Contact email must be a valid email address.',
            'logo_url.url' => 'Logo URL must be a valid URL.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'manufacturer name',
            'code' => 'manufacturer code',
            'contact_email' => 'contact email',
            'contact_phone' => 'contact phone',
            'logo_url' => 'logo URL',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper($this->code ?? ''),
            'is_active' => $this->is_active ?? true,
        ]);
    }
}