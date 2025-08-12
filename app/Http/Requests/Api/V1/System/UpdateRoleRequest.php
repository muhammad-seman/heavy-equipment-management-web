<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('roles.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $roleId = $this->route('role')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:roles,name,' . $roleId, 'regex:/^[a-z_]+$/'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
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
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'role name',
            'display_name' => 'display name',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $role = $this->route('role');
            $systemRoles = ['super_admin', 'admin', 'supervisor', 'inspector', 'operator', 'maintenance_tech', 'viewer'];
            
            // Check if trying to modify system role name
            if ($this->has('name') && in_array($role->name, $systemRoles) && $this->name !== $role->name) {
                $validator->errors()->add('name', 'Cannot modify system role name.');
            }
            
            // Check if trying to create a new system role
            if ($this->has('name') && in_array($this->name, $systemRoles) && !in_array($role->name, $systemRoles)) {
                $validator->errors()->add('name', 'Cannot rename to system role name.');
            }
        });
    }
}