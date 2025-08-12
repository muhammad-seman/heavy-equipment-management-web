<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class EquipmentCategory extends Model
{

    protected $fillable = [
        'name',
        'code',
        'description',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function equipmentTypes(): HasMany
    {
        return $this->hasMany(EquipmentType::class, 'category_id');
    }

    public function equipment(): HasManyThrough
    {
        return $this->hasManyThrough(Equipment::class, EquipmentType::class, 'category_id', 'equipment_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getEquipmentCountAttribute(): int
    {
        return $this->equipment()->count();
    }

    public function getActiveEquipmentCountAttribute(): int
    {
        return $this->equipment()->where('status', 'active')->count();
    }
}
