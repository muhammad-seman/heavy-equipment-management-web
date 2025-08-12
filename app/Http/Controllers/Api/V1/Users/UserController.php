<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('roles')->withCount('assignedEquipment');

        if ($request->has('search')) {
            $query->search($request->input('search'));
        }

        if ($request->has('department')) {
            $query->byDepartment($request->input('department'));
        }

        if ($request->has('certification_level')) {
            $query->byCertificationLevel($request->input('certification_level'));
        }

        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->active();
        }

        if ($request->has('role')) {
            $query->role($request->input('role'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return $this->successResponse($users);
    }

    public function show($id): JsonResponse
    {
        $user = User::with(['roles', 'assignedEquipment'])
            ->withCount(['assignedEquipment', 'operatingSessions'])
            ->findOrFail($id);

        return $this->successResponse([
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'employee_id' => $user->employee_id,
            'department' => $user->department,
            'position' => $user->position,
            'certification_level' => $user->certification_level,
            'certification_expiry' => $user->certification_expiry,
            'has_active_certification' => $user->hasActiveCertification(),
            'is_certification_expiring_soon' => $user->isCertificationExpiringSoon(),
            'is_active' => $user->is_active,
            'last_login' => $user->last_login,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'assigned_equipment_count' => $user->assigned_equipment_count,
            'operating_sessions_count' => $user->operating_sessions_count,
            'assigned_equipment' => $user->assignedEquipment,
            'created_at' => $user->created_at,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:20',
            'employee_id' => 'required|string|max:50|unique:users',
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'certification_level' => 'required|in:basic,intermediate,advanced,expert',
            'certification_expiry' => 'nullable|date|after:today',
            'password' => 'required|string|min:8',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'employee_id' => $request->employee_id,
            'department' => $request->department,
            'position' => $request->position,
            'certification_level' => $request->certification_level,
            'certification_expiry' => $request->certification_expiry,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        if ($request->has('roles')) {
            $user->assignRole($request->roles);
        }

        return $this->successResponse($user->load('roles'), 'User created successfully', [], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'employee_id' => 'sometimes|string|max:50|unique:users,employee_id,' . $id,
            'department' => 'sometimes|string|max:255',
            'position' => 'sometimes|string|max:255',
            'certification_level' => 'sometimes|in:basic,intermediate,advanced,expert',
            'certification_expiry' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
            'roles' => 'sometimes|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user->update($request->except(['roles']));

        if ($request->has('roles')) {
            $user->syncRoles($request->roles);
        }

        return $this->successResponse($user->load('roles'), 'User updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);

        if ($user->assignedEquipment()->count() > 0) {
            return $this->errorResponse('Cannot delete user who has equipment assigned', 422);
        }

        $user->delete();

        return $this->successResponse(null, 'User deleted successfully');
    }

    public function assignRole(Request $request, $id): JsonResponse
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::findOrFail($id);
        $user->assignRole($request->role);

        return $this->successResponse($user->load('roles'), 'Role assigned successfully');
    }

    public function removeRole(Request $request, $id): JsonResponse
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::findOrFail($id);
        $user->removeRole($request->role);

        return $this->successResponse($user->load('roles'), 'Role removed successfully');
    }

    public function activate($id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        return $this->successResponse($user, 'User activated successfully');
    }

    public function deactivate($id): JsonResponse
    {
        $user = User::findOrFail($id);
        
        if ($user->assignedEquipment()->count() > 0) {
            return $this->errorResponse('Cannot deactivate user who has equipment assigned', 422);
        }

        $user->update(['is_active' => false]);

        return $this->successResponse($user, 'User deactivated successfully');
    }

    public function operators(Request $request): JsonResponse
    {
        $operators = User::role('operator')
            ->active()
            ->with(['assignedEquipment'])
            ->withCount('assignedEquipment')
            ->get();

        return $this->successResponse($operators);
    }

    public function resetPassword(Request $request, $id): JsonResponse
    {
        $request->validate([
            'new_password' => 'required|string|min:8',
        ]);

        $user = User::findOrFail($id);
        $user->update(['password' => Hash::make($request->new_password)]);

        return $this->successResponse(null, 'Password reset successfully');
    }
}
