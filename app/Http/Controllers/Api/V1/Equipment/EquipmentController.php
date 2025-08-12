<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Equipment;
use App\Models\EquipmentStatusLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Equipment::with(['category', 'type', 'manufacturer', 'assignedUser']);

        if ($request->has('filters')) {
            $filters = $request->input('filters');
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['category_id'])) {
                $query->where('equipment_category_id', $filters['category_id']);
            }
            
            if (isset($filters['type_id'])) {
                $query->where('equipment_type_id', $filters['type_id']);
            }
            
            if (isset($filters['manufacturer_id'])) {
                $query->where('manufacturer_id', $filters['manufacturer_id']);
            }
            
            if (isset($filters['assigned_user_id'])) {
                $query->where('assigned_user_id', $filters['assigned_user_id']);
            }
            
            if (isset($filters['search'])) {
                $search = $filters['search'];
                $query->where(function($q) use ($search) {
                    $q->where('asset_number', 'like', "%{$search}%")
                      ->orWhere('serial_number', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%");
                });
            }
        }

        if ($request->has('sort')) {
            $sort = $request->input('sort');
            $direction = $request->input('direction', 'asc');
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('asset_number');
        }

        $perPage = min($request->input('per_page', 15), 100);
        $equipment = $query->paginate($perPage);

        return $this->successResponse($equipment);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $equipment = Equipment::with([
            'category', 
            'type', 
            'manufacturer', 
            'assignedUser',
            'documents',
            'statusLog' => function($query) {
                $query->latest()->limit(10);
            },
            'operatingSessions' => function($query) {
                $query->latest()->limit(5);
            }
        ])->findOrFail($id);

        return $this->successResponse($equipment);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'equipment_category_id' => 'required|exists:equipment_categories,id',
            'equipment_type_id' => 'required|exists:equipment_types,id',
            'manufacturer_id' => 'required|exists:manufacturers,id',
            'asset_number' => 'required|string|max:255|unique:equipment',
            'serial_number' => 'required|string|max:255|unique:equipment',
            'model' => 'required|string|max:255',
            'year_manufactured' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'purchase_date' => 'required|date',
            'purchase_price' => 'required|numeric|min:0',
            'operating_weight' => 'nullable|numeric|min:0',
            'engine_power' => 'nullable|numeric|min:0',
            'fuel_capacity' => 'nullable|numeric|min:0',
            'bucket_capacity' => 'nullable|numeric|min:0',
            'ownership_type' => 'required|in:owned,leased,rented',
            'lease_start_date' => 'nullable|date|required_if:ownership_type,leased',
            'lease_end_date' => 'nullable|date|after:lease_start_date|required_if:ownership_type,leased',
            'lease_monthly_cost' => 'nullable|numeric|min:0|required_if:ownership_type,leased',
            'current_site' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $equipment = Equipment::create(array_merge(
            $request->all(),
            [
                'status' => 'active',
                'condition' => 'good',
                'current_value' => $request->purchase_price,
            ]
        ));

        EquipmentStatusLog::create([
            'equipment_id' => $equipment->id,
            'previous_status' => null,
            'new_status' => 'active',
            'reason' => 'Initial registration',
            'changed_by' => $request->user()->id,
            'changed_at' => now(),
        ]);

        return $this->successResponse($equipment->load(['category', 'type', 'manufacturer']), 'Equipment created successfully', [], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $equipment = Equipment::findOrFail($id);
        
        $request->validate([
            'equipment_category_id' => 'sometimes|exists:equipment_categories,id',
            'equipment_type_id' => 'sometimes|exists:equipment_types,id',
            'manufacturer_id' => 'sometimes|exists:manufacturers,id',
            'asset_number' => 'sometimes|string|max:255|unique:equipment,asset_number,' . $id,
            'serial_number' => 'sometimes|string|max:255|unique:equipment,serial_number,' . $id,
            'model' => 'sometimes|string|max:255',
            'year_manufactured' => 'sometimes|integer|min:1900|max:' . (date('Y') + 1),
            'purchase_date' => 'sometimes|date',
            'purchase_price' => 'sometimes|numeric|min:0',
            'current_value' => 'sometimes|numeric|min:0',
            'operating_weight' => 'nullable|numeric|min:0',
            'engine_power' => 'nullable|numeric|min:0',
            'fuel_capacity' => 'nullable|numeric|min:0',
            'bucket_capacity' => 'nullable|numeric|min:0',
            'ownership_type' => 'sometimes|in:owned,leased,rented',
            'lease_start_date' => 'nullable|date',
            'lease_end_date' => 'nullable|date|after:lease_start_date',
            'lease_monthly_cost' => 'nullable|numeric|min:0',
            'current_site' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $equipment->update($request->all());

        return $this->successResponse($equipment->load(['category', 'type', 'manufacturer']), 'Equipment updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $equipment = Equipment::findOrFail($id);
        
        if ($equipment->status === 'active' && $equipment->assigned_user_id) {
            return $this->errorResponse('Cannot delete equipment that is currently assigned to a user', 422);
        }

        $equipment->delete();

        return $this->successResponse(null, 'Equipment deleted successfully');
    }

    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,maintenance,repair,retired,out_of_service',
            'reason' => 'required|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        $equipment = Equipment::findOrFail($id);
        $previousStatus = $equipment->status;

        DB::transaction(function() use ($equipment, $request, $previousStatus) {
            $equipment->update(['status' => $request->status]);

            EquipmentStatusLog::create([
                'equipment_id' => $equipment->id,
                'previous_status' => $previousStatus,
                'new_status' => $request->status,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
        });

        return $this->successResponse($equipment, 'Equipment status updated successfully');
    }

    public function updateLocation(Request $request, $id): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
        ]);

        $equipment = Equipment::findOrFail($id);
        
        $equipment->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'last_location_update' => now(),
        ]);

        return $this->successResponse($equipment, 'Equipment location updated successfully');
    }

    public function assign(Request $request, $id): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'site' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $equipment = Equipment::findOrFail($id);
        
        if ($equipment->status !== 'active') {
            return $this->errorResponse('Only active equipment can be assigned', 422);
        }

        if ($equipment->assigned_user_id) {
            return $this->errorResponse('Equipment is already assigned to another user', 422);
        }

        $equipment->update([
            'assigned_user_id' => $request->user_id,
            'current_site' => $request->site,
        ]);

        return $this->successResponse($equipment->load('assignedUser'), 'Equipment assigned successfully');
    }

    public function unassign(Request $request, $id): JsonResponse
    {
        $equipment = Equipment::findOrFail($id);
        
        if (!$equipment->assigned_user_id) {
            return $this->errorResponse('Equipment is not currently assigned', 422);
        }

        $equipment->update([
            'assigned_user_id' => null,
        ]);

        return $this->successResponse($equipment, 'Equipment unassigned successfully');
    }

    public function statusHistory(Request $request, $id): JsonResponse
    {
        $equipment = Equipment::findOrFail($id);
        
        $history = EquipmentStatusLog::where('equipment_id', $id)
            ->with('changedBy')
            ->latest('changed_at')
            ->paginate($request->input('per_page', 20));

        return $this->successResponse($history);
    }

    public function assigned(Request $request): JsonResponse
    {
        $query = Equipment::with(['category', 'type', 'manufacturer', 'assignedUser'])
            ->whereNotNull('assigned_user_id');

        if ($request->has('user_id')) {
            $query->where('assigned_user_id', $request->input('user_id'));
        }

        $equipment = $query->get();

        return $this->successResponse($equipment);
    }
}
