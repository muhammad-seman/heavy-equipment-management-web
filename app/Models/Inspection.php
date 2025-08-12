<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_id',
        'inspector_id',
        'inspection_type',
        'inspection_date',
        'scheduled_date',
        'status',
        'overall_result',
        'notes',
        'signature_data',
        'completion_time',
        'weather_conditions',
        'operating_hours_before',
        'operating_hours_after',
        'fuel_level_before',
        'fuel_level_after',
        'location',
        'temperature',
        'humidity',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'inspection_date' => 'datetime',
        'scheduled_date' => 'datetime',
        'completion_time' => 'datetime',
        'signature_data' => 'json',
        'weather_conditions' => 'json',
        'operating_hours_before' => 'decimal:2',
        'operating_hours_after' => 'decimal:2',
        'fuel_level_before' => 'integer',
        'fuel_level_after' => 'integer',
        'temperature' => 'decimal:1',
        'humidity' => 'integer',
    ];

    // Inspection types
    public const TYPE_SCHEDULED = 'scheduled';
    public const TYPE_UNSCHEDULED = 'unscheduled';
    public const TYPE_PRE_OPERATION = 'pre_operation';
    public const TYPE_POST_OPERATION = 'post_operation';
    public const TYPE_DAILY = 'daily';
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_ANNUAL = 'annual';

    // Inspection statuses
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    // Overall results
    public const RESULT_PASS = 'pass';
    public const RESULT_FAIL = 'fail';
    public const RESULT_WARNING = 'warning';
    public const RESULT_PENDING = 'pending';

    /**
     * Get the equipment being inspected
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the inspector (user)
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /**
     * Get the user who created this inspection
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this inspection
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the inspection items for this inspection
     */
    public function inspectionItems(): HasMany
    {
        return $this->hasMany(InspectionItem::class);
    }

    /**
     * Get the inspection results
     */
    public function inspectionResults(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    /**
     * Scope for filtering by inspection type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('inspection_type', $type);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by result
     */
    public function scopeByResult($query, string $result)
    {
        return $query->where('overall_result', $result);
    }

    /**
     * Scope for overdue inspections
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
                    ->where('scheduled_date', '<', now());
    }

    /**
     * Scope for today's inspections
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', today());
    }

    /**
     * Scope for this week's inspections
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('scheduled_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Check if inspection is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && 
               $this->scheduled_date < now();
    }

    /**
     * Get duration of inspection in minutes
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->completion_time || !$this->inspection_date) {
            return null;
        }

        return $this->inspection_date->diffInMinutes($this->completion_time);
    }

    /**
     * Get formatted inspection type
     */
    public function getFormattedTypeAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->inspection_type));
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatusAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    /**
     * Get formatted overall result
     */
    public function getFormattedResultAttribute(): string
    {
        return ucwords($this->overall_result);
    }

    /**
     * Get all inspection types
     */
    public static function getInspectionTypes(): array
    {
        return [
            self::TYPE_SCHEDULED => 'Scheduled',
            self::TYPE_UNSCHEDULED => 'Unscheduled',
            self::TYPE_PRE_OPERATION => 'Pre Operation',
            self::TYPE_POST_OPERATION => 'Post Operation',
            self::TYPE_DAILY => 'Daily',
            self::TYPE_WEEKLY => 'Weekly',
            self::TYPE_MONTHLY => 'Monthly',
            self::TYPE_ANNUAL => 'Annual',
        ];
    }

    /**
     * Get all inspection statuses
     */
    public static function getInspectionStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get all inspection results
     */
    public static function getInspectionResults(): array
    {
        return [
            self::RESULT_PASS => 'Pass',
            self::RESULT_FAIL => 'Fail',
            self::RESULT_WARNING => 'Warning',
            self::RESULT_PENDING => 'Pending',
        ];
    }
}