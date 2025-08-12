<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * API Authentication Middleware
 * 
 * Handles API token-based authentication for the Heavy Equipment Management system.
 * Validates Bearer tokens and sets the authenticated user.
 */
class ApiAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$guards): mixed
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return $this->unauthorizedResponse('Authentication token required');
        }

        // Find the token and its associated user
        $personalAccessToken = PersonalAccessToken::findToken($token);
        
        if (!$personalAccessToken) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        $user = $personalAccessToken->tokenable;
        
        if (!$user || !$user->is_active) {
            return $this->unauthorizedResponse('User account is not active');
        }

        // Check token expiration if configured
        if ($personalAccessToken->expires_at && $personalAccessToken->expires_at->isPast()) {
            return $this->unauthorizedResponse('Token has expired');
        }

        // Update last used timestamp
        $personalAccessToken->forceFill(['last_used_at' => now()])->save();

        // Set the authenticated user
        Auth::setUser($user);
        
        // Add user context to request for logging
        $request->merge(['_authenticated_user_id' => $user->id]);

        return $next($request);
    }

    /**
     * Return unauthorized response
     *
     * @param string $message
     * @return JsonResponse
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTHENTICATION_REQUIRED',
                'message' => $message
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid()->toString()
            ]
        ], 401);
    }
}