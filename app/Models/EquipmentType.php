<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentType extends Model
{

    protected $fillable = [
        'category_id',
        'name',
        'code',
        'description',
        'specifications',
        'operating_weight_min',
        'operating_weight_max',
        'engine_power_min',
        'engine_power_max',
        'bucket_capacity_min',
        'bucket_capacity_max',
        'is_active',
    ];

    protected $casts = [
        'specifications' => 'array',
        'operating_weight_min' => 'decimal:2',
        'operating_weight_max' => 'decimal:2',
        'engine_power_min' => 'decimal:2',
        'engine_power_max' => 'decimal:2',
        'bucket_capacity_min' => 'decimal:3',
        'bucket_capacity_max' => 'decimal:3',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function inspectionTemplates(): HasMany
    {
        return $this->hasMany(InspectionTemplate::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function getEquipmentCountAttribute(): int
    {
        return $this->equipment()->count();
    }

    public function getActiveEquipmentCountAttribute(): int
    {
        return $this->equipment()->where('status', 'active')->count();
    }

    public function getFullNameAttribute(): string
    {
        return $this->category->name . ' - ' . $this->name;
    }
}
