<?php

namespace App\Http\Requests\Api\V1\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRolePermissionsRequest extends FormRequest
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
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'permissions.required' => 'At least one permission must be specified',
            'permissions.array' => 'Permissions must be provided as an array',
            'permissions.*.exists' => 'One or more selected permissions do not exist',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'permissions' => 'role permissions',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $role = $this->route('role');
            
            // Ensure super_admin role always has all permissions
            if ($role->name === 'super_admin') {
                $allPermissions = \Spatie\Permission\Models\Permission::pluck('name')->toArray();
                $missingPermissions = array_diff($allPermissions, $this->permissions);
                
                if (!empty($missingPermissions)) {
                    $validator->errors()->add('permissions', 'Super Admin role must have all permissions assigned.');
                }
            }
        });
    }
}