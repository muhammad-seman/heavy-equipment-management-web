<?php

namespace App\Http\Resources\Api\V1\Equipment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
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
            'asset_number' => $this->asset_number,
            'serial_number' => $this->serial_number,
            'model' => $this->model,
            'year_manufactured' => $this->year_manufactured,
            
            // Relationships
            'category' => $this->whenLoaded('category', function () {
                return new EquipmentCategoryResource($this->category);
            }),
            'type' => $this->whenLoaded('type', function () {
                return new EquipmentTypeResource($this->type);
            }),
            'manufacturer' => $this->whenLoaded('manufacturer', function () {
                return new ManufacturerResource($this->manufacturer);
            }),
            'assigned_user' => $this->whenLoaded('assignedUser', function () {
                return [
                    'id' => $this->assignedUser->id,
                    'full_name' => $this->assignedUser->full_name,
                    'employee_id' => $this->assignedUser->employee_id,
                    'department' => $this->assignedUser->department,
                    'position' => $this->assignedUser->position,
                ];
            }),
            
            // Purchase and warranty info
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'warranty_expiry' => $this->warranty_expiry?->format('Y-m-d'),
            'warranty_status' => $this->warranty_status,
            
            // Technical specifications
            'specifications' => [
                'operating_weight' => $this->operating_weight ? (float) $this->operating_weight : null,
                'engine_power' => $this->engine_power ? (float) $this->engine_power : null,
                'bucket_capacity' => $this->bucket_capacity ? (float) $this->bucket_capacity : null,
                'max_digging_depth' => $this->max_digging_depth ? (float) $this->max_digging_depth : null,
                'max_reach' => $this->max_reach ? (float) $this->max_reach : null,
                'travel_speed' => $this->travel_speed ? (float) $this->travel_speed : null,
                'fuel_capacity' => $this->fuel_capacity ? (float) $this->fuel_capacity : null,
            ],
            
            // Operational data
            'operational' => [
                'total_operating_hours' => (float) $this->total_operating_hours,
                'total_distance_km' => (float) $this->total_distance_km,
                'last_service_hours' => (float) $this->last_service_hours,
                'next_service_hours' => $this->next_service_hours ? (float) $this->next_service_hours : null,
                'hours_until_service' => $this->next_service_hours 
                    ? max(0, (float) $this->next_service_hours - (float) $this->total_operating_hours)
                    : null,
            ],
            
            // Status and location
            'status' => $this->status,
            'status_changed_at' => $this->status_changed_at?->toISOString(),
            'status_notes' => $this->status_notes,
            'assigned_to_site' => $this->assigned_to_site,
            
            // Location data
            'location' => [
                'latitude' => $this->current_location_lat ? (float) $this->current_location_lat : null,
                'longitude' => $this->current_location_lng ? (float) $this->current_location_lng : null,
                'address' => $this->current_location_address,
            ],
            
            // Ownership and financial data
            'ownership' => [
                'type' => $this->ownership_type,
                'lease_start_date' => $this->lease_start_date?->format('Y-m-d'),
                'lease_end_date' => $this->lease_end_date?->format('Y-m-d'),
                'lease_cost_monthly' => $this->lease_cost_monthly ? (float) $this->lease_cost_monthly : null,
            ],
            
            'financial' => $this->when($request->user()->can('equipment.view_financial'), [
                'purchase_price' => $this->purchase_price ? (float) $this->purchase_price : null,
                'current_book_value' => $this->current_book_value ? (float) $this->current_book_value : null,
                'depreciation_rate' => $this->depreciation_rate ? (float) $this->depreciation_rate : null,
            ]),
            
            // Insurance
            'insurance' => [
                'policy' => $this->insurance_policy,
                'expiry' => $this->insurance_expiry?->format('Y-m-d'),
            ],
            
            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}