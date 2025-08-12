<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'inspection_id',
        'inspection_item_id',
        'result_value',
        'result_status',
        'result_notes',
        'measured_value',
        'photo_path',
        'signature_data',
        'is_within_tolerance',
        'deviation_percentage',
        'requires_action',
        'action_required',
        'priority_level',
        'inspector_notes',
        'timestamp_checked',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'result_value' => 'json',
        'signature_data' => 'json',
        'measured_value' => 'decimal:2',
        'is_within_tolerance' => 'boolean',
        'requires_action' => 'boolean',
        'deviation_percentage' => 'decimal:2',
        'timestamp_checked' => 'datetime',
    ];

    // Result statuses
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';
    public const STATUS_WARNING = 'warning';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REQUIRES_RECHECK = 'requires_recheck';

    // Priority levels
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    // Action types
    public const ACTION_NONE = 'none';
    public const ACTION_MONITOR = 'monitor';
    public const ACTION_REPAIR = 'repair';
    public const ACTION_REPLACE = 'replace';
    public const ACTION_ADJUST = 'adjust';
    public const ACTION_CLEAN = 'clean';
    public const ACTION_LUBRICATE = 'lubricate';
    public const ACTION_TIGHTEN = 'tighten';
    public const ACTION_INVESTIGATE = 'investigate';
    public const ACTION_SHUTDOWN = 'shutdown';

    /**
     * Get the inspection this result belongs to
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * Get the inspection item this result is for
     */
    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class);
    }

    /**
     * Get the user who created this result
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this result
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for filtering by result status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('result_status', $status);
    }

    /**
     * Scope for failed results
     */
    public function scopeFailed($query)
    {
        return $query->where('result_status', self::STATUS_FAIL);
    }

    /**
     * Scope for warning results
     */
    public function scopeWarning($query)
    {
        return $query->where('result_status', self::STATUS_WARNING);
    }

    /**
     * Scope for results requiring action
     */
    public function scopeRequiresAction($query)
    {
        return $query->where('requires_action', true);
    }

    /**
     * Scope for filtering by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority_level', $priority);
    }

    /**
     * Scope for high priority results
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority_level', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    /**
     * Scope for critical results
     */
    public function scopeCritical($query)
    {
        return $query->where('priority_level', self::PRIORITY_CRITICAL);
    }

    /**
     * Get formatted result status
     */
    public function getFormattedStatusAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->result_status));
    }

    /**
     * Get formatted priority level
     */
    public function getFormattedPriorityAttribute(): string
    {
        return ucwords($this->priority_level);
    }

    /**
     * Get formatted action required
     */
    public function getFormattedActionAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->action_required ?? self::ACTION_NONE));
    }

    /**
     * Check if result has photo
     */
    public function getHasPhotoAttribute(): bool
    {
        return !empty($this->photo_path);
    }

    /**
     * Check if result has signature
     */
    public function getHasSignatureAttribute(): bool
    {
        return !empty($this->signature_data);
    }

    /**
     * Get photo URL
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo_path) {
            return null;
        }

        return asset('storage/' . $this->photo_path);
    }

    /**
     * Check if result indicates a problem
     */
    public function getIsProblematicAttribute(): bool
    {
        return in_array($this->result_status, [
            self::STATUS_FAIL,
            self::STATUS_WARNING,
            self::STATUS_REQUIRES_RECHECK
        ]);
    }

    /**
     * Get severity level based on status and priority
     */
    public function getSeverityLevelAttribute(): string
    {
        if ($this->result_status === self::STATUS_FAIL) {
            return $this->priority_level === self::PRIORITY_CRITICAL ? 'critical' : 'high';
        }

        if ($this->result_status === self::STATUS_WARNING) {
            return $this->priority_level === self::PRIORITY_HIGH ? 'high' : 'medium';
        }

        return 'low';
    }

    /**
     * Get all result statuses
     */
    public static function getResultStatuses(): array
    {
        return [
            self::STATUS_PASS => 'Pass',
            self::STATUS_FAIL => 'Fail',
            self::STATUS_WARNING => 'Warning',
            self::STATUS_NOT_APPLICABLE => 'Not Applicable',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_REQUIRES_RECHECK => 'Requires Recheck',
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
        ];
    }

    /**
     * Get all action types
     */
    public static function getActionTypes(): array
    {
        return [
            self::ACTION_NONE => 'No Action Required',
            self::ACTION_MONITOR => 'Monitor',
            self::ACTION_REPAIR => 'Repair',
            self::ACTION_REPLACE => 'Replace',
            self::ACTION_ADJUST => 'Adjust',
            self::ACTION_CLEAN => 'Clean',
            self::ACTION_LUBRICATE => 'Lubricate',
            self::ACTION_TIGHTEN => 'Tighten',
            self::ACTION_INVESTIGATE => 'Investigate',
            self::ACTION_SHUTDOWN => 'Shutdown Equipment',
        ];
    }
}