<?php

namespace App\Http\Resources\Api\V1\Equipment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EquipmentCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->transform(function ($equipment) {
                return new EquipmentResource($equipment);
            }),
            
            // Collection-level metadata
            'summary' => [
                'total_count' => $this->collection->count(),
                'status_breakdown' => $this->collection->groupBy('status')->map->count(),
                'ownership_breakdown' => $this->collection->groupBy('ownership_type')->map->count(),
                'average_operating_hours' => $this->collection->avg('total_operating_hours'),
                'total_value' => $this->collection->sum('current_book_value'),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'collection_type' => 'equipment',
                'generated_at' => now()->toISOString(),
                'filters_applied' => $request->only(['status', 'manufacturer_id', 'type_id', 'category_id']),
            ],
        ];
    }
}