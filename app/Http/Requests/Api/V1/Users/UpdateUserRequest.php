<?php

namespace App\Http\Requests\Api\V1\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('users.edit');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $userId],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[\d\s\-\(\)]{10,20}$/'],
            'employee_id' => ['sometimes', 'required', 'string', 'max:50', 'unique:users,employee_id,' . $userId, 'regex:/^[A-Z]{2,3}\d{3,4}$/'],
            'department' => ['sometimes', 'required', 'string', 'max:255'],
            'position' => ['sometimes', 'required', 'string', 'max:255'],
            'certification_level' => ['sometimes', 'required', Rule::in(['basic', 'intermediate', 'advanced', 'expert'])],
            'certification_expiry' => ['nullable', 'date', 'after:today'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required',
            'last_name.required' => 'Last name is required',
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email address is already registered',
            'employee_id.required' => 'Employee ID is required',
            'employee_id.unique' => 'This employee ID is already in use',
            'employee_id.regex' => 'Employee ID must follow format: AB123 or ABC1234',
            'phone.regex' => 'Please provide a valid phone number',
            'department.required' => 'Department is required',
            'position.required' => 'Position is required',
            'certification_level.required' => 'Certification level is required',
            'certification_level.in' => 'Certification level must be: basic, intermediate, advanced, or expert',
            'certification_expiry.after' => 'Certification expiry must be in the future',
            'roles.array' => 'Roles must be provided as an array',
            'roles.*.exists' => 'One or more selected roles do not exist',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert employee_id to uppercase
        if ($this->has('employee_id')) {
            $this->merge([
                'employee_id' => strtoupper($this->employee_id),
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'employee_id' => 'employee ID',
            'certification_level' => 'certification level',
            'certification_expiry' => 'certification expiry date',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_phone' => 'emergency contact phone',
        ];
    }
}