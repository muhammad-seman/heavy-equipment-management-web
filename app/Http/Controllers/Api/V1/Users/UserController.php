<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Users\StoreUserRequest;
use App\Http\Requests\Api\V1\Users\UpdateUserRequest;
use App\Http\Requests\Api\V1\Users\UpdateUserRolesRequest;
use App\Http\Resources\Api\V1\Users\UserResource;
use App\Http\Resources\Api\V1\Users\UserCollection;
use App\Models\User;
use App\Services\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends BaseApiController
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'role', 'department', 'certification_level', 
            'is_active', 'has_certification', 'sort_by', 'sort_order'
        ]);

        $perPage = min($request->input('per_page', 15), 100);
        
        $users = $this->userService->getFilteredUsers($filters, $perPage);

        return $this->successResponse(
            new UserCollection($users),
            'Users retrieved successfully',
            []
        );
    }

    /**
     * Display the specified user
     */
    public function show(User $user): JsonResponse
    {
        $userWithDetails = $this->userService->getUserWithDetails($user);

        return $this->successResponse(
            new UserResource($userWithDetails),
            'User retrieved successfully',
            []
        );
    }

    /**
     * Store a newly created user
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->createUser(
            $request->validated(), 
            $request->user()
        );

        return $this->successResponse(
            new UserResource($user),
            'User created successfully',
            [],
            201
        );
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->userService->updateUser(
            $user, 
            $request->validated(), 
            $request->user()
        );

        return $this->successResponse(
            new UserResource($updatedUser),
            'User updated successfully',
            []
        );
    }

    /**
     * Remove the specified user from storage (soft delete)
     */
    public function destroy(User $user): JsonResponse
    {
        $this->userService->deleteUser($user, request()->user());

        return $this->successResponse(
            null,
            'User deleted successfully',
            []
        );
    }

    /**
     * Get operators list (specialized endpoint)
     */
    public function operators(Request $request): JsonResponse
    {
        $operators = User::role('operator')
            ->where('is_active', true)
            ->with(['assignedEquipment'])
            ->withCount('assignedEquipment')
            ->get();

        return $this->successResponse(
            new UserCollection(collect($operators)),
            'Operators retrieved successfully',
            []
        );
    }

    /**
     * Restore a soft-deleted user
     */
    public function restore(int $userId): JsonResponse
    {
        $user = $this->userService->restoreUser($userId, request()->user());

        return $this->successResponse(
            new UserResource($user),
            'User restored successfully',
            []
        );
    }

    /**
     * Activate or deactivate a user
     */
    public function activate(User $user): JsonResponse
    {
        $updatedUser = $this->userService->toggleUserStatus($user, request()->user());

        $status = $updatedUser->is_active ? 'activated' : 'deactivated';
        
        return $this->successResponse(
            new UserResource($updatedUser),
            "User {$status} successfully",
            []
        );
    }

    /**
     * Update user roles
     */
    public function updateRoles(UpdateUserRolesRequest $request, User $user): JsonResponse
    {
        $updatedUser = $this->userService->updateUserRoles(
            $user, 
            $request->validated()['roles'], 
            $request->user()
        );

        return $this->successResponse(
            new UserResource($updatedUser),
            'User roles updated successfully',
            []
        );
    }

    /**
     * Get user activity summary
     */
    public function activity(User $user, Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        
        $activity = $this->userService->getUserActivitySummary($user, $days);

        return $this->successResponse(
            $activity,
            'User activity retrieved successfully',
            []
        );
    }

    /**
     * Get user's assigned equipment
     */
    public function assignedEquipment(User $user): JsonResponse
    {
        $equipment = $this->userService->getUserEquipment($user);

        return $this->successResponse(
            $equipment,
            'User assigned equipment retrieved successfully',
            []
        );
    }

    /**
     * Get user profile for authenticated user
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->userService->getUserProfile($user);

        return $this->successResponse(
            new UserResource($profile),
            'User profile retrieved successfully',
            []
        );
    }

    /**
     * Update user profile for authenticated user
     */
    public function updateProfile(Request $request): JsonResponse
    {
        // Basic validation for profile update
        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'emergency_contact_name' => 'sometimes|nullable|string|max:255',
            'emergency_contact_phone' => 'sometimes|nullable|string|max:20',
        ]);

        $user = $request->user();
        $updatedUser = $this->userService->updateUserProfile($user, $validated);

        return $this->successResponse(
            new UserResource($updatedUser),
            'Profile updated successfully',
            []
        );
    }

    /**
     * Get users statistics for dashboard
     */
    public function statistics(): JsonResponse
    {
        $stats = $this->userService->getUserStatistics();

        return $this->successResponse(
            $stats,
            'User statistics retrieved successfully',
            []
        );
    }

    /**
     * Search users with basic info only
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

        $users = $this->userService->searchUsers($query, $limit);

        return $this->successResponse(
            $users,
            'Users search completed',
            []
        );
    }
}
