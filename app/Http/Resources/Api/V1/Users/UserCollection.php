<?php

namespace App\Http\Resources\Api\V1\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($user) use ($request) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'department' => $user->department,
                    'position' => $user->position,
                    'certification_level' => $user->certification_level,
                    'certification_expiry' => $user->certification_expiry?->format('Y-m-d'),
                    'is_active' => $user->is_active,
                    'last_login' => $user->last_login?->toISOString(),
                    
                    // Certification Status
                    'has_active_certification' => $user->hasActiveCertification(),
                    'is_certification_expiring_soon' => $user->isCertificationExpiringSoon(),
                    
                    // Roles (basic info only in collections)
                    'roles' => $user->getRoleNames(),
                    'primary_role' => $user->getRoleNames()->first(),
                    
                    // Equipment Assignment Summary
                    'assigned_equipment_count' => $user->assigned_equipment_count ?? 0,
                    'has_equipment_assigned' => ($user->assigned_equipment_count ?? 0) > 0,
                    
                    // Quick status indicators
                    'status_indicators' => [
                        'is_active' => $user->is_active,
                        'has_valid_certification' => $user->hasActiveCertification(),
                        'certification_warning' => $user->isCertificationExpiringSoon(),
                        'has_equipment' => ($user->assigned_equipment_count ?? 0) > 0,
                    ],
                    
                    // Timestamps
                    'created_at' => $user->created_at->toISOString(),
                ];
            }),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total_users' => $this->collection->count(),
                'active_users' => $this->collection->where('is_active', true)->count(),
                'inactive_users' => $this->collection->where('is_active', false)->count(),
                'users_with_equipment' => $this->collection->filter(function ($user) {
                    return ($user->assigned_equipment_count ?? 0) > 0;
                })->count(),
                'certification_expiring_soon' => $this->collection->filter(function ($user) {
                    return $user->isCertificationExpiringSoon();
                })->count(),
            ],
        ];
    }
}