<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    
    // Authentication Routes (no auth required)
    Route::prefix('auth')->group(function () {
        Route::post('login', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'login']);
        Route::post('forgot-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'resetPassword']);
        
        // Authenticated auth routes
        Route::middleware(['api.auth'])->group(function () {
            Route::post('logout', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'logout']);
            Route::post('refresh', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'refresh']);
            Route::get('me', [\App\Http\Controllers\Api\V1\Auth\AuthController::class, 'me']);
        });
    });

    // Protected API routes
    Route::middleware(['api.auth', 'api.throttle:api'])->group(function () {
        
        // User Management Routes
        Route::middleware(['api.permission:users.view'])->group(function () {
            Route::apiResource('users', \App\Http\Controllers\Api\V1\Auth\UserController::class);
            Route::post('users/{user}/restore', [\App\Http\Controllers\Api\V1\Auth\UserController::class, 'restore'])
                ->middleware('api.permission:users.create');
            Route::put('users/{user}/activate', [\App\Http\Controllers\Api\V1\Auth\UserController::class, 'activate'])
                ->middleware('api.permission:users.edit');
            Route::put('users/{user}/roles', [\App\Http\Controllers\Api\V1\Auth\UserController::class, 'updateRoles'])
                ->middleware('api.permission:users.edit');
        });

        // Role & Permission Management
        Route::middleware(['api.permission:roles.view'])->group(function () {
            Route::apiResource('roles', \App\Http\Controllers\Api\V1\Auth\RoleController::class);
            Route::put('roles/{role}/permissions', [\App\Http\Controllers\Api\V1\Auth\RoleController::class, 'updatePermissions'])
                ->middleware('api.permission:roles.edit');
        });
        
        Route::get('permissions', [\App\Http\Controllers\Api\V1\Auth\PermissionController::class, 'index'])
            ->middleware('api.permission:permissions.view');
        Route::get('permissions/modules', [\App\Http\Controllers\Api\V1\Auth\PermissionController::class, 'modules'])
            ->middleware('api.permission:permissions.view');

        // Equipment Management Routes
        Route::middleware(['api.permission:equipment.view'])->group(function () {
            // Equipment Categories
            Route::apiResource('equipment-categories', \App\Http\Controllers\Api\V1\Equipment\EquipmentCategoryController::class);
            
            // Equipment Types  
            Route::apiResource('equipment-types', \App\Http\Controllers\Api\V1\Equipment\EquipmentTypeController::class);
            
            // Manufacturers
            Route::apiResource('manufacturers', \App\Http\Controllers\Api\V1\Equipment\ManufacturerController::class);
            
            // Main Equipment Resource
            Route::apiResource('equipment', \App\Http\Controllers\Api\V1\Equipment\EquipmentController::class);
            Route::post('equipment/{equipment}/restore', [\App\Http\Controllers\Api\V1\Equipment\EquipmentController::class, 'restore'])
                ->middleware('api.permission:equipment.create');
            
            // Equipment Status Management
            Route::put('equipment/{equipment}/status', [\App\Http\Controllers\Api\V1\Equipment\EquipmentStatusController::class, 'update'])
                ->middleware('api.permission:equipment.edit');
            Route::get('equipment/{equipment}/status-history', [\App\Http\Controllers\Api\V1\Equipment\EquipmentStatusController::class, 'history']);
            
            // Equipment Location
            Route::put('equipment/{equipment}/location', [\App\Http\Controllers\Api\V1\Equipment\EquipmentLocationController::class, 'update'])
                ->middleware('api.permission:equipment.edit');
            Route::get('equipment/{equipment}/location-history', [\App\Http\Controllers\Api\V1\Equipment\EquipmentLocationController::class, 'history']);
            
            // Equipment Assignment
            Route::put('equipment/{equipment}/assign', [\App\Http\Controllers\Api\V1\Equipment\EquipmentAssignmentController::class, 'assign'])
                ->middleware('api.permission:equipment.edit');
            Route::put('equipment/{equipment}/unassign', [\App\Http\Controllers\Api\V1\Equipment\EquipmentAssignmentController::class, 'unassign'])
                ->middleware('api.permission:equipment.edit');
            Route::get('equipment/assigned', [\App\Http\Controllers\Api\V1\Equipment\EquipmentAssignmentController::class, 'assignedToUser']);
        });

        // Equipment Documents (with file upload throttling)
        Route::middleware(['api.permission:equipment.view', 'api.throttle:api-upload'])->group(function () {
            Route::get('equipment/{equipment}/documents', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'index']);
            Route::post('equipment/{equipment}/documents', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'store'])
                ->middleware('api.permission:equipment.edit');
            Route::get('equipment/{equipment}/documents/{document}', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'show']);
            Route::put('equipment/{equipment}/documents/{document}', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'update'])
                ->middleware('api.permission:equipment.edit');
            Route::delete('equipment/{equipment}/documents/{document}', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'destroy'])
                ->middleware('api.permission:equipment.edit');
        });

        // System Settings
        Route::middleware(['api.permission:system.view'])->group(function () {
            Route::get('settings', [\App\Http\Controllers\Api\V1\System\SettingsController::class, 'index']);
            Route::get('settings/{key}', [\App\Http\Controllers\Api\V1\System\SettingsController::class, 'show']);
            Route::put('settings', [\App\Http\Controllers\Api\V1\System\SettingsController::class, 'updateMultiple'])
                ->middleware('api.permission:system.edit');
            Route::put('settings/{key}', [\App\Http\Controllers\Api\V1\System\SettingsController::class, 'update'])
                ->middleware('api.permission:system.edit');
        });

        // Activity Logs
        Route::middleware(['api.permission:system.view'])->group(function () {
            Route::get('activity-logs', [\App\Http\Controllers\Api\V1\System\ActivityLogController::class, 'index']);
            Route::get('activity-logs/user/{user}', [\App\Http\Controllers\Api\V1\System\ActivityLogController::class, 'userLogs']);
            Route::get('activity-logs/equipment/{equipment}', [\App\Http\Controllers\Api\V1\System\ActivityLogController::class, 'equipmentLogs']);
        });

        // Notifications
        Route::get('notifications', [\App\Http\Controllers\Api\V1\System\NotificationController::class, 'index']);
        Route::put('notifications/{notification}/read', [\App\Http\Controllers\Api\V1\System\NotificationController::class, 'markAsRead']);
        Route::put('notifications/mark-all-read', [\App\Http\Controllers\Api\V1\System\NotificationController::class, 'markAllAsRead']);
        Route::delete('notifications/{notification}', [\App\Http\Controllers\Api\V1\System\NotificationController::class, 'destroy']);

        // Heavy Operations (reports, exports) - Limited rate limiting
        Route::middleware(['api.throttle:api-heavy'])->group(function () {
            Route::get('reports/equipment-utilization', [\App\Http\Controllers\Api\V1\System\ReportController::class, 'equipmentUtilization'])
                ->middleware('api.permission:reports.view');
            Route::post('reports/export', [\App\Http\Controllers\Api\V1\System\ReportController::class, 'export'])
                ->middleware('api.permission:reports.export');
        });
    });
});

// Health check endpoint (no authentication required)
Route::get('health', function () {
    return response()->json([
        'success' => true,
        'data' => [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment()
        ]
    ]);
});