<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Base API Controller
 * 
 * Provides standardized response formats and common functionality
 * for all API controllers in the Heavy Equipment Management system.
 */
abstract class BaseApiController extends Controller
{
    /**
     * Return a successful JSON response
     *
     * @param mixed $data The data to return
     * @param string|null $message Success message
     * @param array $meta Additional metadata
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function successResponse(
        mixed $data = null,
        ?string $message = null,
        array $meta = [],
        int $status = 200
    ): JsonResponse {
        $response = [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?? Str::uuid()->toString()
            ], $meta)
        ];

        // Remove null values to keep response clean
        return response()->json(array_filter($response, fn($value) => !is_null($value)), $status);
    }

    /**
     * Return an error JSON response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array $details Additional error details
     * @param array $meta Additional metadata
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message,
        int $code = 400,
        array $details = [],
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $this->getErrorCodeString($code),
                'message' => $message,
                'details' => $details
            ],
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?? Str::uuid()->toString()
            ], $meta)
        ], $code);
    }

    /**
     * Return a validation error response
     *
     * @param array $errors Validation errors
     * @param string $message Main error message
     * @return JsonResponse
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'The given data was invalid'
    ): JsonResponse {
        return $this->errorResponse(
            $message,
            422,
            $errors
        );
    }

    /**
     * Return a not found error response
     *
     * @param string $resource Resource name that was not found
     * @return JsonResponse
     */
    protected function notFoundResponse(string $resource = 'Resource'): JsonResponse
    {
        return $this->errorResponse(
            "{$resource} not found",
            404
        );
    }

    /**
     * Return an unauthorized error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Return a forbidden error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Return a method not allowed error response
     *
     * @return JsonResponse
     */
    protected function methodNotAllowedResponse(): JsonResponse
    {
        return $this->errorResponse('Method not allowed', 405);
    }

    /**
     * Return an internal server error response
     *
     * @param string $message Error message
     * @return JsonResponse
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse($message, 500);
    }

    /**
     * Get error code string from HTTP status code
     *
     * @param int $code HTTP status code
     * @return string
     */
    private function getErrorCodeString(int $code): string
    {
        return match ($code) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_FAILED',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'INTERNAL_SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'UNKNOWN_ERROR'
        };
    }

    /**
     * Get performance metadata for response
     *
     * @param float $startTime Request start time
     * @param int $queryCount Number of database queries
     * @return array
     */
    protected function getPerformanceMeta(float $startTime, int $queryCount = 0): array
    {
        return [
            'performance' => [
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'memory_usage' => $this->formatBytes(memory_get_usage(true)),
                'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
                'queries_executed' => $queryCount
            ]
        ];
    }

    /**
     * Format bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor(log($bytes, 1024));
        
        return sprintf('%.1f%s', $bytes / (1024 ** $factor), $units[$factor]);
    }
}