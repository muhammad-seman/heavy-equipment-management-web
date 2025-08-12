<?php

declare(strict_types=1);

namespace App\Exceptions\Equipment;

use App\Exceptions\HeavyEquipmentException;

/**
 * Equipment Not Found Exception
 * 
 * Thrown when a requested equipment resource cannot be found.
 */
class EquipmentNotFoundException extends HeavyEquipmentException
{
    protected int $statusCode = 404;
    protected string $errorCode = 'EQUIPMENT_NOT_FOUND';

    /**
     * Create equipment not found exception
     *
     * @param string|int $identifier Equipment ID or asset number
     */
    public function __construct(string|int $identifier)
    {
        $message = "Equipment with identifier '{$identifier}' not found";
        
        parent::__construct($message, [
            'identifier' => $identifier,
            'suggestions' => [
                'Check if the equipment ID or asset number is correct',
                'Verify that the equipment has not been deleted',
                'Ensure you have permission to access this equipment'
            ]
        ]);
    }
}