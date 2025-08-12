<?php

namespace App\Services\Equipment;

use App\Models\EquipmentType;
use App\Models\EquipmentCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EquipmentTypeService
{
    /**
     * Get filtered and paginated equipment types
     */
    public function getFilteredEquipmentTypes(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = EquipmentType::with(['category'])->withCount(['equipment']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Create new equipment type with validation
     */
    public function createEquipmentType(array $data, User $user): EquipmentType
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateEquipmentTypeData($data);

            // Normalize data
            $data = $this->normalizeEquipmentTypeData($data);

            // Create equipment type
            $equipmentType = EquipmentType::create($data);

            DB::commit();

            return $equipmentType->load(['category'])->loadCount(['equipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update equipment type with business logic
     */
    public function updateEquipmentType(EquipmentType $equipmentType, array $data, User $user): EquipmentType
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateEquipmentTypeData($data, $equipmentType);

            // Check if changing category affects existing equipment
            if (isset($data['category_id']) && $data['category_id'] !== $equipmentType->category_id) {
                $this->validateCategoryChange($equipmentType, $data['category_id']);
            }

            // Normalize data
            $data = $this->normalizeEquipmentTypeData($data);

            // Check if deactivating equipment type with active equipment
            if (isset($data['is_active']) && !$data['is_active'] && $equipmentType->is_active) {
                $this->validateEquipmentTypeDeactivation($equipmentType);
            }

            // Update equipment type
            $equipmentType->update($data);

            DB::commit();

            return $equipmentType->fresh(['category'])->loadCount(['equipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete equipment type with safety checks
     */
    public function deleteEquipmentType(EquipmentType $equipmentType, User $user): bool
    {
        DB::beginTransaction();

        try {
            // Check if equipment type has any equipment
            $equipmentCount = $equipmentType->equipment()->count();
            if ($equipmentCount > 0) {
                throw new \InvalidArgumentException(
                    "Cannot delete equipment type. It has {$equipmentCount} equipment(s) associated with it."
                );
            }

            $equipmentType->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get equipment types by category
     */
    public function getByCategory(int $categoryId, bool $activeOnly = true): Collection
    {
        $query = EquipmentType::where('category_id', $categoryId)
            ->withCount(['equipment'])
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * Get equipment type analytics
     */
    public function getEquipmentTypeAnalytics(EquipmentType $equipmentType): array
    {
        return [
            'total_equipment' => $equipmentType->equipment()->count(),
            'active_equipment' => $equipmentType->equipment()->where('status', 'active')->count(),
            'equipment_by_status' => $equipmentType->equipment()
                ->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'equipment_by_manufacturer' => $equipmentType->equipment()
                ->with('manufacturer:id,name')
                ->get()
                ->groupBy('manufacturer.name')
                ->map->count()
                ->toArray(),
            'specifications_compliance' => $this->getSpecificationsCompliance($equipmentType),
            'utilization_stats' => $this->getUtilizationStats($equipmentType),
            'maintenance_stats' => $this->getMaintenanceStats($equipmentType),
        ];
    }

    /**
     * Search equipment types with specifications
     */
    public function searchBySpecifications(array $criteria): Collection
    {
        $query = EquipmentType::with(['category'])
            ->where('is_active', true);

        // Filter by operating weight range
        if (isset($criteria['operating_weight_min']) && isset($criteria['operating_weight_max'])) {
            $query->where(function ($q) use ($criteria) {
                $q->whereBetween('operating_weight_min', [$criteria['operating_weight_min'], $criteria['operating_weight_max']])
                  ->orWhereBetween('operating_weight_max', [$criteria['operating_weight_min'], $criteria['operating_weight_max']])
                  ->orWhere(function ($subQuery) use ($criteria) {
                      $subQuery->where('operating_weight_min', '<=', $criteria['operating_weight_min'])
                               ->where('operating_weight_max', '>=', $criteria['operating_weight_max']);
                  });
            });
        }

        // Filter by engine power range
        if (isset($criteria['engine_power_min']) && isset($criteria['engine_power_max'])) {
            $query->where(function ($q) use ($criteria) {
                $q->whereBetween('engine_power_min', [$criteria['engine_power_min'], $criteria['engine_power_max']])
                  ->orWhereBetween('engine_power_max', [$criteria['engine_power_min'], $criteria['engine_power_max']])
                  ->orWhere(function ($subQuery) use ($criteria) {
                      $subQuery->where('engine_power_min', '<=', $criteria['engine_power_min'])
                               ->where('engine_power_max', '>=', $criteria['engine_power_max']);
                  });
            });
        }

        // Filter by bucket capacity range
        if (isset($criteria['bucket_capacity_min']) && isset($criteria['bucket_capacity_max'])) {
            $query->where(function ($q) use ($criteria) {
                $q->whereBetween('bucket_capacity_min', [$criteria['bucket_capacity_min'], $criteria['bucket_capacity_max']])
                  ->orWhereBetween('bucket_capacity_max', [$criteria['bucket_capacity_min'], $criteria['bucket_capacity_max']])
                  ->orWhere(function ($subQuery) use ($criteria) {
                      $subQuery->where('bucket_capacity_min', '<=', $criteria['bucket_capacity_min'])
                               ->where('bucket_capacity_max', '>=', $criteria['bucket_capacity_max']);
                  });
            });
        }

        // Filter by category
        if (isset($criteria['category_id'])) {
            $query->where('category_id', $criteria['category_id']);
        }

        return $query->get();
    }

    /**
     * Get equipment type recommendations for specific use case
     */
    public function getRecommendations(array $requirements): array
    {
        $recommendations = collect();

        // Define scoring criteria
        $criteriaWeights = [
            'operating_weight' => 0.3,
            'engine_power' => 0.3,
            'bucket_capacity' => 0.2,
            'availability' => 0.2,
        ];

        $equipmentTypes = EquipmentType::with(['category'])
            ->where('is_active', true)
            ->get();

        foreach ($equipmentTypes as $type) {
            $score = $this->calculateRecommendationScore($type, $requirements, $criteriaWeights);
            
            if ($score > 0.5) { // Only recommend if score is above 50%
                $recommendations->push([
                    'equipment_type' => $type,
                    'score' => $score,
                    'match_reasons' => $this->getMatchReasons($type, $requirements),
                    'available_equipment' => $type->equipment()
                        ->where('status', 'active')
                        ->whereNull('assigned_to_user')
                        ->count(),
                ]);
            }
        }

        return $recommendations->sortByDesc('score')->values()->toArray();
    }

    /**
     * Apply filters to equipment types query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['active']) && $filters['active'] !== null) {
            $query->where('is_active', $filters['active']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%')
                  ->orWhereHas('category', function ($categoryQuery) use ($search) {
                      $categoryQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Specification filters
        if (isset($filters['operating_weight_min'])) {
            $query->where('operating_weight_max', '>=', $filters['operating_weight_min']);
        }

        if (isset($filters['operating_weight_max'])) {
            $query->where('operating_weight_min', '<=', $filters['operating_weight_max']);
        }

        if (isset($filters['engine_power_min'])) {
            $query->where('engine_power_max', '>=', $filters['engine_power_min']);
        }

        if (isset($filters['engine_power_max'])) {
            $query->where('engine_power_min', '<=', $filters['engine_power_max']);
        }
    }

    /**
     * Apply sorting to equipment types query
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $allowedSorts = [
            'name', 'code', 'created_at', 'equipment_count', 
            'operating_weight_min', 'engine_power_min'
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    /**
     * Validate equipment type data
     */
    private function validateEquipmentTypeData(array $data, ?EquipmentType $equipmentType = null): void
    {
        // Validate specification ranges
        if (isset($data['operating_weight_min']) && isset($data['operating_weight_max'])) {
            if ($data['operating_weight_max'] < $data['operating_weight_min']) {
                throw new \InvalidArgumentException('Maximum operating weight must be greater than minimum');
            }
        }

        if (isset($data['engine_power_min']) && isset($data['engine_power_max'])) {
            if ($data['engine_power_max'] < $data['engine_power_min']) {
                throw new \InvalidArgumentException('Maximum engine power must be greater than minimum');
            }
        }

        if (isset($data['bucket_capacity_min']) && isset($data['bucket_capacity_max'])) {
            if ($data['bucket_capacity_max'] < $data['bucket_capacity_min']) {
                throw new \InvalidArgumentException('Maximum bucket capacity must be greater than minimum');
            }
        }

        // Validate category exists and is active
        if (isset($data['category_id'])) {
            $category = EquipmentCategory::find($data['category_id']);
            if (!$category) {
                throw new \InvalidArgumentException('Equipment category not found');
            }
            if (!$category->is_active) {
                throw new \InvalidArgumentException('Cannot assign equipment type to inactive category');
            }
        }
    }

    /**
     * Normalize equipment type data
     */
    private function normalizeEquipmentTypeData(array $data): array
    {
        // Normalize code to uppercase
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        // Ensure is_active is boolean
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        return $data;
    }

    /**
     * Validate category change impact
     */
    private function validateCategoryChange(EquipmentType $equipmentType, int $newCategoryId): void
    {
        $equipmentCount = $equipmentType->equipment()->count();
        
        if ($equipmentCount > 0) {
            $newCategory = EquipmentCategory::findOrFail($newCategoryId);
            
            // Add business logic here if category changes affect equipment
            // For example, certain categories might not be compatible
        }
    }

    /**
     * Validate equipment type can be deactivated
     */
    private function validateEquipmentTypeDeactivation(EquipmentType $equipmentType): void
    {
        $activeEquipment = $equipmentType->equipment()
            ->whereIn('status', ['active', 'maintenance'])
            ->count();
        
        if ($activeEquipment > 0) {
            throw new \InvalidArgumentException(
                "Cannot deactivate equipment type. It has {$activeEquipment} active equipment(s)."
            );
        }
    }

    /**
     * Get specifications compliance for equipment type
     */
    private function getSpecificationsCompliance(EquipmentType $equipmentType): array
    {
        $equipment = $equipmentType->equipment;
        $totalCount = $equipment->count();

        if ($totalCount === 0) {
            return ['compliance_rate' => 100, 'out_of_spec_count' => 0];
        }

        $outOfSpecCount = 0;

        foreach ($equipment as $item) {
            // Check operating weight compliance
            if ($equipmentType->operating_weight_min && $item->operating_weight < $equipmentType->operating_weight_min) {
                $outOfSpecCount++;
                continue;
            }
            if ($equipmentType->operating_weight_max && $item->operating_weight > $equipmentType->operating_weight_max) {
                $outOfSpecCount++;
                continue;
            }

            // Check engine power compliance
            if ($equipmentType->engine_power_min && $item->engine_power < $equipmentType->engine_power_min) {
                $outOfSpecCount++;
                continue;
            }
            if ($equipmentType->engine_power_max && $item->engine_power > $equipmentType->engine_power_max) {
                $outOfSpecCount++;
                continue;
            }
        }

        return [
            'compliance_rate' => round(100 - ($outOfSpecCount / $totalCount) * 100, 1),
            'out_of_spec_count' => $outOfSpecCount,
            'total_count' => $totalCount,
        ];
    }

    /**
     * Get utilization statistics for equipment type
     */
    private function getUtilizationStats(EquipmentType $equipmentType): array
    {
        $equipment = $equipmentType->equipment;

        if ($equipment->isEmpty()) {
            return ['average_hours' => 0, 'utilization_rate' => 0];
        }

        $totalHours = $equipment->sum('total_operating_hours');
        $averageHours = $totalHours / $equipment->count();
        
        // Assume 2000 hours per year target utilization
        $targetHoursPerYear = 2000;
        $averageAge = $equipment->avg(function ($item) {
            return now()->year - $item->year_manufactured;
        });
        
        $expectedTotalHours = $averageAge * $targetHoursPerYear;
        $utilizationRate = $expectedTotalHours > 0 ? min(100, ($averageHours / $expectedTotalHours) * 100) : 0;

        return [
            'average_hours' => round($averageHours, 1),
            'utilization_rate' => round($utilizationRate, 1),
            'total_equipment' => $equipment->count(),
        ];
    }

    /**
     * Get maintenance statistics for equipment type
     */
    private function getMaintenanceStats(EquipmentType $equipmentType): array
    {
        $equipment = $equipmentType->equipment;

        $serviceDue = $equipment->filter(function ($item) {
            return $item->next_service_hours && $item->total_operating_hours >= $item->next_service_hours;
        })->count();

        $inMaintenance = $equipment->where('status', 'maintenance')->count();
        $inRepair = $equipment->where('status', 'repair')->count();

        return [
            'service_due_count' => $serviceDue,
            'in_maintenance_count' => $inMaintenance,
            'in_repair_count' => $inRepair,
            'maintenance_ratio' => $equipment->count() > 0 ? 
                round((($inMaintenance + $inRepair) / $equipment->count()) * 100, 1) : 0,
        ];
    }

    /**
     * Calculate recommendation score for equipment type
     */
    private function calculateRecommendationScore(EquipmentType $type, array $requirements, array $weights): float
    {
        $score = 0;

        // Score operating weight match
        if (isset($requirements['operating_weight'])) {
            $weightScore = $this->calculateRangeScore(
                $requirements['operating_weight'],
                $type->operating_weight_min,
                $type->operating_weight_max
            );
            $score += $weightScore * $weights['operating_weight'];
        }

        // Score engine power match  
        if (isset($requirements['engine_power'])) {
            $powerScore = $this->calculateRangeScore(
                $requirements['engine_power'],
                $type->engine_power_min,
                $type->engine_power_max
            );
            $score += $powerScore * $weights['engine_power'];
        }

        // Score bucket capacity match
        if (isset($requirements['bucket_capacity'])) {
            $capacityScore = $this->calculateRangeScore(
                $requirements['bucket_capacity'],
                $type->bucket_capacity_min,
                $type->bucket_capacity_max
            );
            $score += $capacityScore * $weights['bucket_capacity'];
        }

        // Score equipment availability
        $availableCount = $type->equipment()
            ->where('status', 'active')
            ->whereNull('assigned_to_user')
            ->count();
        $availabilityScore = min(1, $availableCount / 3); // Score based on having at least 3 available
        $score += $availabilityScore * $weights['availability'];

        return min(1, $score); // Cap at 1.0
    }

    /**
     * Calculate score for value within range
     */
    private function calculateRangeScore(float $value, ?float $min, ?float $max): float
    {
        if ($min === null && $max === null) {
            return 0.5; // Neutral score if no range defined
        }

        if ($min !== null && $max !== null) {
            if ($value >= $min && $value <= $max) {
                return 1.0; // Perfect match
            }
            
            // Calculate how far outside the range
            $distance = $value < $min ? ($min - $value) / $min : ($value - $max) / $max;
            return max(0, 1 - $distance); // Score decreases with distance
        }

        if ($min !== null) {
            return $value >= $min ? 1.0 : max(0, $value / $min);
        }

        if ($max !== null) {
            return $value <= $max ? 1.0 : max(0, $max / $value);
        }

        return 0.5;
    }

    /**
     * Get match reasons for recommendation
     */
    private function getMatchReasons(EquipmentType $type, array $requirements): array
    {
        $reasons = [];

        if (isset($requirements['operating_weight'])) {
            if ($type->operating_weight_min <= $requirements['operating_weight'] && 
                $type->operating_weight_max >= $requirements['operating_weight']) {
                $reasons[] = 'Operating weight matches requirements';
            }
        }

        if (isset($requirements['engine_power'])) {
            if ($type->engine_power_min <= $requirements['engine_power'] && 
                $type->engine_power_max >= $requirements['engine_power']) {
                $reasons[] = 'Engine power matches requirements';
            }
        }

        $availableCount = $type->equipment()
            ->where('status', 'active')
            ->whereNull('assigned_to_user')
            ->count();

        if ($availableCount > 0) {
            $reasons[] = "{$availableCount} equipment available";
        }

        return $reasons;
    }
}