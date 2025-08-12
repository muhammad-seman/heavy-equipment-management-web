<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;

class StorePermissionRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name', 'regex:/^[a-z_]+\.[a-z_]+$/'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'guard_name' => ['sometimes', 'string', 'in:web,api'],
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
            'guard_name.in' => 'Guard name must be either web or api',
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

        // Set default guard_name if not provided
        if (!$this->has('guard_name')) {
            $this->merge([
                'guard_name' => 'web',
            ]);
        }

        // Generate display_name if not provided
        if (!$this->has('display_name') && $this->has('name')) {
            $this->merge([
                'display_name' => $this->generateDisplayName($this->name),
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
            'guard_name' => 'guard name',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check for system permissions that shouldn't be created manually
            $systemModules = ['equipment', 'inspection', 'maintenance', 'users', 'roles', 'permissions', 'system', 'reports', 'sessions'];
            
            if ($this->name) {
                $parts = explode('.', $this->name);
                $module = $parts[0] ?? '';
                
                if (in_array($module, $systemModules)) {
                    $validator->errors()->add('name', 'Cannot create system permission manually. Use permission sync instead.');
                }
            }
        });
    }

    /**
     * Generate display name from permission name
     */
    private function generateDisplayName(string $permissionName): string
    {
        $parts = explode('.', $permissionName);
        $module = ucwords(str_replace('_', ' ', $parts[0]));
        $action = ucwords(str_replace('_', ' ', $parts[1] ?? ''));
        
        return "{$action} {$module}";
    }
}