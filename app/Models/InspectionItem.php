<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'inspection_id',
        'inspection_template_item_id',
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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'safety_critical' => 'boolean',
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'order_sequence' => 'integer',
    ];

    // Item types
    public const TYPE_VISUAL = 'visual';
    public const TYPE_MEASUREMENT = 'measurement';
    public const TYPE_FUNCTIONAL = 'functional';
    public const TYPE_CHECKLIST = 'checklist';
    public const TYPE_PHOTO = 'photo';
    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_BOOLEAN = 'boolean';

    // Categories
    public const CATEGORY_ENGINE = 'engine';
    public const CATEGORY_HYDRAULIC = 'hydraulic';
    public const CATEGORY_ELECTRICAL = 'electrical';
    public const CATEGORY_STRUCTURAL = 'structural';
    public const CATEGORY_SAFETY = 'safety';
    public const CATEGORY_OPERATIONAL = 'operational';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_FLUIDS = 'fluids';
    public const CATEGORY_ATTACHMENTS = 'attachments';
    public const CATEGORY_DOCUMENTATION = 'documentation';

    /**
     * Get the inspection this item belongs to
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * Get the inspection template item this is based on
     */
    public function inspectionTemplateItem(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplateItem::class);
    }

    /**
     * Get the user who created this inspection item
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this inspection item
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the inspection results for this item
     */
    public function inspectionResults(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    /**
     * Get the latest inspection result for this item
     */
    public function latestResult(): HasMany
    {
        return $this->hasMany(InspectionResult::class)->latest();
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
     * Get all item types
     */
    public static function getItemTypes(): array
    {
        return [
            self::TYPE_VISUAL => 'Visual Inspection',
            self::TYPE_MEASUREMENT => 'Measurement',
            self::TYPE_FUNCTIONAL => 'Functional Test',
            self::TYPE_CHECKLIST => 'Checklist',
            self::TYPE_PHOTO => 'Photo Required',
            self::TYPE_SIGNATURE => 'Signature Required',
            self::TYPE_TEXT => 'Text Input',
            self::TYPE_NUMERIC => 'Numeric Input',
            self::TYPE_BOOLEAN => 'Yes/No Question',
        ];
    }

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_ENGINE => 'Engine & Powertrain',
            self::CATEGORY_HYDRAULIC => 'Hydraulic System',
            self::CATEGORY_ELECTRICAL => 'Electrical System',
            self::CATEGORY_STRUCTURAL => 'Structural Components',
            self::CATEGORY_SAFETY => 'Safety Equipment',
            self::CATEGORY_OPERATIONAL => 'Operational Controls',
            self::CATEGORY_MAINTENANCE => 'Maintenance Items',
            self::CATEGORY_FLUIDS => 'Fluids & Lubricants',
            self::CATEGORY_ATTACHMENTS => 'Attachments & Tools',
            self::CATEGORY_DOCUMENTATION => 'Documentation',
        ];
    }
}