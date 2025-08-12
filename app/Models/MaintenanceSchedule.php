<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class MaintenanceSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_id',
        'equipment_type_id',
        'title',
        'description',
        'maintenance_type',
        'priority_level',
        'schedule_type',
        'interval_hours',
        'interval_days',
        'interval_kilometers',
        'interval_cycles',
        'tolerance_hours',
        'tolerance_days',
        'estimated_duration_minutes',
        'estimated_cost',
        'required_skills',
        'required_tools',
        'required_parts',
        'safety_requirements',
        'work_instructions',
        'last_performed_at',
        'last_performed_hours',
        'last_performed_kilometers',
        'next_due_date',
        'next_due_hours',
        'next_due_kilometers',
        'is_active',
        'auto_generate',
        'advance_notice_days',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'last_performed_at' => 'datetime',
        'next_due_date' => 'datetime',
        'interval_hours' => 'decimal:2',
        'interval_days' => 'integer',
        'interval_kilometers' => 'decimal:2',
        'interval_cycles' => 'integer',
        'tolerance_hours' => 'decimal:2',
        'tolerance_days' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'estimated_cost' => 'decimal:2',
        'last_performed_hours' => 'decimal:2',
        'last_performed_kilometers' => 'decimal:2',
        'next_due_hours' => 'decimal:2',
        'next_due_kilometers' => 'decimal:2',
        'is_active' => 'boolean',
        'auto_generate' => 'boolean',
        'advance_notice_days' => 'integer',
        'required_skills' => 'json',
        'required_tools' => 'json',
        'required_parts' => 'json',
        'safety_requirements' => 'json',
        'work_instructions' => 'json',
    ];

    // Schedule types
    public const SCHEDULE_TIME_BASED = 'time_based';
    public const SCHEDULE_HOUR_BASED = 'hour_based';
    public const SCHEDULE_MILEAGE_BASED = 'mileage_based';
    public const SCHEDULE_CYCLE_BASED = 'cycle_based';
    public const SCHEDULE_CONDITION_BASED = 'condition_based';
    public const SCHEDULE_CALENDAR_BASED = 'calendar_based';

    /**
     * Get the equipment this schedule applies to
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the equipment type this schedule applies to
     */
    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }

    /**
     * Get the user who created this schedule
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this schedule
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the maintenance records generated from this schedule
     */
    public function maintenanceRecords(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceRecord::class, 'maintenance_schedule_records')
            ->withTimestamps();
    }

    /**
     * Scope for active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for schedules that auto-generate work orders
     */
    public function scopeAutoGenerate($query)
    {
        return $query->where('auto_generate', true);
    }

    /**
     * Scope for due schedules
     */
    public function scopeDue($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->where('next_due_date', '<=', now())
                          ->orWhereRaw('next_due_hours <= (SELECT operating_hours FROM equipment WHERE equipment.id = maintenance_schedules.equipment_id)')
                          ->orWhereRaw('next_due_kilometers <= (SELECT kilometers FROM equipment WHERE equipment.id = maintenance_schedules.equipment_id)');
                    });
    }

    /**
     * Scope for overdue schedules
     */
    public function scopeOverdue($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->where('next_due_date', '<', now()->subDays(7)) // Overdue by more than tolerance
                          ->orWhereRaw('next_due_hours < (SELECT operating_hours FROM equipment WHERE equipment.id = maintenance_schedules.equipment_id) - tolerance_hours')
                          ->orWhereRaw('next_due_kilometers < (SELECT kilometers FROM equipment WHERE equipment.id = maintenance_schedules.equipment_id) - (tolerance_days * 50)'); // Rough conversion
                    });
    }

    /**
     * Scope for schedules due soon
     */
    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->where('is_active', true)
                    ->where('next_due_date', '<=', now()->addDays($days))
                    ->where('next_due_date', '>', now());
    }

    /**
     * Scope for filtering by schedule type
     */
    public function scopeByScheduleType($query, string $type)
    {
        return $query->where('schedule_type', $type);
    }

    /**
     * Scope for filtering by maintenance type
     */
    public function scopeByMaintenanceType($query, string $type)
    {
        return $query->where('maintenance_type', $type);
    }

    /**
     * Check if maintenance is due
     */
    public function getIsDueAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        // Check calendar-based due date
        if ($this->next_due_date && $this->next_due_date <= now()) {
            return true;
        }

        // Check hour-based due
        if ($this->next_due_hours && $this->equipment) {
            $currentHours = $this->equipment->operating_hours ?? 0;
            if ($currentHours >= $this->next_due_hours) {
                return true;
            }
        }

        // Check mileage-based due
        if ($this->next_due_kilometers && $this->equipment) {
            $currentKm = $this->equipment->kilometers ?? 0;
            if ($currentKm >= $this->next_due_kilometers) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if maintenance is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->is_active || !$this->is_due) {
            return false;
        }

        // Check if overdue by tolerance
        if ($this->next_due_date) {
            $toleranceDays = $this->tolerance_days ?? 0;
            if ($this->next_due_date->addDays($toleranceDays) < now()) {
                return true;
            }
        }

        if ($this->next_due_hours && $this->equipment) {
            $currentHours = $this->equipment->operating_hours ?? 0;
            $toleranceHours = $this->tolerance_hours ?? 0;
            if ($currentHours > ($this->next_due_hours + $toleranceHours)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if maintenance is due soon
     */
    public function getIsDueSoonAttribute(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $advanceNoticeDays = $this->advance_notice_days ?? 7;

        if ($this->next_due_date) {
            return $this->next_due_date <= now()->addDays($advanceNoticeDays) && 
                   $this->next_due_date > now();
        }

        return false;
    }

    /**
     * Get days until due
     */
    public function getDaysUntilDueAttribute(): ?int
    {
        if (!$this->next_due_date) {
            return null;
        }

        return now()->diffInDays($this->next_due_date, false);
    }

    /**
     * Get hours until due
     */
    public function getHoursUntilDueAttribute(): ?float
    {
        if (!$this->next_due_hours || !$this->equipment) {
            return null;
        }

        $currentHours = $this->equipment->operating_hours ?? 0;
        return $this->next_due_hours - $currentHours;
    }

    /**
     * Get kilometers until due
     */
    public function getKilometersUntilDueAttribute(): ?float
    {
        if (!$this->next_due_kilometers || !$this->equipment) {
            return null;
        }

        $currentKm = $this->equipment->kilometers ?? 0;
        return $this->next_due_kilometers - $currentKm;
    }

    /**
     * Calculate next due date based on last performed
     */
    public function calculateNextDueDate(): void
    {
        if (!$this->last_performed_at) {
            return;
        }

        switch ($this->schedule_type) {
            case self::SCHEDULE_TIME_BASED:
            case self::SCHEDULE_CALENDAR_BASED:
                if ($this->interval_days) {
                    $this->next_due_date = $this->last_performed_at->addDays($this->interval_days);
                }
                break;

            case self::SCHEDULE_HOUR_BASED:
                if ($this->interval_hours && $this->last_performed_hours) {
                    $this->next_due_hours = $this->last_performed_hours + $this->interval_hours;
                }
                break;

            case self::SCHEDULE_MILEAGE_BASED:
                if ($this->interval_kilometers && $this->last_performed_kilometers) {
                    $this->next_due_kilometers = $this->last_performed_kilometers + $this->interval_kilometers;
                }
                break;
        }
    }

    /**
     * Update last performed data
     */
    public function updateLastPerformed(Carbon $performedAt, ?float $hours = null, ?float $kilometers = null): void
    {
        $this->update([
            'last_performed_at' => $performedAt,
            'last_performed_hours' => $hours,
            'last_performed_kilometers' => $kilometers,
        ]);

        $this->calculateNextDueDate();
        $this->save();
    }

    /**
     * Get formatted schedule type
     */
    public function getFormattedScheduleTypeAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->schedule_type));
    }

    /**
     * Get formatted maintenance type
     */
    public function getFormattedMaintenanceTypeAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->maintenance_type));
    }

    /**
     * Get estimated duration in hours
     */
    public function getEstimatedDurationHoursAttribute(): float
    {
        return round($this->estimated_duration_minutes / 60, 2);
    }

    /**
     * Get all schedule types
     */
    public static function getScheduleTypes(): array
    {
        return [
            self::SCHEDULE_TIME_BASED => 'Time Based',
            self::SCHEDULE_HOUR_BASED => 'Operating Hours Based',
            self::SCHEDULE_MILEAGE_BASED => 'Mileage Based',
            self::SCHEDULE_CYCLE_BASED => 'Cycle Based',
            self::SCHEDULE_CONDITION_BASED => 'Condition Based',
            self::SCHEDULE_CALENDAR_BASED => 'Calendar Based',
        ];
    }
}