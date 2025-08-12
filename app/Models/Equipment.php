<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'equipment_category_id',
        'equipment_type_id',
        'manufacturer_id',
        'asset_number',
        'serial_number',
        'model',
        'year_manufactured',
        'purchase_date',
        'purchase_price',
        'current_value',
        'depreciation_rate',
        'operating_weight',
        'engine_power',
        'fuel_capacity',
        'hydraulic_capacity',
        'bucket_capacity',
        'max_dig_depth',
        'max_reach',
        'travel_speed_max',
        'ownership_type',
        'lease_start_date',
        'lease_end_date',
        'lease_monthly_cost',
        'warranty_start_date',
        'warranty_end_date',
        'status',
        'condition',
        'current_site',
        'assigned_user_id',
        'last_inspection_date',
        'next_inspection_due',
        'last_maintenance_date',
        'next_maintenance_due',
        'current_hours',
        'current_kilometers',
        'latitude',
        'longitude',
        'address',
        'last_location_update',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'current_value' => 'decimal:2',
        'depreciation_rate' => 'decimal:2',
        'operating_weight' => 'decimal:2',
        'engine_power' => 'decimal:2',
        'fuel_capacity' => 'decimal:2',
        'hydraulic_capacity' => 'decimal:2',
        'bucket_capacity' => 'decimal:3',
        'max_dig_depth' => 'decimal:2',
        'max_reach' => 'decimal:2',
        'travel_speed_max' => 'decimal:2',
        'lease_start_date' => 'date',
        'lease_end_date' => 'date',
        'lease_monthly_cost' => 'decimal:2',
        'warranty_start_date' => 'date',
        'warranty_end_date' => 'date',
        'last_inspection_date' => 'date',
        'next_inspection_due' => 'date',
        'last_maintenance_date' => 'date',
        'next_maintenance_due' => 'date',
        'current_hours' => 'decimal:2',
        'current_kilometers' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'last_location_update' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class);
    }

    public function statusLog(): HasMany
    {
        return $this->hasMany(EquipmentStatusLog::class);
    }

    public function operatingSessions(): HasMany
    {
        return $this->hasMany(OperatingSession::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('equipment_category_id', $categoryId);
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('equipment_type_id', $typeId);
    }

    public function scopeByManufacturer($query, $manufacturerId)
    {
        return $query->where('manufacturer_id', $manufacturerId);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_user_id', $userId);
    }

    public function scopeInLocation($query, $lat, $lng, $radius = 10)
    {
        return $query->whereRaw(
            "ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?",
            [$lng, $lat, $radius * 1000]
        );
    }

    public function getFullNameAttribute(): string
    {
        return $this->asset_number . ' - ' . $this->manufacturer->name . ' ' . $this->model;
    }

    public function getAgeAttribute(): int
    {
        return now()->year - $this->year_manufactured;
    }

    public function getWarrantyStatusAttribute(): string
    {
        if (!$this->warranty_end_date) {
            return 'no_warranty';
        }

        return $this->warranty_end_date->isFuture() ? 'active' : 'expired';
    }

    public function getLeaseStatusAttribute(): string
    {
        if ($this->ownership_type !== 'leased') {
            return 'not_applicable';
        }

        if (!$this->lease_end_date) {
            return 'ongoing';
        }

        return $this->lease_end_date->isFuture() ? 'active' : 'expired';
    }

    public function getInspectionStatusAttribute(): string
    {
        if (!$this->next_inspection_due) {
            return 'no_schedule';
        }

        $daysUntilDue = now()->diffInDays($this->next_inspection_due, false);

        if ($daysUntilDue < 0) {
            return 'overdue';
        } elseif ($daysUntilDue <= 7) {
            return 'due_soon';
        } else {
            return 'current';
        }
    }

    public function getMaintenanceStatusAttribute(): string
    {
        if (!$this->next_maintenance_due) {
            return 'no_schedule';
        }

        $daysUntilDue = now()->diffInDays($this->next_maintenance_due, false);

        if ($daysUntilDue < 0) {
            return 'overdue';
        } elseif ($daysUntilDue <= 7) {
            return 'due_soon';
        } else {
            return 'current';
        }
    }

    public function isAvailable(): bool
    {
        return $this->status === 'active' && !$this->assigned_user_id;
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_status === 'active';
    }

    public function needsInspection(): bool
    {
        return in_array($this->inspection_status, ['overdue', 'due_soon']);
    }

    public function needsMaintenance(): bool
    {
        return in_array($this->maintenance_status, ['overdue', 'due_soon']);
    }
}
