<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Exceptions\UnauthorizedException;

/**
 * Check Permission Middleware
 * 
 * Validates that the authenticated user has the required permission
 * to access the requested resource or perform the requested action.
 */
class CheckPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $permission
     * @param string|null $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $guard = null): mixed
    {
        $authGuard = app('auth')->guard($guard);
        
        if ($authGuard->guest()) {
            return $this->unauthorizedResponse('Authentication required');
        }

        $user = $authGuard->user();
        
        if (!$user->can($permission)) {
            return $this->forbiddenResponse(
                'You do not have permission to perform this action',
                $permission
            );
        }

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

    /**
     * Return forbidden response
     *
     * @param string $message
     * @param string $permission
     * @return JsonResponse
     */
    private function forbiddenResponse(string $message, string $permission): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INSUFFICIENT_PERMISSIONS',
                'message' => $message,
                'details' => [
                    'required_permission' => $permission,
                    'user_permissions' => auth()->user()?->getAllPermissions()->pluck('name')->toArray() ?? []
                ]
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID') ?? \Illuminate\Support\Str::uuid()->toString()
            ]
        ], 403);
    }
}