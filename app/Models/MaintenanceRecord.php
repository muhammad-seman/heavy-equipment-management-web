<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_id',
        'technician_id',
        'supervisor_id',
        'maintenance_type',
        'priority_level',
        'status',
        'title',
        'description',
        'scheduled_date',
        'started_at',
        'completed_at',
        'estimated_duration',
        'actual_duration',
        'labor_hours',
        'labor_cost',
        'parts_cost',
        'external_cost',
        'total_cost',
        'operating_hours_before',
        'operating_hours_after',
        'work_performed',
        'parts_replaced',
        'next_service_hours',
        'next_service_date',
        'warranty_expires_at',
        'failure_reason',
        'root_cause_analysis',
        'preventive_actions',
        'work_order_number',
        'external_vendor',
        'invoice_number',
        'approval_required',
        'approved_by',
        'approved_at',
        'is_warranty_work',
        'safety_notes',
        'quality_check_passed',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'warranty_expires_at' => 'datetime',
        'next_service_date' => 'datetime',
        'estimated_duration' => 'integer',
        'actual_duration' => 'integer',
        'labor_hours' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'parts_cost' => 'decimal:2',
        'external_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'operating_hours_before' => 'decimal:2',
        'operating_hours_after' => 'decimal:2',
        'next_service_hours' => 'decimal:2',
        'approval_required' => 'boolean',
        'is_warranty_work' => 'boolean',
        'quality_check_passed' => 'boolean',
        'parts_replaced' => 'json',
        'preventive_actions' => 'json',
        'safety_notes' => 'json',
    ];

    // Maintenance types
    public const TYPE_PREVENTIVE = 'preventive';
    public const TYPE_CORRECTIVE = 'corrective';
    public const TYPE_EMERGENCY = 'emergency';
    public const TYPE_PREDICTIVE = 'predictive';
    public const TYPE_CONDITION_BASED = 'condition_based';
    public const TYPE_BREAKDOWN = 'breakdown';
    public const TYPE_OVERHAUL = 'overhaul';
    public const TYPE_UPGRADE = 'upgrade';

    // Priority levels
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';
    public const PRIORITY_EMERGENCY = 'emergency';

    // Status types
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Get the equipment being maintained
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the technician performing maintenance
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get the supervisor overseeing maintenance
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Get the user who approved the maintenance
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
     * Get the parts used in this maintenance
     */
    public function maintenanceParts(): HasMany
    {
        return $this->hasMany(MaintenancePart::class);
    }

    /**
     * Get the maintenance schedules this record fulfills
     */
    public function maintenanceSchedules(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceSchedule::class, 'maintenance_schedule_records')
            ->withTimestamps();
    }

    /**
     * Scope for filtering by maintenance type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('maintenance_type', $type);
    }

    /**
     * Scope for filtering by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority_level', $priority);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for high priority maintenance
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority_level', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL, self::PRIORITY_EMERGENCY]);
    }

    /**
     * Scope for emergency maintenance
     */
    public function scopeEmergency($query)
    {
        return $query->where('priority_level', self::PRIORITY_EMERGENCY);
    }

    /**
     * Scope for overdue maintenance
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
                    ->where('scheduled_date', '<', now());
    }

    /**
     * Scope for today's maintenance
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', today());
    }

    /**
     * Scope for this week's maintenance
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('scheduled_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope for completed maintenance
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending approval
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    /**
     * Check if maintenance is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_SCHEDULED && 
               $this->scheduled_date < now();
    }

    /**
     * Check if maintenance requires approval
     */
    public function getRequiresApprovalAttribute(): bool
    {
        return $this->approval_required && 
               !$this->approved_at && 
               !in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_REJECTED]);
    }

    /**
     * Check if maintenance is approved
     */
    public function getIsApprovedAttribute(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Get actual duration in hours
     */
    public function getActualDurationHoursAttribute(): ?float
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }

        return round($this->started_at->diffInMinutes($this->completed_at) / 60, 2);
    }

    /**
     * Get cost variance
     */
    public function getCostVarianceAttribute(): ?float
    {
        if (!$this->total_cost || !$this->labor_cost || !$this->parts_cost || !$this->external_cost) {
            return null;
        }

        $calculatedTotal = $this->labor_cost + $this->parts_cost + $this->external_cost;
        return $this->total_cost - $calculatedTotal;
    }

    /**
     * Get duration variance in hours
     */
    public function getDurationVarianceAttribute(): ?float
    {
        if (!$this->estimated_duration || !$this->actual_duration_hours) {
            return null;
        }

        return $this->actual_duration_hours - ($this->estimated_duration / 60);
    }

    /**
     * Get formatted maintenance type
     */
    public function getFormattedTypeAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->maintenance_type));
    }

    /**
     * Get formatted priority
     */
    public function getFormattedPriorityAttribute(): string
    {
        return ucwords($this->priority_level);
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatusAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    /**
     * Get all maintenance types
     */
    public static function getMaintenanceTypes(): array
    {
        return [
            self::TYPE_PREVENTIVE => 'Preventive',
            self::TYPE_CORRECTIVE => 'Corrective',
            self::TYPE_EMERGENCY => 'Emergency',
            self::TYPE_PREDICTIVE => 'Predictive',
            self::TYPE_CONDITION_BASED => 'Condition Based',
            self::TYPE_BREAKDOWN => 'Breakdown',
            self::TYPE_OVERHAUL => 'Overhaul',
            self::TYPE_UPGRADE => 'Upgrade',
        ];
    }

    /**
     * Get all priority levels
     */
    public static function getPriorityLevels(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
            self::PRIORITY_CRITICAL => 'Critical',
            self::PRIORITY_EMERGENCY => 'Emergency',
        ];
    }

    /**
     * Get all statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_ON_HOLD => 'On Hold',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }
}