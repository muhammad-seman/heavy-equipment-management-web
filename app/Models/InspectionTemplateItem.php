<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionTemplateItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_type_id',
        'item_name',
        'item_description',
        'category',
        'item_type',
        'is_required',
        'order_sequence',
        'min_value',
        'max_value',
        'unit_of_measure',
        'expected_condition',
        'safety_critical',
        'frequency',
        'instructions',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'safety_critical' => 'boolean',
        'is_active' => 'boolean',
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'order_sequence' => 'integer',
        'instructions' => 'json',
    ];

    // Frequencies
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_QUARTERLY = 'quarterly';
    public const FREQUENCY_SEMI_ANNUAL = 'semi_annual';
    public const FREQUENCY_ANNUAL = 'annual';
    public const FREQUENCY_PRE_OPERATION = 'pre_operation';
    public const FREQUENCY_POST_OPERATION = 'post_operation';
    public const FREQUENCY_MAINTENANCE = 'maintenance';

    /**
     * Get the equipment type this template item belongs to
     */
    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }

    /**
     * Get the user who created this template item
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this template item
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the inspection items created from this template
     */
    public function inspectionItems(): HasMany
    {
        return $this->hasMany(InspectionItem::class);
    }

    /**
     * Scope for filtering by equipment type
     */
    public function scopeForEquipmentType($query, int $equipmentTypeId)
    {
        return $query->where('equipment_type_id', $equipmentTypeId);
    }

    /**
     * Scope for filtering by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for filtering by item type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('item_type', $type);
    }

    /**
     * Scope for filtering by frequency
     */
    public function scopeByFrequency($query, string $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Scope for active items only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for required items only
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope for safety critical items
     */
    public function scopeSafetyCritical($query)
    {
        return $query->where('safety_critical', true);
    }

    /**
     * Scope for ordering by sequence
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    /**
     * Get formatted item type
     */
    public function getFormattedTypeAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->item_type));
    }

    /**
     * Get formatted category
     */
    public function getFormattedCategoryAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->category));
    }

    /**
     * Get formatted frequency
     */
    public function getFormattedFrequencyAttribute(): string
    {
        return ucwords(str_replace('_', ' ', $this->frequency));
    }

    /**
     * Check if item has numeric validation
     */
    public function getHasNumericValidationAttribute(): bool
    {
        return !is_null($this->min_value) || !is_null($this->max_value);
    }

    /**
     * Get validation message for numeric items
     */
    public function getValidationMessageAttribute(): ?string
    {
        if (!$this->has_numeric_validation) {
            return null;
        }

        $message = '';
        if (!is_null($this->min_value) && !is_null($this->max_value)) {
            $message = "Value must be between {$this->min_value} and {$this->max_value}";
        } elseif (!is_null($this->min_value)) {
            $message = "Value must be at least {$this->min_value}";
        } elseif (!is_null($this->max_value)) {
            $message = "Value must not exceed {$this->max_value}";
        }

        if ($this->unit_of_measure) {
            $message .= " {$this->unit_of_measure}";
        }

        return $message;
    }

    /**
     * Get usage count
     */
    public function getUsageCountAttribute(): int
    {
        return $this->inspectionItems()->count();
    }

    /**
     * Get all frequencies
     */
    public static function getFrequencies(): array
    {
        return [
            self::FREQUENCY_DAILY => 'Daily',
            self::FREQUENCY_WEEKLY => 'Weekly',
            self::FREQUENCY_MONTHLY => 'Monthly',
            self::FREQUENCY_QUARTERLY => 'Quarterly',
            self::FREQUENCY_SEMI_ANNUAL => 'Semi-Annual',
            self::FREQUENCY_ANNUAL => 'Annual',
            self::FREQUENCY_PRE_OPERATION => 'Pre-Operation',
            self::FREQUENCY_POST_OPERATION => 'Post-Operation',
            self::FREQUENCY_MAINTENANCE => 'During Maintenance',
        ];
    }
}