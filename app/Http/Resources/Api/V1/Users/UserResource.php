<?php

namespace App\Http\Resources\Api\V1\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'employee_id' => $this->employee_id,
            'department' => $this->department,
            'position' => $this->position,
            'certification_level' => $this->certification_level,
            'certification_expiry' => $this->certification_expiry?->format('Y-m-d'),
            'emergency_contact_name' => $this->when(
                $request->user()->can('users.view_details'),
                $this->emergency_contact_name
            ),
            'emergency_contact_phone' => $this->when(
                $request->user()->can('users.view_details'),
                $this->emergency_contact_phone
            ),
            'is_active' => $this->is_active,
            'last_login' => $this->last_login?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            // Certification Status
            'certification_status' => [
                'has_active_certification' => $this->hasActiveCertification(),
                'is_expiring_soon' => $this->isCertificationExpiringSoon(),
                'days_until_expiry' => $this->when(
                    $this->certification_expiry,
                    fn() => now()->diffInDays($this->certification_expiry, false)
                ),
            ],

            // Roles and Permissions
            'roles' => $this->getRoleNames(),
            'permissions' => $this->when(
                $request->user()->can('users.view_permissions'),
                $this->getAllPermissions()->pluck('name')
            ),

            // Equipment Assignment
            'equipment_assignment' => [
                'assigned_equipment_count' => $this->whenCounted('assignedEquipment'),
                'assigned_equipment' => $this->when(
                    $this->relationLoaded('assignedEquipment'),
                    function () {
                        return $this->assignedEquipment->map(function ($equipment) {
                            return [
                                'id' => $equipment->id,
                                'asset_number' => $equipment->asset_number,
                                'model' => $equipment->model,
                                'status' => $equipment->status,
                                'type' => $equipment->type?->name,
                                'manufacturer' => $equipment->manufacturer?->name,
                            ];
                        });
                    }
                ),
            ],

            // Profile Completeness (only for own profile or admins)
            'profile_completeness' => $this->when(
                $request->user()->id === $this->id || $request->user()->can('users.view_details'),
                function () {
                    $fields = [
                        'first_name' => !empty($this->first_name),
                        'last_name' => !empty($this->last_name),
                        'email' => !empty($this->email),
                        'phone' => !empty($this->phone),
                        'employee_id' => !empty($this->employee_id),
                        'department' => !empty($this->department),
                        'position' => !empty($this->position),
                        'certification_level' => !empty($this->certification_level),
                        'emergency_contact' => !empty($this->emergency_contact_name) && !empty($this->emergency_contact_phone),
                    ];

                    $totalFields = count($fields);
                    $completedFields = array_sum($fields);
                    $percentage = round(($completedFields / $totalFields) * 100, 1);

                    return [
                        'percentage' => $percentage,
                        'completed_fields' => $completedFields,
                        'total_fields' => $totalFields,
                        'missing_fields' => array_keys(array_filter($fields, fn($completed) => !$completed)),
                    ];
                }
            ),

            // Activity Summary (limited info)
            'activity_summary' => $this->when(
                $request->user()->can('users.view_activity'),
                [
                    'active_sessions' => $this->tokens()->count(),
                    'last_activity' => $this->last_login?->toISOString(),
                ]
            ),

            // Administrative Info (admin only)
            'administrative' => $this->when(
                $request->user()->can('users.view_admin_details'),
                [
                    'created_by' => $this->created_by,
                    'updated_by' => $this->updated_by,
                    'deleted_at' => $this->deleted_at?->toISOString(),
                ]
            ),
        ];
    }
}