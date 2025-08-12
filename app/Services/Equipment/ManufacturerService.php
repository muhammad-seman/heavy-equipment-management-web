<?php

namespace App\Services\Equipment;

use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ManufacturerService
{
    /**
     * Get filtered and paginated manufacturers
     */
    public function getFilteredManufacturers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Manufacturer::withCount(['equipmentTypes', 'equipment']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    /**
     * Create new manufacturer with validation
     */
    public function createManufacturer(array $data, User $user): Manufacturer
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateManufacturerData($data);

            // Normalize data
            $data = $this->normalizeManufacturerData($data);

            // Create manufacturer
            $manufacturer = Manufacturer::create($data);

            DB::commit();

            return $manufacturer->loadCount(['equipmentTypes', 'equipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update manufacturer with business logic
     */
    public function updateManufacturer(Manufacturer $manufacturer, array $data, User $user): Manufacturer
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateManufacturerData($data, $manufacturer);

            // Normalize data
            $data = $this->normalizeManufacturerData($data);

            // Check if deactivating manufacturer with active equipment
            if (isset($data['is_active']) && !$data['is_active'] && $manufacturer->is_active) {
                $this->validateManufacturerDeactivation($manufacturer);
            }

            // Update manufacturer
            $manufacturer->update($data);

            DB::commit();

            return $manufacturer->fresh()->loadCount(['equipmentTypes', 'equipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete manufacturer with safety checks
     */
    public function deleteManufacturer(Manufacturer $manufacturer, User $user): bool
    {
        DB::beginTransaction();

        try {
            // Check if manufacturer has any equipment
            $equipmentCount = $manufacturer->equipment()->count();
            if ($equipmentCount > 0) {
                throw new \InvalidArgumentException(
                    "Cannot delete manufacturer. It has {$equipmentCount} equipment(s) associated with it."
                );
            }

            // Check if manufacturer has any equipment types
            $equipmentTypesCount = $manufacturer->equipmentTypes()->count();
            if ($equipmentTypesCount > 0) {
                throw new \InvalidArgumentException(
                    "Cannot delete manufacturer. It has {$equipmentTypesCount} equipment type(s) associated with it."
                );
            }

            $manufacturer->delete();

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Toggle manufacturer active status
     */
    public function toggleStatus(Manufacturer $manufacturer, User $user): Manufacturer
    {
        DB::beginTransaction();

        try {
            $newStatus = !$manufacturer->is_active;

            // If deactivating, validate
            if (!$newStatus) {
                $this->validateManufacturerDeactivation($manufacturer);
            }

            $manufacturer->update(['is_active' => $newStatus]);

            DB::commit();

            return $manufacturer->fresh()->loadCount(['equipmentTypes', 'equipment']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get manufacturer analytics
     */
    public function getManufacturerAnalytics(Manufacturer $manufacturer): array
    {
        return [
            'total_equipment' => $manufacturer->equipment()->count(),
            'active_equipment' => $manufacturer->equipment()->where('status', 'active')->count(),
            'equipment_by_status' => $manufacturer->equipment()
                ->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'equipment_by_type' => $manufacturer->equipment()
                ->with('type:id,name')
                ->get()
                ->groupBy('type.name')
                ->map->count()
                ->toArray(),
            'average_equipment_age' => $this->getAverageEquipmentAge($manufacturer),
            'total_value' => $manufacturer->equipment()
                ->sum('current_book_value'),
            'maintenance_stats' => $this->getMaintenanceStats($manufacturer),
        ];
    }

    /**
     * Get countries list from manufacturers
     */
    public function getCountries(): array
    {
        return Manufacturer::select('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Search manufacturers with advanced options
     */
    public function searchManufacturers(string $query, array $options = []): array
    {
        $manufacturers = Manufacturer::where(function ($q) use ($query) {
            $q->where('name', 'like', '%' . $query . '%')
              ->orWhere('code', 'like', '%' . $query . '%')
              ->orWhere('country', 'like', '%' . $query . '%');
        });

        if (isset($options['active_only']) && $options['active_only']) {
            $manufacturers->where('is_active', true);
        }

        if (isset($options['with_equipment']) && $options['with_equipment']) {
            $manufacturers->has('equipment');
        }

        $limit = $options['limit'] ?? 10;

        return $manufacturers->limit($limit)->get(['id', 'name', 'code', 'country'])->toArray();
    }

    /**
     * Apply filters to manufacturers query
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['active']) && $filters['active'] !== null) {
            $query->where('is_active', $filters['active']);
        }

        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%')
                  ->orWhere('country', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['has_equipment'])) {
            if ($filters['has_equipment']) {
                $query->has('equipment');
            } else {
                $query->doesntHave('equipment');
            }
        }
    }

    /**
     * Apply sorting to manufacturers query
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortOrder = $filters['sort_order'] ?? 'asc';

        $allowedSorts = [
            'name', 'code', 'country', 'created_at', 'equipment_types_count', 'equipment_count'
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    /**
     * Validate manufacturer data
     */
    private function validateManufacturerData(array $data, ?Manufacturer $manufacturer = null): void
    {
        // Validate manufacturer code format (3-4 uppercase letters)
        if (isset($data['code']) && !preg_match('/^[A-Z]{2,4}$/', $data['code'])) {
            throw new \InvalidArgumentException('Manufacturer code must be 2-4 uppercase letters');
        }

        // Validate website URL format
        if (isset($data['website']) && $data['website'] && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Website must be a valid URL');
        }

        // Validate contact email format
        if (isset($data['contact_email']) && $data['contact_email'] && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Contact email must be a valid email address');
        }
    }

    /**
     * Normalize manufacturer data
     */
    private function normalizeManufacturerData(array $data): array
    {
        // Normalize code to uppercase
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        // Ensure is_active is boolean
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        // Clean and validate URLs
        if (isset($data['website']) && $data['website']) {
            $data['website'] = $this->normalizeUrl($data['website']);
        }

        if (isset($data['logo_url']) && $data['logo_url']) {
            $data['logo_url'] = $this->normalizeUrl($data['logo_url']);
        }

        return $data;
    }

    /**
     * Validate manufacturer can be deactivated
     */
    private function validateManufacturerDeactivation(Manufacturer $manufacturer): void
    {
        $activeEquipment = $manufacturer->equipment()->whereIn('status', ['active', 'maintenance'])->count();
        
        if ($activeEquipment > 0) {
            throw new \InvalidArgumentException(
                "Cannot deactivate manufacturer. It has {$activeEquipment} active equipment(s)."
            );
        }
    }

    /**
     * Get average equipment age for manufacturer
     */
    private function getAverageEquipmentAge(Manufacturer $manufacturer): float
    {
        $equipmentAges = $manufacturer->equipment()
            ->whereNotNull('year_manufactured')
            ->pluck('year_manufactured')
            ->map(function ($year) {
                return now()->year - $year;
            })
            ->filter(function ($age) {
                return $age >= 0;
            });

        return $equipmentAges->count() > 0 ? $equipmentAges->avg() : 0;
    }

    /**
     * Get maintenance statistics for manufacturer equipment
     */
    private function getMaintenanceStats(Manufacturer $manufacturer): array
    {
        $equipment = $manufacturer->equipment;

        $serviceDue = $equipment->filter(function ($item) {
            return $item->next_service_hours && $item->total_operating_hours >= $item->next_service_hours;
        })->count();

        $serviceOverdue = $equipment->filter(function ($item) {
            return $item->next_service_hours && $item->total_operating_hours > ($item->next_service_hours + 50);
        })->count();

        return [
            'service_due_count' => $serviceDue,
            'service_overdue_count' => $serviceOverdue,
            'average_operating_hours' => $equipment->avg('total_operating_hours'),
        ];
    }

    /**
     * Normalize URL format
     */
    private function normalizeUrl(string $url): string
    {
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }
}