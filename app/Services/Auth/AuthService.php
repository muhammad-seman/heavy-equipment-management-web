<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    /**
     * Authenticate user and create API token
     */
    public function login(string $email, string $password, bool $remember = false): array
    {
        $user = User::where('email', $email)
            ->where('is_active', true)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Update last login timestamp
        $user->updateLastLogin();

        // Create token with appropriate expiration
        $tokenExpiration = $remember ? now()->addDays(30) : now()->addDays(1);
        $token = $user->createToken('api-token', ['*'], $tokenExpiration);

        return [
            'user' => $this->getUserData($user),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $tokenExpiration->toISOString(),
        ];
    }

    /**
     * Logout user by revoking current token
     */
    public function logout(User $user): bool
    {
        // Revoke current token
        $user->currentAccessToken()?->delete();

        return true;
    }

    /**
     * Logout from all devices by revoking all tokens
     */
    public function logoutFromAllDevices(User $user): bool
    {
        // Revoke all tokens
        $user->tokens()->delete();

        return true;
    }

    /**
     * Change user password
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        DB::beginTransaction();

        try {
            // Update password
            $user->update([
                'password' => Hash::make($newPassword),
                'updated_at' => now(),
            ]);

            // Optionally revoke all other tokens to force re-login
            $currentToken = $user->currentAccessToken();
            $user->tokens()->where('id', '!=', $currentToken?->id)->delete();

            DB::commit();

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): User
    {
        DB::beginTransaction();

        try {
            // Business logic validation
            $this->validateProfileData($data, $user);

            // Update user
            $user->update($data);

            DB::commit();

            return $user->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get user profile data
     */
    public function getProfile(User $user): User
    {
        return $user->load(['roles.permissions']);
    }

    /**
     * Refresh authentication token
     */
    public function refreshToken(User $user): array
    {
        $currentToken = $user->currentAccessToken();
        
        if (!$currentToken) {
            throw new \InvalidArgumentException('No active token found');
        }

        // Create new token with same expiration time as original
        $originalExpiration = $currentToken->expires_at ?? now()->addDays(1);
        $newExpiration = now()->addDays($originalExpiration->diffInDays(now()));
        
        // Delete current token
        $currentToken->delete();
        
        // Create new token
        $newToken = $user->createToken('api-token', ['*'], $newExpiration);

        return [
            'user' => $this->getUserData($user),
            'token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $newExpiration->toISOString(),
        ];
    }

    /**
     * Get user sessions (active tokens)
     */
    public function getUserSessions(User $user): array
    {
        return $user->tokens()->get()->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'is_current' => $token->id === $user->currentAccessToken()?->id,
                'user_agent' => $token->abilities['user_agent'] ?? null,
                'ip_address' => $token->abilities['ip_address'] ?? null,
            ];
        })->values()->toArray();
    }

    /**
     * Revoke specific session/token
     */
    public function revokeSession(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->find($tokenId);

        if (!$token) {
            throw new \InvalidArgumentException('Token not found');
        }

        // Don't allow revoking current token through this method
        if ($token->id === $user->currentAccessToken()?->id) {
            throw new \InvalidArgumentException('Cannot revoke current session. Use logout instead.');
        }

        $token->delete();

        return true;
    }

    /**
     * Check if user has valid certification
     */
    public function checkCertificationStatus(User $user): array
    {
        $hasActiveCertification = $user->hasActiveCertification();
        $isExpiringSoon = $user->isCertificationExpiringSoon();
        
        $daysUntilExpiry = null;
        if ($user->certification_expiry) {
            $daysUntilExpiry = now()->diffInDays($user->certification_expiry, false);
        }

        return [
            'has_active_certification' => $hasActiveCertification,
            'is_expiring_soon' => $isExpiringSoon,
            'certification_level' => $user->certification_level,
            'certification_expiry' => $user->certification_expiry?->format('Y-m-d'),
            'days_until_expiry' => $daysUntilExpiry,
            'can_operate_equipment' => $hasActiveCertification && $user->can('equipment.operate'),
        ];
    }

    /**
     * Get user activity summary
     */
    public function getUserActivity(User $user, int $days = 30): array
    {
        $fromDate = now()->subDays($days);

        return [
            'last_login' => $user->last_login?->toISOString(),
            'active_sessions' => $user->tokens()->count(),
            'assigned_equipment_count' => $user->assignedEquipment()->count(),
            'recent_equipment_operations' => $this->getRecentEquipmentOperations($user, $fromDate),
            'profile_completeness' => $this->calculateProfileCompleteness($user),
        ];
    }

    /**
     * Calculate profile completeness percentage
     */
    public function calculateProfileCompleteness(User $user): array
    {
        $fields = [
            'first_name' => !empty($user->first_name),
            'last_name' => !empty($user->last_name),
            'email' => !empty($user->email),
            'phone' => !empty($user->phone),
            'employee_id' => !empty($user->employee_id),
            'department' => !empty($user->department),
            'position' => !empty($user->position),
            'certification_level' => !empty($user->certification_level),
            'emergency_contact' => !empty($user->emergency_contact_name) && !empty($user->emergency_contact_phone),
        ];

        $totalFields = count($fields);
        $completedFields = array_sum($fields);
        $percentage = round(($completedFields / $totalFields) * 100, 1);

        return [
            'percentage' => $percentage,
            'completed_fields' => $completedFields,
            'total_fields' => $totalFields,
            'missing_fields' => array_keys(array_filter($fields, fn($completed) => !$completed)),
        ];
    }

    /**
     * Get formatted user data for API responses
     */
    private function getUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'employee_id' => $user->employee_id,
            'department' => $user->department,
            'position' => $user->position,
            'certification_level' => $user->certification_level,
            'certification_expiry' => $user->certification_expiry?->format('Y-m-d'),
            'has_active_certification' => $user->hasActiveCertification(),
            'is_certification_expiring_soon' => $user->isCertificationExpiringSoon(),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'last_login' => $user->last_login?->toISOString(),
        ];
    }

    /**
     * Validate profile update data
     */
    private function validateProfileData(array $data, User $user): void
    {
        // Check if certification expiry is in the future
        if (isset($data['certification_expiry']) && $data['certification_expiry']) {
            $expiryDate = new \DateTime($data['certification_expiry']);
            if ($expiryDate <= now()) {
                throw new \InvalidArgumentException('Certification expiry must be in the future');
            }
        }

        // Validate employee ID format if provided
        if (isset($data['employee_id']) && $data['employee_id'] && !preg_match('/^[A-Z]{2,3}\d{3,4}$/', $data['employee_id'])) {
            throw new \InvalidArgumentException('Employee ID must follow format: AB123 or ABC1234');
        }

        // Validate phone number format
        if (isset($data['phone']) && $data['phone'] && !preg_match('/^\+?[\d\s\-\(\)]{10,20}$/', $data['phone'])) {
            throw new \InvalidArgumentException('Invalid phone number format');
        }
    }

    /**
     * Get recent equipment operations for user
     */
    private function getRecentEquipmentOperations(User $user, \DateTime $fromDate): array
    {
        // This would typically query an operations/activity log table
        // For now, return assigned equipment status
        return $user->assignedEquipment()->with(['type', 'manufacturer'])
            ->get(['id', 'asset_number', 'model', 'status', 'equipment_type_id', 'manufacturer_id'])
            ->map(function ($equipment) {
                return [
                    'equipment_id' => $equipment->id,
                    'asset_number' => $equipment->asset_number,
                    'model' => $equipment->model,
                    'status' => $equipment->status,
                    'type' => $equipment->type?->name,
                    'manufacturer' => $equipment->manufacturer?->name,
                ];
            })
            ->toArray();
    }
}