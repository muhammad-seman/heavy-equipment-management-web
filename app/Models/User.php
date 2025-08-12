<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * User Model
 * 
 * Represents users in the Heavy Equipment Management system.
 * Includes authentication, authorization, and user profile management.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'employee_id',
        'department',
        'position',
        'certification_level',
        'certification_expiry',
        'is_active',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'certification_expiry' => 'date',
            'last_login' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Equipment assigned to this user
     *
     * @return HasMany
     */
    public function assignedEquipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'assigned_user_id');
    }

    /**
     * Equipment documents uploaded by this user
     *
     * @return HasMany
     */
    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(EquipmentDocument::class, 'uploaded_by');
    }

    /**
     * Operating sessions by this user
     *
     * @return HasMany
     */
    public function operatingSessions(): HasMany
    {
        return $this->hasMany(OperatingSession::class, 'operator_id');
    }

    /**
     * Notifications for this user
     *
     * @return HasMany
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Activity logs for this user
     *
     * @return HasMany
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Get user's full name
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Check if user has active certification
     *
     * @return bool
     */
    public function hasActiveCertification(): bool
    {
        if (!$this->certification_expiry) {
            return true; // No expiry means permanent certification
        }

        return $this->certification_expiry->isFuture();
    }

    /**
     * Check if certification is expiring soon (within 30 days)
     *
     * @return bool
     */
    public function isCertificationExpiringSoon(): bool
    {
        if (!$this->certification_expiry) {
            return false;
        }

        return $this->certification_expiry->isAfter(now()) 
            && $this->certification_expiry->isBefore(now()->addDays(30));
    }

    /**
     * Update last login timestamp
     *
     * @return void
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login' => now()]);
    }

    /**
     * Scope to get only active users
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get users by certification level
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $level
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCertificationLevel($query, string $level)
    {
        return $query->where('certification_level', $level);
    }

    /**
     * Scope to get users by department
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $department
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope to search users by name or employee ID
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%")
              ->orWhere('employee_id', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
