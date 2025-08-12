<?php

namespace App\Http\Resources\Api\V1\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\V1\Users\UserResource;

class MaintenancePartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_record_id' => $this->maintenance_record_id,
            
            // Part identification
            'part_number' => $this->part_number,
            'part_name' => $this->part_name,
            'part_description' => $this->part_description,
            'manufacturer' => $this->manufacturer,
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            
            // Quantity and measurements
            'quantity_used' => $this->quantity_used,
            'unit_of_measure' => $this->unit_of_measure,
            
            // Cost information
            'unit_cost' => number_format($this->unit_cost, 2),
            'total_cost' => number_format($this->total_cost, 2),
            'cost_per_unit_formatted' => $this->formatCurrency($this->unit_cost),
            'total_cost_formatted' => $this->formatCurrency($this->total_cost),
            
            // Procurement information
            'supplier' => $this->supplier,
            'purchase_order_number' => $this->purchase_order_number,
            
            // Warranty information
            'warranty_period_months' => $this->warranty_period_months,
            'warranty_expires_at' => $this->warranty_expires_at?->toISOString(),
            'warranty_status' => $this->getWarrantyStatus(),
            'warranty_days_remaining' => $this->getWarrantyDaysRemaining(),
            'installation_date' => $this->installation_date?->toISOString(),
            
            // Part specifications
            'part_condition' => $this->part_condition,
            'part_condition_label' => $this->getPartConditionLabel(),
            'part_source' => $this->part_source,
            'part_source_label' => $this->getPartSourceLabel(),
            
            // Old part management
            'old_part_condition' => $this->old_part_condition,
            'old_part_condition_label' => $this->when($this->old_part_condition, $this->getOldPartConditionLabel()),
            'old_part_disposed' => $this->old_part_disposed,
            
            // Installation information
            'installation_notes' => $this->installation_notes,
            'is_critical_part' => $this->is_critical_part,
            
            // Inventory management
            'lead_time_days' => $this->lead_time_days,
            'minimum_stock_level' => $this->minimum_stock_level,
            'current_stock_level' => $this->current_stock_level,
            'reorder_point' => $this->reorder_point,
            'stock_status' => $this->getStockStatus(),
            
            // Status indicators
            'is_warranty_active' => $this->getIsWarrantyActive(),
            'is_critical' => $this->is_critical_part,
            'is_oem_part' => $this->part_source === 'oem',
            'is_new_part' => $this->part_condition === 'new',
            'needs_reorder' => $this->getNeedsReorder(),
            'is_disposed' => $this->old_part_disposed,
            
            // Calculated fields
            'age_in_service_days' => $this->when(
                $this->installation_date,
                now()->diffInDays($this->installation_date)
            ),
            'warranty_coverage_percentage' => $this->getWarrantyCoveragePercentage(),
            'cost_per_hour' => $this->when(
                $this->relationLoaded('maintenanceRecord') && 
                $this->maintenanceRecord->actual_duration_minutes,
                function () {
                    $hours = $this->maintenanceRecord->actual_duration_minutes / 60;
                    return $hours > 0 ? number_format($this->total_cost / $hours, 2) : null;
                }
            ),
            
            // Audit information
            'created_by' => new UserResource($this->whenLoaded('createdBy')),
            'updated_by' => new UserResource($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at?->toISOString()),
            
            // Relationships
            'maintenance_record' => $this->when(
                $this->relationLoaded('maintenanceRecord') && $request->query('include_maintenance'),
                new MaintenanceResource($this->maintenanceRecord)
            ),
            
            // Additional metadata for detailed views
            'metadata' => $this->when(
                $request->query('include_metadata'),
                function () {
                    return [
                        'category_color' => $this->getCategoryColor(),
                        'condition_color' => $this->getConditionColor(),
                        'source_icon' => $this->getSourceIcon(),
                        'priority_score' => $this->calculatePriorityScore(),
                        'replacement_urgency' => $this->getReplacementUrgency(),
                    ];
                }
            ),
        ];
    }

    /**
     * Format currency value
     */
    private function formatCurrency(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }

    /**
     * Get warranty status
     */
    private function getWarrantyStatus(): string
    {
        if (!$this->warranty_expires_at) {
            return 'no_warranty';
        }

        if ($this->warranty_expires_at->isPast()) {
            return 'expired';
        }

        $daysRemaining = now()->diffInDays($this->warranty_expires_at);
        
        if ($daysRemaining <= 30) {
            return 'expiring_soon';
        }

        return 'active';
    }

    /**
     * Get warranty days remaining
     */
    private function getWarrantyDaysRemaining(): ?int
    {
        if (!$this->warranty_expires_at || $this->warranty_expires_at->isPast()) {
            return null;
        }

        return now()->diffInDays($this->warranty_expires_at);
    }

    /**
     * Check if warranty is active
     */
    private function getIsWarrantyActive(): bool
    {
        return $this->warranty_expires_at && $this->warranty_expires_at->isFuture();
    }

    /**
     * Get stock status
     */
    private function getStockStatus(): string
    {
        if ($this->current_stock_level === null) {
            return 'unknown';
        }

        if ($this->current_stock_level <= 0) {
            return 'out_of_stock';
        }

        if ($this->reorder_point && $this->current_stock_level <= $this->reorder_point) {
            return 'low_stock';
        }

        if ($this->minimum_stock_level && $this->current_stock_level <= $this->minimum_stock_level) {
            return 'minimum_stock';
        }

        return 'in_stock';
    }

    /**
     * Check if part needs reordering
     */
    private function getNeedsReorder(): bool
    {
        return $this->reorder_point && 
               $this->current_stock_level !== null && 
               $this->current_stock_level <= $this->reorder_point;
    }

    /**
     * Get warranty coverage percentage
     */
    private function getWarrantyCoveragePercentage(): ?float
    {
        if (!$this->warranty_expires_at || !$this->installation_date) {
            return null;
        }

        $totalWarrantyDays = $this->installation_date->diffInDays($this->warranty_expires_at);
        $daysUsed = $this->installation_date->diffInDays(now());
        
        if ($totalWarrantyDays <= 0) {
            return 0;
        }

        $percentage = max(0, (($totalWarrantyDays - $daysUsed) / $totalWarrantyDays) * 100);
        return round($percentage, 2);
    }

    /**
     * Get category label
     */
    private function getCategoryLabel(): string
    {
        $labels = [
            'engine' => 'Engine',
            'hydraulic' => 'Hydraulic',
            'electrical' => 'Electrical',
            'transmission' => 'Transmission',
            'cooling' => 'Cooling',
            'fuel' => 'Fuel',
            'brake' => 'Brake',
            'track' => 'Track',
            'attachment' => 'Attachment',
            'cabin' => 'Cabin',
            'filter' => 'Filter',
            'bearing' => 'Bearing',
            'seal' => 'Seal',
            'fastener' => 'Fastener',
            'lubricant' => 'Lubricant',
            'consumable' => 'Consumable',
        ];

        return $labels[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Get part condition label
     */
    private function getPartConditionLabel(): string
    {
        $labels = [
            'new' => 'New',
            'refurbished' => 'Refurbished',
            'used' => 'Used',
            'core_exchange' => 'Core Exchange',
        ];

        return $labels[$this->part_condition] ?? ucfirst($this->part_condition);
    }

    /**
     * Get part source label
     */
    private function getPartSourceLabel(): string
    {
        $labels = [
            'oem' => 'OEM',
            'aftermarket' => 'Aftermarket',
            'internal_stock' => 'Internal Stock',
            'emergency_purchase' => 'Emergency Purchase',
            'warranty_replacement' => 'Warranty Replacement',
        ];

        return $labels[$this->part_source] ?? ucfirst(str_replace('_', ' ', $this->part_source));
    }

    /**
     * Get old part condition label
     */
    private function getOldPartConditionLabel(): string
    {
        $labels = [
            'serviceable' => 'Serviceable',
            'repairable' => 'Repairable',
            'scrap' => 'Scrap',
            'core_return' => 'Core Return',
            'disposed' => 'Disposed',
        ];

        return $labels[$this->old_part_condition] ?? ucfirst(str_replace('_', ' ', $this->old_part_condition));
    }

    /**
     * Get category color for UI
     */
    private function getCategoryColor(): string
    {
        $colors = [
            'engine' => 'red',
            'hydraulic' => 'blue',
            'electrical' => 'yellow',
            'transmission' => 'purple',
            'cooling' => 'teal',
            'fuel' => 'orange',
            'brake' => 'red',
            'track' => 'gray',
            'attachment' => 'green',
            'cabin' => 'indigo',
            'filter' => 'cyan',
            'bearing' => 'pink',
            'seal' => 'lime',
            'fastener' => 'amber',
            'lubricant' => 'violet',
            'consumable' => 'emerald',
        ];

        return $colors[$this->category] ?? 'gray';
    }

    /**
     * Get condition color for UI
     */
    private function getConditionColor(): string
    {
        $colors = [
            'new' => 'green',
            'refurbished' => 'yellow',
            'used' => 'orange',
            'core_exchange' => 'blue',
        ];

        return $colors[$this->part_condition] ?? 'gray';
    }

    /**
     * Get source icon for UI
     */
    private function getSourceIcon(): string
    {
        $icons = [
            'oem' => 'shield-check',
            'aftermarket' => 'shopping-cart',
            'internal_stock' => 'warehouse',
            'emergency_purchase' => 'alert-triangle',
            'warranty_replacement' => 'refresh-cw',
        ];

        return $icons[$this->part_source] ?? 'package';
    }

    /**
     * Calculate priority score for inventory management
     */
    private function calculatePriorityScore(): int
    {
        $score = 0;

        // Critical parts get high priority
        if ($this->is_critical_part) {
            $score += 50;
        }

        // Low stock increases priority
        if ($this->getNeedsReorder()) {
            $score += 30;
        }

        // High value parts get higher priority
        if ($this->total_cost > 1000) {
            $score += 20;
        } elseif ($this->total_cost > 500) {
            $score += 10;
        }

        // Long lead times increase priority
        if ($this->lead_time_days > 30) {
            $score += 15;
        } elseif ($this->lead_time_days > 14) {
            $score += 10;
        }

        // OEM parts get slightly higher priority
        if ($this->part_source === 'oem') {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Get replacement urgency level
     */
    private function getReplacementUrgency(): string
    {
        if ($this->is_critical_part && $this->getNeedsReorder()) {
            return 'urgent';
        }

        if ($this->getNeedsReorder()) {
            return 'high';
        }

        if ($this->current_stock_level && $this->minimum_stock_level && 
            $this->current_stock_level <= $this->minimum_stock_level * 1.5) {
            return 'medium';
        }

        return 'low';
    }
}