<?php

namespace App\Http\Resources\Api\V1\Equipment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentTypeResource extends JsonResource
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
            'description' => $this->description,
            'full_name' => $this->full_name,
            
            // Category relationship
            'category' => $this->whenLoaded('category', function () {
                return new EquipmentCategoryResource($this->category);
            }),
            
            // Technical specifications
            'specifications' => [
                'operating_weight' => [
                    'min' => $this->operating_weight_min ? (float) $this->operating_weight_min : null,
                    'max' => $this->operating_weight_max ? (float) $this->operating_weight_max : null,
                ],
                'engine_power' => [
                    'min' => $this->engine_power_min ? (float) $this->engine_power_min : null,
                    'max' => $this->engine_power_max ? (float) $this->engine_power_max : null,
                ],
                'bucket_capacity' => [
                    'min' => $this->bucket_capacity_min ? (float) $this->bucket_capacity_min : null,
                    'max' => $this->bucket_capacity_max ? (float) $this->bucket_capacity_max : null,
                ],
                'custom' => $this->specifications,
            ],
            
            'is_active' => $this->is_active,
            
            // Counts (if loaded)
            'equipment_count' => $this->when(
                isset($this->equipment_count),
                $this->equipment_count
            ),
            'active_equipment_count' => $this->when(
                isset($this->active_equipment_count),
                $this->active_equipment_count
            ),
            
            // Related equipment (if loaded)
            'equipment' => $this->whenLoaded('equipment', function () {
                return EquipmentResource::collection($this->equipment);
            }),
            
            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}