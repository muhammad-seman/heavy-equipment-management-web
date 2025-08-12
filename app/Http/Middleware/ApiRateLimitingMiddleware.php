<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

/**
 * API Rate Limiting Middleware
 * 
 * Provides flexible rate limiting for API endpoints with different
 * limits based on operation type and user authentication status.
 */
class ApiRateLimitingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $limiter
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $limiter = 'api'): mixed
    {
        $key = $this->resolveRequestSignature($request);
        
        $rateLimiter = app(RateLimiter::class);
        
        // Get limit configuration
        $limits = $this->getLimits($limiter, $request);
        
        foreach ($limits as $limit) {
            if ($rateLimiter->tooManyAttempts($key . ':' . $limit['window'], $limit['maxAttempts'])) {
                return $this->rateLimitResponse($key, $limit);
            }
            
            $rateLimiter->hit($key . ':' . $limit['window'], $limit['decaySeconds']);
        }

        $response = $next($request);
        
        // Add rate limit headers
        return $this->addHeaders($response, $key, $limits, $rateLimiter);
    }

    /**
     * Resolve the request signature for rate limiting
     *
     * @param Request $request
     * @return string
     */
    private function resolveRequestSignature(Request $request): string
    {
        if ($user = $request->user()) {
            return 'user:' . $user->id;
        }

        return 'ip:' . $request->ip();
    }

    /**
     * Get rate limits based on limiter type and request context
     *
     * @param string $limiter
     * @param Request $request
     * @return array
     */
    private function getLimits(string $limiter, Request $request): array
    {
        $user = $request->user();
        $isAuthenticated = !is_null($user);
        
        return match ($limiter) {
            'api' => [
                [
                    'maxAttempts' => $isAuthenticated ? 120 : 60, // Authenticated users get higher limits
                    'decaySeconds' => 60,
                    'window' => 'minute'
                ]
            ],
            'api-heavy' => [
                [
                    'maxAttempts' => $isAuthenticated ? 20 : 5,
                    'decaySeconds' => 60,
                    'window' => 'minute'
                ],
                [
                    'maxAttempts' => $isAuthenticated ? 100 : 20,
                    'decaySeconds' => 3600,
                    'window' => 'hour'
                ]
            ],
            'api-upload' => [
                [
                    'maxAttempts' => $isAuthenticated ? 10 : 3,
                    'decaySeconds' => 60,
                    'window' => 'minute'
                ],
                [
                    'maxAttempts' => $isAuthenticated ? 50 : 10,
                    'decaySeconds' => 3600,
                    'window' => 'hour'
                ]
            ],
            'api-auth' => [
                [
                    'maxAttempts' => 5, // Stricter for authentication endpoints
                    'decaySeconds' => 60,
                    'window' => 'minute'
                ],
                [
                    'maxAttempts' => 20,
                    'decaySeconds' => 3600,
                    'window' => 'hour'
                ]
            ],
            default => [
                [
                    'maxAttempts' => 60,
                    'decaySeconds' => 60,
                    'window' => 'minute'
                ]
            ]
        };
    }

    /**
     * Create rate limit exceeded response
     *
     * @param string $key
     * @param array $limit
     * @return JsonResponse
     */
    private function rateLimitResponse(string $key, array $limit): JsonResponse
    {
        $rateLimiter = app(RateLimiter::class);
        $retryAfter = $rateLimiter->availableIn($key . ':' . $limit['window']);
        
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => 'Too many requests. Please try again later.',
                'details' => [
                    'retry_after_seconds' => $retryAfter,
                    'limit' => $limit['maxAttempts'],
                    'window' => $limit['window']
                ]
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid()->toString()
            ]
        ], 429)->header('Retry-After', $retryAfter);
    }

    /**
     * Add rate limit headers to response
     *
     * @param mixed $response
     * @param string $key
     * @param array $limits
     * @param RateLimiter $rateLimiter
     * @return mixed
     */
    private function addHeaders($response, string $key, array $limits, RateLimiter $rateLimiter)
    {
        // Add headers for the most restrictive limit (usually per minute)
        $primaryLimit = $limits[0];
        $remainingAttempts = $rateLimiter->retriesLeft($key . ':' . $primaryLimit['window'], $primaryLimit['maxAttempts']);
        
        return $response->withHeaders([
            'X-RateLimit-Limit' => $primaryLimit['maxAttempts'],
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
            'X-RateLimit-Reset' => now()->addSeconds($primaryLimit['decaySeconds'])->timestamp
        ]);
    }
}