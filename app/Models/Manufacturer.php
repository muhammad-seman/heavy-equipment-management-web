<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{

    protected $fillable = [
        'name',
        'country',
        'website',
        'contact_email',
        'contact_phone',
        'description',
        'logo_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function equipmentTypes(): HasMany
    {
        return $this->hasMany(EquipmentType::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
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
