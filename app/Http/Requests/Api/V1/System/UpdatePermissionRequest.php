<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('permissions.assign');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $permissionId = $this->route('permission')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:permissions,name,' . $permissionId, 'regex:/^[a-z_]+\.[a-z_]+$/'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Permission name is required',
            'name.unique' => 'This permission name already exists',
            'name.regex' => 'Permission name must follow format: module.action (e.g., users.create)',
            'display_name.max' => 'Display name cannot be longer than 255 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normalize permission name to lowercase
        if ($this->has('name')) {
            $this->merge([
                'name' => strtolower($this->name),
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'permission name',
            'display_name' => 'display name',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $permission = $this->route('permission');
            
            // Check if trying to modify system permission name
            if ($this->has('name') && $this->isSystemPermission($permission->name) && $this->name !== $permission->name) {
                $validator->errors()->add('name', 'Cannot modify system permission name.');
            }
            
            // Check if trying to create a new system permission
            if ($this->has('name') && !$this->isSystemPermission($permission->name)) {
                $systemModules = ['equipment', 'inspection', 'maintenance', 'users', 'roles', 'permissions', 'system', 'reports', 'sessions'];
                $parts = explode('.', $this->name);
                $module = $parts[0] ?? '';
                
                if (in_array($module, $systemModules)) {
                    $validator->errors()->add('name', 'Cannot rename to system permission name.');
                }
            }
        });
    }

    /**
     * Check if permission is a system permission
     */
    private function isSystemPermission(string $permissionName): bool
    {
        $systemModules = ['equipment', 'inspection', 'maintenance', 'users', 'roles', 'permissions', 'system', 'reports', 'sessions'];
        $parts = explode('.', $permissionName);
        $module = $parts[0] ?? '';
        
        return in_array($module, $systemModules);
    }
}