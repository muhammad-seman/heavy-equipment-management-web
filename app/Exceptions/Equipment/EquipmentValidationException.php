<?php

declare(strict_types=1);

namespace App\Exceptions\Equipment;

use App\Exceptions\HeavyEquipmentException;

/**
 * Equipment Validation Exception
 * 
 * Thrown when equipment data validation fails due to business rules.
 */
class EquipmentValidationException extends HeavyEquipmentException
{
    protected int $statusCode = 422;
    protected string $errorCode = 'EQUIPMENT_VALIDATION_FAILED';

    /**
     * Create equipment validation exception
     *
     * @param string $message
     * @param array $validationErrors
     */
    public function __construct(string $message, array $validationErrors = [])
    {
        parent::__construct($message, [
            'validation_errors' => $validationErrors
        ]);
    }

    /**
     * Create exception for invalid status transition
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return static
     */
    public static function invalidStatusTransition(string $currentStatus, string $newStatus): static
    {
        return new static(
            "Cannot change equipment status from '{$currentStatus}' to '{$newStatus}'",
            [
                'current_status' => $currentStatus,
                'attempted_status' => $newStatus,
                'allowed_transitions' => self::getAllowedStatusTransitions($currentStatus)
            ]
        );
    }

    /**
     * Create exception for maintenance due equipment
     *
     * @param string $equipmentId
     * @param int $hoursOverdue
     * @return static
     */
    public static function maintenanceOverdue(string $equipmentId, int $hoursOverdue): static
    {
        return new static(
            "Equipment {$equipmentId} cannot be operated - maintenance is overdue by {$hoursOverdue} hours",
            [
                'equipment_id' => $equipmentId,
                'hours_overdue' => $hoursOverdue,
                'required_action' => 'Complete scheduled maintenance before operating'
            ]
        );
    }

    /**
     * Get allowed status transitions for a given status
     *
     * @param string $currentStatus
     * @return array
     */
    private static function getAllowedStatusTransitions(string $currentStatus): array
    {
        return match ($currentStatus) {
            'active' => ['maintenance', 'repair', 'standby', 'retired'],
            'maintenance' => ['active', 'repair', 'standby'],
            'repair' => ['active', 'maintenance', 'standby', 'disposal'],
            'standby' => ['active', 'maintenance', 'repair', 'retired'],
            'retired' => ['disposal'],
            'disposal' => [],
            default => []
        };
    }
}