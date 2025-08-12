<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Base Heavy Equipment Exception
 * 
 * Base exception class for all custom exceptions in the
 * Heavy Equipment Management system.
 */
abstract class HeavyEquipmentException extends Exception
{
    /**
     * The HTTP status code for this exception
     *
     * @var int
     */
    protected int $statusCode = 400;

    /**
     * Additional error details
     *
     * @var array
     */
    protected array $details = [];

    /**
     * Error code for API responses
     *
     * @var string
     */
    protected string $errorCode = 'SYSTEM_ERROR';

    /**
     * Create a new exception instance
     *
     * @param string $message
     * @param array $details
     * @param int|null $statusCode
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = '',
        array $details = [],
        ?int $statusCode = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        
        $this->details = $details;
        
        if ($statusCode) {
            $this->statusCode = $statusCode;
        }
    }

    /**
     * Get the HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get error details
     *
     * @return array
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Get error code
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Convert exception to JSON response
     *
     * @return JsonResponse
     */
    public function toResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->getErrorCode(),
                'message' => $this->getMessage(),
                'details' => $this->getDetails()
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid()->toString()
            ]
        ], $this->getStatusCode());
    }
}