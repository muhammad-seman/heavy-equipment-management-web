<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePart extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'maintenance_record_id',
        'part_number',
        'part_name',
        'part_description',
        'manufacturer',
        'category',
        'quantity_used',
        'unit_of_measure',
        'unit_cost',
        'total_cost',
        'supplier',
        'purchase_order_number',
        'warranty_period_months',
        'warranty_expires_at',
        'installation_date',
        'part_condition',
        'part_source',
        'old_part_condition',
        'old_part_disposed',
        'installation_notes',
        'is_critical_part',
        'lead_time_days',
        'minimum_stock_level',
        'current_stock_level',
        'reorder_point',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity_used' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'warranty_period_months' => 'integer',
        'warranty_expires_at' => 'datetime',
        'installation_date' => 'datetime',
        'is_critical_part' => 'boolean',
        'old_part_disposed' => 'boolean',
        'lead_time_days' => 'integer',
        'minimum_stock_level' => 'integer',
        'current_stock_level' => 'integer',
        'reorder_point' => 'integer',
    ];

    // Part categories
    public const CATEGORY_ENGINE = 'engine';
    public const CATEGORY_HYDRAULIC = 'hydraulic';
    public const CATEGORY_ELECTRICAL = 'electrical';
    public const CATEGORY_TRANSMISSION = 'transmission';
    public const CATEGORY_COOLING = 'cooling';
    public const CATEGORY_FUEL = 'fuel';
    public const CATEGORY_BRAKE = 'brake';
    public const CATEGORY_TRACK = 'track';
    public const CATEGORY_ATTACHMENT = 'attachment';
    public const CATEGORY_CABIN = 'cabin';
    public const CATEGORY_FILTER = 'filter';
    public const CATEGORY_BEARING = 'bearing';
    public const CATEGORY_SEAL = 'seal';
    public const CATEGORY_FASTENER = 'fastener';
    public const CATEGORY_LUBRICANT = 'lubricant';
    public const CATEGORY_CONSUMABLE = 'consumable';

    // Part conditions
    public const CONDITION_NEW = 'new';
    public const CONDITION_REFURBISHED = 'refurbished';
    public const CONDITION_USED = 'used';
    public const CONDITION_CORE_EXCHANGE = 'core_exchange';

    // Part sources
    public const SOURCE_OEM = 'oem';
    public const SOURCE_AFTERMARKET = 'aftermarket';
    public const SOURCE_INTERNAL_STOCK = 'internal_stock';
    public const SOURCE_EMERGENCY_PURCHASE = 'emergency_purchase';
    public const SOURCE_WARRANTY_REPLACEMENT = 'warranty_replacement';

    // Old part conditions
    public const OLD_CONDITION_SERVICEABLE = 'serviceable';
    public const OLD_CONDITION_REPAIRABLE = 'repairable';
    public const OLD_CONDITION_SCRAP = 'scrap';
    public const OLD_CONDITION_CORE_RETURN = 'core_return';
    public const OLD_CONDITION_DISPOSED = 'disposed';

    /**
     * Get the maintenance record this part belongs to
     */
    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    /**
     * Get the user who created this record
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for filtering by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for critical parts
     */
    public function scopeCritical($query)
    {
        return $query->where('is_critical_part', true);
    }

    /**
     * Scope for parts under warranty
     */
    public function scopeUnderWarranty($query)
    {
        return $query->where('warranty_expires_at', '>', now());
    }

    /**
     * Scope for expired warranty parts
     */
    public function scopeWarrantyExpired($query)
    {
        return $query->where('warranty_expires_at', '<=', now());
    }

    /**
     * Scope for low stock parts
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('current_stock_level <= reorder_point');
    }

    /**
     * Check if part is under warranty
     */
    public function getIsUnderWarrantyAttribute(): bool
    {
        return $this->warranty_expires_at && $this->warranty_expires_at > now();
    }

    /**
     * Check if warranty is expiring soon (within 30 days)
     */
    public function getIsWarrantyExpiringSoonAttribute(): bool
    {
        return $this->warranty_expires_at && 
               $this->warranty_expires_at > now() && 
               $this->warranty_expires_at <= now()->addDays(30);
    }

    /**
     * Check if part needs reordering
     */
    public function getNeedsReorderAttribute(): bool
    {
        return $this->current_stock_level <= $this->reorder_point;
    }

    /**
     * Get warranty remaining days
     */
    public function getWarrantyRemainingDaysAttribute(): ?int
    {
        if (!$this->warranty_expires_at || $this->warranty_expires_at <= now()) {
            return 0;
        }

        return now()->diffInDays($this->warranty_expires_at);
    }

    /**
     * Get part cost per unit
     */
    public function getCostPerUnitAttribute(): float
    {
        return $this->quantity_used > 0 ? $this->total_cost / $this->quantity_used : 0;
    }

    /**
     * Get formatted category
     */
    public function getFormattedCategoryAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->category));
    }

    /**
     * Get formatted condition
     */
    public function getFormattedConditionAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->part_condition));
    }

    /**
     * Get formatted source
     */
    public function getFormattedSourceAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->part_source));
    }

    /**
     * Get all part categories
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_ENGINE => 'Engine Components',
            self::CATEGORY_HYDRAULIC => 'Hydraulic System',
            self::CATEGORY_ELECTRICAL => 'Electrical Components',
            self::CATEGORY_TRANSMISSION => 'Transmission',
            self::CATEGORY_COOLING => 'Cooling System',
            self::CATEGORY_FUEL => 'Fuel System',
            self::CATEGORY_BRAKE => 'Brake System',
            self::CATEGORY_TRACK => 'Track System',
            self::CATEGORY_ATTACHMENT => 'Attachments',
            self::CATEGORY_CABIN => 'Cabin Components',
            self::CATEGORY_FILTER => 'Filters',
            self::CATEGORY_BEARING => 'Bearings',
            self::CATEGORY_SEAL => 'Seals & Gaskets',
            self::CATEGORY_FASTENER => 'Fasteners',
            self::CATEGORY_LUBRICANT => 'Lubricants & Fluids',
            self::CATEGORY_CONSUMABLE => 'Consumables',
        ];
    }

    /**
     * Get all part conditions
     */
    public static function getConditions(): array
    {
        return [
            self::CONDITION_NEW => 'New',
            self::CONDITION_REFURBISHED => 'Refurbished',
            self::CONDITION_USED => 'Used',
            self::CONDITION_CORE_EXCHANGE => 'Core Exchange',
        ];
    }

    /**
     * Get all part sources
     */
    public static function getSources(): array
    {
        return [
            self::SOURCE_OEM => 'OEM (Original Equipment Manufacturer)',
            self::SOURCE_AFTERMARKET => 'Aftermarket',
            self::SOURCE_INTERNAL_STOCK => 'Internal Stock',
            self::SOURCE_EMERGENCY_PURCHASE => 'Emergency Purchase',
            self::SOURCE_WARRANTY_REPLACEMENT => 'Warranty Replacement',
        ];
    }

    /**
     * Get all old part conditions
     */
    public static function getOldPartConditions(): array
    {
        return [
            self::OLD_CONDITION_SERVICEABLE => 'Serviceable',
            self::OLD_CONDITION_REPAIRABLE => 'Repairable',
            self::OLD_CONDITION_SCRAP => 'Scrap',
            self::OLD_CONDITION_CORE_RETURN => 'Core Return',
            self::OLD_CONDITION_DISPOSED => 'Disposed',
        ];
    }
}