<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatingSession extends Model
{
    protected $fillable = [
        'equipment_id',
        'operator_id',
        'session_type',
        'start_time',
        'end_time',
        'start_hours',
        'end_hours',
        'start_kilometers',
        'end_kilometers',
        'fuel_used',
        'start_location_lat',
        'start_location_lng',
        'end_location_lat',
        'end_location_lng',
        'work_site',
        'work_description',
        'performance_rating',
        'issues_reported',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'start_hours' => 'decimal:2',
        'end_hours' => 'decimal:2',
        'start_kilometers' => 'decimal:2',
        'end_kilometers' => 'decimal:2',
        'fuel_used' => 'decimal:2',
        'start_location_lat' => 'decimal:8',
        'start_location_lng' => 'decimal:8',
        'end_location_lat' => 'decimal:8',
        'end_location_lng' => 'decimal:8',
        'performance_rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function scopeForEquipment($query, $equipmentId)
    {
        return $query->where('equipment_id', $equipmentId);
    }

    public function scopeForOperator($query, $operatorId)
    {
        return $query->where('operator_id', $operatorId);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('end_time');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('end_time');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_time', [$startDate, $endDate]);
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->start_time || !$this->end_time) {
            return null;
        }

        return $this->start_time->diffInMinutes($this->end_time);
    }

    public function getHoursWorkedAttribute(): ?float
    {
        if (!$this->start_hours || !$this->end_hours) {
            return null;
        }

        return $this->end_hours - $this->start_hours;
    }

    public function getKilometersAttribute(): ?float
    {
        if (!$this->start_kilometers || !$this->end_kilometers) {
            return null;
        }

        return $this->end_kilometers - $this->start_kilometers;
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->end_time);
    }

    public function getFuelEfficiencyAttribute(): ?float
    {
        if (!$this->fuel_used || !$this->hours_worked || $this->hours_worked <= 0) {
            return null;
        }

        return $this->fuel_used / $this->hours_worked;
    }
}
