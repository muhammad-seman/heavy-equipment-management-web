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
            Route::apiResource('users', \App\Http\Controllers\Api\V1\Users\UserController::class);
            Route::post('users/{user}/restore', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'restore'])
                ->middleware('api.permission:users.create');
            Route::put('users/{user}/activate', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'activate'])
                ->middleware('api.permission:users.edit');
            Route::put('users/{user}/roles', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'updateRoles'])
                ->middleware('api.permission:users.edit');
            
            // Additional user routes
            Route::get('users/{user}/activity', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'activity']);
            Route::get('users/{user}/assigned-equipment', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'assignedEquipment']);
            Route::get('users/search', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'search']);
            Route::get('users/statistics', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'statistics'])
                ->middleware('api.permission:users.view_statistics');
        });

        // User Profile Routes (accessible to all authenticated users)
        Route::get('profile', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'profile']);
        Route::put('profile', [\App\Http\Controllers\Api\V1\Users\UserController::class, 'updateProfile']);

        // Role & Permission Management
        Route::middleware(['api.permission:roles.view'])->group(function () {
            // Static routes first (before resource routes)
            Route::get('roles/search', [\App\Http\Controllers\Api\V1\System\RoleController::class, 'search']);
            Route::get('roles/hierarchy', [\App\Http\Controllers\Api\V1\System\RoleController::class, 'hierarchy']);
            Route::get('roles/statistics', [\App\Http\Controllers\Api\V1\System\RoleController::class, 'statistics'])
                ->middleware('api.permission:roles.view_statistics');
            
            // Resource routes
            Route::apiResource('roles', \App\Http\Controllers\Api\V1\System\RoleController::class);
            
            // Additional role-specific routes
            Route::put('roles/{role}/permissions', [\App\Http\Controllers\Api\V1\System\RoleController::class, 'updatePermissions'])
                ->middleware('api.permission:permissions.assign');
            Route::get('roles/{role}/users', [\App\Http\Controllers\Api\V1\System\RoleController::class, 'users']);
            Route::post('roles/{role}/clone', [\App\Http\Controllers\Api\V1\System\RoleController::class, 'clone'])
                ->middleware('api.permission:roles.create');
        });
        
        // Permission Management
        Route::middleware(['api.permission:permissions.view'])->group(function () {
            // Static routes first (before resource routes)
            Route::get('permissions/search', [\App\Http\Controllers\Api\V1\System\PermissionController::class, 'search']);
            Route::get('permissions/modules', [\App\Http\Controllers\Api\V1\System\PermissionController::class, 'modules']);
            Route::get('permissions/statistics', [\App\Http\Controllers\Api\V1\System\PermissionController::class, 'statistics']);
            Route::get('permissions/category/{category}', [\App\Http\Controllers\Api\V1\System\PermissionController::class, 'byCategory']);
            Route::post('permissions/sync', [\App\Http\Controllers\Api\V1\System\PermissionController::class, 'sync'])
                ->middleware('api.permission:permissions.assign');
            Route::post('permissions/bulk-assign', [\App\Http\Controllers\Api\V1\System\PermissionController::class, 'bulkAssign'])
                ->middleware('api.permission:permissions.assign');
            
            // Resource routes
            Route::apiResource('permissions', \App\Http\Controllers\Api\V1\System\PermissionController::class)
                ->except(['store', 'update', 'destroy']); // Only allow viewing for now
            
            // Permission-specific routes
            Route::get('permissions/{permission}/roles', [\App\Http\Controllers\Api\V1\System\PermissionController::class, 'roles']);
            Route::get('permissions/{permission}/usage', [\App\Http\Controllers\Api\V1\System\PermissionController::class, 'usage']);
        });

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
            
            // Equipment Status Management (TODO: Implement EquipmentStatusController)
            // Route::put('equipment/{equipment}/status', [\App\Http\Controllers\Api\V1\Equipment\EquipmentStatusController::class, 'update'])
            //     ->middleware('api.permission:equipment.edit');
            // Route::get('equipment/{equipment}/status-history', [\App\Http\Controllers\Api\V1\Equipment\EquipmentStatusController::class, 'history']);
            
            // Equipment Location (TODO: Implement EquipmentLocationController)
            // Route::put('equipment/{equipment}/location', [\App\Http\Controllers\Api\V1\Equipment\EquipmentLocationController::class, 'update'])
            //     ->middleware('api.permission:equipment.edit');
            // Route::get('equipment/{equipment}/location-history', [\App\Http\Controllers\Api\V1\Equipment\EquipmentLocationController::class, 'history']);
            
            // Equipment Assignment (TODO: Implement EquipmentAssignmentController)
            // Route::put('equipment/{equipment}/assign', [\App\Http\Controllers\Api\V1\Equipment\EquipmentAssignmentController::class, 'assign'])
            //     ->middleware('api.permission:equipment.edit');
            // Route::put('equipment/{equipment}/unassign', [\App\Http\Controllers\Api\V1\Equipment\EquipmentAssignmentController::class, 'unassign'])
            //     ->middleware('api.permission:equipment.edit');
            // Route::get('equipment/assigned', [\App\Http\Controllers\Api\V1\Equipment\EquipmentAssignmentController::class, 'assignedToUser']);
        });

        // Inspection Management Routes
        Route::middleware(['api.permission:inspection.view'])->group(function () {
            // Static routes first (before resource routes)
            Route::get('inspections/search', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'search']);
            Route::get('inspections/statistics', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'statistics'])
                ->middleware('api.permission:inspection.view');
            Route::get('inspections/summary', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'summary']);
            Route::get('inspections/today', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'today']);
            Route::get('inspections/this-week', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'thisWeek']);
            Route::get('inspections/overdue', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'overdue']);
            Route::get('inspections/requires-action', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'requiresAction']);
            Route::get('inspections/types', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'types']);
            Route::get('inspections/statuses', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'statuses']);
            Route::get('inspections/results', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'results']);
            Route::get('inspections/equipment/{equipmentId}', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'byEquipment']);
            Route::get('inspections/inspector/{inspectorId}', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'byInspector']);
            
            // Resource routes
            Route::apiResource('inspections', \App\Http\Controllers\Api\V1\Inspections\InspectionController::class);
            
            // Inspection-specific routes
            Route::post('inspections/{inspection}/restore', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'restore'])
                ->middleware('api.permission:inspection.create');
            Route::put('inspections/{inspection}/start', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'start'])
                ->middleware('api.permission:inspection.edit');
            Route::put('inspections/{inspection}/complete', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'complete'])
                ->middleware('api.permission:inspection.edit');
            Route::put('inspections/{inspection}/cancel', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'cancel'])
                ->middleware('api.permission:inspection.edit');
            Route::post('inspections/{inspection}/duplicate', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'duplicate'])
                ->middleware('api.permission:inspection.create');
            Route::post('inspections/generate-from-template', [\App\Http\Controllers\Api\V1\Inspections\InspectionController::class, 'generateFromTemplate'])
                ->middleware('api.permission:inspection.create');
        });

        // Equipment Documents (TODO: Implement EquipmentDocumentController)
        // Route::middleware(['api.permission:equipment.view', 'api.throttle:api-upload'])->group(function () {
        //     Route::get('equipment/{equipment}/documents', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'index']);
        //     Route::post('equipment/{equipment}/documents', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'store'])
        //         ->middleware('api.permission:equipment.edit');
        //     Route::get('equipment/{equipment}/documents/{document}', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'show']);
        //     Route::put('equipment/{equipment}/documents/{document}', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'update'])
        //         ->middleware('api.permission:equipment.edit');
        //     Route::delete('equipment/{equipment}/documents/{document}', [\App\Http\Controllers\Api\V1\Equipment\EquipmentDocumentController::class, 'destroy'])
        //         ->middleware('api.permission:equipment.edit');
        // });

        // System Settings (TODO: Implement SettingsController)
        // Route::middleware(['api.permission:system.view'])->group(function () {
        //     Route::get('settings', [\App\Http\Controllers\Api\V1\System\SettingsController::class, 'index']);
        //     Route::get('settings/{key}', [\App\Http\Controllers\Api\V1\System\SettingsController::class, 'show']);
        //     Route::put('settings', [\App\Http\Controllers\Api\V1\System\SettingsController::class, 'updateMultiple'])
        //         ->middleware('api.permission:system.edit');
        //     Route::put('settings/{key}', [\App\Http\Controllers\Api\V1\System\SettingsController::class, 'update'])
        //         ->middleware('api.permission:system.edit');
        // });

        // Activity Logs (TODO: Implement ActivityLogController)
        // Route::middleware(['api.permission:system.view'])->group(function () {
        //     Route::get('activity-logs', [\App\Http\Controllers\Api\V1\System\ActivityLogController::class, 'index']);
        //     Route::get('activity-logs/user/{user}', [\App\Http\Controllers\Api\V1\System\ActivityLogController::class, 'userLogs']);
        //     Route::get('activity-logs/equipment/{equipment}', [\App\Http\Controllers\Api\V1\System\ActivityLogController::class, 'equipmentLogs']);
        // });

        // Notifications (TODO: Implement NotificationController)
        // Route::get('notifications', [\App\Http\Controllers\Api\V1\System\NotificationController::class, 'index']);
        // Route::put('notifications/{notification}/read', [\App\Http\Controllers\Api\V1\System\NotificationController::class, 'markAsRead']);
        // Route::put('notifications/mark-all-read', [\App\Http\Controllers\Api\V1\System\NotificationController::class, 'markAllAsRead']);
        // Route::delete('notifications/{notification}', [\App\Http\Controllers\Api\V1\System\NotificationController::class, 'destroy']);

        // Heavy Operations (TODO: Implement ReportController)
        // Route::middleware(['api.throttle:api-heavy'])->group(function () {
        //     Route::get('reports/equipment-utilization', [\App\Http\Controllers\Api\V1\System\ReportController::class, 'equipmentUtilization'])
        //         ->middleware('api.permission:reports.view');
        //     Route::post('reports/export', [\App\Http\Controllers\Api\V1\System\ReportController::class, 'export'])
        //         ->middleware('api.permission:reports.export');
        // });
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