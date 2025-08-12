<?php

namespace App\Http\Resources\Api\V1\Equipment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturerResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'country' => $this->country,
            'website' => $this->website,
            
            // Contact information
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
            ],
            
            'description' => $this->description,
            'logo_url' => $this->logo_url,
            'is_active' => $this->is_active,
            
            // Counts (if loaded)
            'equipment_count' => $this->when(
                isset($this->equipment_count),
                $this->equipment_count
            ),
            'equipment_types_count' => $this->when(
                isset($this->equipment_types_count),
                $this->equipment_types_count
            ),
            'active_equipment_count' => $this->when(
                isset($this->active_equipment_count),
                $this->active_equipment_count
            ),
            
            // Related data (if loaded)
            'equipment_types' => $this->whenLoaded('equipmentTypes', function () {
                return EquipmentTypeResource::collection($this->equipmentTypes);
            }),
            'equipment' => $this->whenLoaded('equipment', function () {
                return EquipmentResource::collection($this->equipment);
            }),
            
            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}