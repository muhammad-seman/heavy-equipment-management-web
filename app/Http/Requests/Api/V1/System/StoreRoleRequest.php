<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('roles.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:roles,name', 'regex:/^[a-z_]+$/'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'guard_name' => ['sometimes', 'string', 'in:web,api'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required',
            'name.unique' => 'This role name already exists',
            'name.regex' => 'Role name must contain only lowercase letters and underscores',
            'display_name.max' => 'Display name cannot be longer than 255 characters',
            'description.max' => 'Description cannot be longer than 500 characters',
            'guard_name.in' => 'Guard name must be either web or api',
            'permissions.array' => 'Permissions must be provided as an array',
            'permissions.*.exists' => 'One or more selected permissions do not exist',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize role name to lowercase with underscores
        if ($this->has('name')) {
            $this->merge([
                'name' => strtolower(str_replace(' ', '_', $this->name)),
            ]);
        }

        // Set default guard_name if not provided
        if (!$this->has('guard_name')) {
            $this->merge([
                'guard_name' => 'web',
            ]);
        }

        // Set default display_name if not provided
        if (!$this->has('display_name') && $this->has('name')) {
            $this->merge([
                'display_name' => ucwords(str_replace('_', ' ', $this->name)),
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'role name',
            'display_name' => 'display name',
            'guard_name' => 'guard name',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check for reserved system role names
            $reservedNames = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
            
            if (in_array($this->name, $reservedNames)) {
                $validator->errors()->add('name', 'Cannot create system role. This name is reserved.');
            }
        });
    }
}