<?php

namespace App\Http\Resources\Api\V1\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'employee_id' => $this->employee_id,
            'phone' => $this->phone,
            
            // Work information
            'work_info' => [
                'department' => $this->department,
                'position' => $this->position,
                'hire_date' => $this->hire_date?->format('Y-m-d'),
                'is_active' => $this->is_active,
            ],
            
            // Certification information
            'certification' => [
                'level' => $this->certification_level,
                'expiry' => $this->certification_expiry?->format('Y-m-d'),
                'has_active_certification' => $this->hasActiveCertification(),
                'is_expiring_soon' => $this->isCertificationExpiringSoon(),
                'days_until_expiry' => $this->certification_expiry 
                    ? now()->diffInDays($this->certification_expiry, false)
                    : null,
            ],
            
            // Emergency contact (only for self or with permission)
            'emergency_contact' => $this->when(
                $request->user()?->id === $this->id || $request->user()?->can('users.view_emergency_contact'),
                [
                    'name' => $this->emergency_contact_name,
                    'phone' => $this->emergency_contact_phone,
                    'relationship' => $this->emergency_contact_relationship,
                ]
            ),
            
            // Personal information (only for self or with permission)
            'personal_info' => $this->when(
                $request->user()?->id === $this->id || $request->user()?->can('users.view_personal'),
                [
                    'address' => $this->address,
                    'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
                ]
            ),
            
            // Role and permissions
            'roles' => $this->whenLoaded('roles', function () {
                return $this->getRoleNames();
            }),
            'permissions' => $this->when(
                $this->relationLoaded('roles'),
                $this->getAllPermissions()->pluck('name')
            ),
            
            // Activity information
            'last_login' => $this->last_login?->toISOString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            
            // Notes (only for users with permission)
            'notes' => $this->when(
                $request->user()?->can('users.view_notes'),
                $this->notes
            ),
            
            // Assigned equipment (if loaded)
            'assigned_equipment' => $this->whenLoaded('assignedEquipment', function () {
                return $this->assignedEquipment->map(function ($equipment) {
                    return [
                        'id' => $equipment->id,
                        'asset_number' => $equipment->asset_number,
                        'model' => $equipment->model,
                        'status' => $equipment->status,
                        'type' => $equipment->type->name ?? null,
                        'manufacturer' => $equipment->manufacturer->name ?? null,
                    ];
                });
            }),
            
            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}