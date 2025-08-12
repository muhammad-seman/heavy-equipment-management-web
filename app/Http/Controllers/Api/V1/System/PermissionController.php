<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\System\StorePermissionRequest;
use App\Http\Requests\Api\V1\System\UpdatePermissionRequest;
use App\Http\Resources\Api\V1\System\PermissionResource;
use App\Http\Resources\Api\V1\System\PermissionCollection;
use App\Services\System\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends BaseApiController
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Display a listing of permissions
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'guard_name', 'category', 'has_roles', 
            'sort_by', 'sort_order'
        ]);

        $perPage = min($request->input('per_page', 50), 100);
        
        $permissions = $this->permissionService->getFilteredPermissions($filters, $perPage);

        return $this->successResponse(
            new PermissionCollection($permissions),
            'Permissions retrieved successfully',
            []
        );
    }

    /**
     * Store a newly created permission
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->permissionService->createPermission(
            $request->validated(), 
            $request->user()
        );

        return $this->successResponse(
            new PermissionResource($permission),
            'Permission created successfully',
            [],
            201
        );
    }

    /**
     * Display the specified permission
     */
    public function show(Permission $permission): JsonResponse
    {
        $permissionWithDetails = $this->permissionService->getPermissionWithDetails($permission);

        return $this->successResponse(
            new PermissionResource($permissionWithDetails),
            'Permission retrieved successfully',
            []
        );
    }

    /**
     * Update the specified permission
     */
    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $updatedPermission = $this->permissionService->updatePermission(
            $permission, 
            $request->validated(), 
            $request->user()
        );

        return $this->successResponse(
            new PermissionResource($updatedPermission),
            'Permission updated successfully',
            []
        );
    }

    /**
     * Remove the specified permission from storage
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $this->permissionService->deletePermission($permission, request()->user());

        return $this->successResponse(
            null,
            'Permission deleted successfully',
            []
        );
    }

    /**
     * Get permissions grouped by modules/categories
     */
    public function modules(): JsonResponse
    {
        $modules = $this->permissionService->getPermissionsByModules();

        return $this->successResponse(
            $modules,
            'Permission modules retrieved successfully',
            []
        );
    }

    /**
     * Get permission statistics for dashboard
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->permissionService->getPermissionStatistics();

        return $this->successResponse(
            $stats,
            'Permission statistics retrieved successfully',
            []
        );
    }

    /**
     * Get roles assigned to a specific permission
     */
    public function roles(Permission $permission): JsonResponse
    {
        $roles = $this->permissionService->getPermissionRoles($permission);

        return $this->successResponse(
            $roles,
            'Permission roles retrieved successfully',
            []
        );
    }

    /**
     * Search permissions with basic info only
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $limit = min($request->input('limit', 20), 50);
        
        if (strlen($query) < 2) {
            return $this->successResponse(
                [],
                'Search query must be at least 2 characters',
                []
            );
        }

        $permissions = $this->permissionService->searchPermissions($query, $limit);

        return $this->successResponse(
            $permissions,
            'Permissions search completed',
            []
        );
    }

    /**
     * Get permissions by category
     */
    public function byCategory(Request $request, string $category): JsonResponse
    {
        $permissions = $this->permissionService->getPermissionsByCategory($category);

        return $this->successResponse(
            $permissions,
            "Permissions for category '{$category}' retrieved successfully",
            []
        );
    }

    /**
     * Sync permissions with available system features
     */
    public function sync(): JsonResponse
    {
        $result = $this->permissionService->syncSystemPermissions(request()->user());

        return $this->successResponse(
            $result,
            'System permissions synchronized successfully',
            []
        );
    }

    /**
     * Get permission usage analysis
     */
    public function usage(Permission $permission): JsonResponse
    {
        $usage = $this->permissionService->getPermissionUsageAnalysis($permission);

        return $this->successResponse(
            $usage,
            'Permission usage analysis retrieved successfully',
            []
        );
    }

    /**
     * Bulk assign permissions to multiple roles
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        $result = $this->permissionService->bulkAssignPermissions(
            $validated['permissions'],
            $validated['roles'],
            $request->user()
        );

        return $this->successResponse(
            $result,
            'Permissions bulk assigned successfully',
            []
        );
    }
}