<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId)
            ],
            'phone' => 'nullable|string|max:20',
            'employee_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'employee_id')->ignore($userId)
            ],
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:150',
            'certification_level' => ['nullable', Rule::in(['basic', 'intermediate', 'advanced', 'expert'])],
            'certification_expiry' => 'nullable|date|after:today',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'emergency_contact_relationship' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'date_of_birth' => 'nullable|date|before:today',
            'hire_date' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already taken.',
            'employee_id.unique' => 'This employee ID is already taken.',
            'certification_expiry.after' => 'Certification expiry must be in the future.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
            'hire_date.before_or_equal' => 'Hire date cannot be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'email' => 'email address',
            'employee_id' => 'employee ID',
            'certification_level' => 'certification level',
            'certification_expiry' => 'certification expiry',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_phone' => 'emergency contact phone',
            'emergency_contact_relationship' => 'emergency contact relationship',
            'date_of_birth' => 'date of birth',
            'hire_date' => 'hire date',
        ];
    }
}