<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\System\StoreRoleRequest;
use App\Http\Requests\Api\V1\System\UpdateRoleRequest;
use App\Http\Requests\Api\V1\System\UpdateRolePermissionsRequest;
use App\Http\Resources\Api\V1\System\RoleResource;
use App\Http\Resources\Api\V1\System\RoleCollection;
use App\Services\System\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends BaseApiController
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    /**
     * Display a listing of roles
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'guard_name', 'has_users', 'has_permissions', 
            'sort_by', 'sort_order'
        ]);

        $perPage = min($request->input('per_page', 15), 100);
        
        $roles = $this->roleService->getFilteredRoles($filters, $perPage);

        return $this->successResponse(
            new RoleCollection($roles),
            'Roles retrieved successfully',
            []
        );
    }

    /**
     * Store a newly created role
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->createRole(
            $request->validated(), 
            $request->user()
        );

        return $this->successResponse(
            new RoleResource($role),
            'Role created successfully',
            [],
            201
        );
    }

    /**
     * Display the specified role
     */
    public function show(Role $role): JsonResponse
    {
        $roleWithDetails = $this->roleService->getRoleWithDetails($role);

        return $this->successResponse(
            new RoleResource($roleWithDetails),
            'Role retrieved successfully',
            []
        );
    }

    /**
     * Update the specified role
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $updatedRole = $this->roleService->updateRole(
            $role, 
            $request->validated(), 
            $request->user()
        );

        return $this->successResponse(
            new RoleResource($updatedRole),
            'Role updated successfully',
            []
        );
    }

    /**
     * Remove the specified role from storage
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->deleteRole($role, request()->user());

        return $this->successResponse(
            null,
            'Role deleted successfully',
            []
        );
    }

    /**
     * Update role permissions
     */
    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $updatedRole = $this->roleService->updateRolePermissions(
            $role, 
            $request->validated()['permissions'], 
            $request->user()
        );

        return $this->successResponse(
            new RoleResource($updatedRole),
            'Role permissions updated successfully',
            []
        );
    }

    /**
     * Get role statistics for dashboard
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->roleService->getRoleStatistics();

        return $this->successResponse(
            $stats,
            'Role statistics retrieved successfully',
            []
        );
    }

    /**
     * Get users assigned to a specific role
     */
    public function users(Role $role): JsonResponse
    {
        $users = $this->roleService->getRoleUsers($role);

        return $this->successResponse(
            $users,
            'Role users retrieved successfully',
            []
        );
    }

    /**
     * Search roles with basic info only
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        $limit = min($request->input('limit', 10), 50);
        
        if (strlen($query) < 2) {
            return $this->successResponse(
                [],
                'Search query must be at least 2 characters',
                []
            );
        }

        $roles = $this->roleService->searchRoles($query, $limit);

        return $this->successResponse(
            $roles,
            'Roles search completed',
            []
        );
    }

    /**
     * Get role hierarchy and structure
     */
    public function hierarchy(): JsonResponse
    {
        $hierarchy = $this->roleService->getRoleHierarchy();

        return $this->successResponse(
            $hierarchy,
            'Role hierarchy retrieved successfully',
            []
        );
    }

    /**
     * Clone a role with all its permissions
     */
    public function clone(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'display_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $newRole = $this->roleService->cloneRole($role, $validated, $request->user());

        return $this->successResponse(
            new RoleResource($newRole),
            'Role cloned successfully',
            [],
            201
        );
    }
}