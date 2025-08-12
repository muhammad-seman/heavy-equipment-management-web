<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Equipment\StoreEquipmentTypeRequest;
use App\Http\Requests\Api\V1\Equipment\UpdateEquipmentTypeRequest;
use App\Models\EquipmentType;
use App\Models\EquipmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipmentTypeController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = EquipmentType::with(['category'])->withCount(['equipment']);

            // Filter by category
            if ($request->has('category_id') && !empty($request->category_id)) {
                $query->where('category_id', $request->category_id);
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhereHas('category', function ($categoryQuery) use ($search) {
                          $categoryQuery->where('name', 'like', '%' . $search . '%');
                      });
                });
            }

            // Filter by active status
            if ($request->has('active') && $request->active !== null) {
                $query->where('is_active', $request->boolean('active'));
            }

            // Filter by specifications
            if ($request->has('filters')) {
                $filters = $request->input('filters');
                
                if (isset($filters['operating_weight_min'])) {
                    $query->where('operating_weight_max', '>=', $filters['operating_weight_min']);
                }
                
                if (isset($filters['operating_weight_max'])) {
                    $query->where('operating_weight_min', '<=', $filters['operating_weight_max']);
                }
                
                if (isset($filters['engine_power_min'])) {
                    $query->where('engine_power_max', '>=', $filters['engine_power_min']);
                }
                
                if (isset($filters['engine_power_max'])) {
                    $query->where('engine_power_min', '<=', $filters['engine_power_max']);
                }
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            
            $allowedSorts = ['name', 'code', 'created_at', 'equipment_count', 'operating_weight_min', 'engine_power_min'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination
            $perPage = min($request->input('per_page', 15), 100);
            
            if ($request->has('paginate') && $request->paginate === 'false') {
                $equipmentTypes = $query->get();
                return $this->successResponse($equipmentTypes);
            }

            $equipmentTypes = $query->paginate($perPage);

            return $this->successResponse($equipmentTypes);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve equipment types', 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function store(StoreEquipmentTypeRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            DB::beginTransaction();

            $equipmentType = EquipmentType::create($validatedData);
            $equipmentType->load(['category'])->loadCount(['equipment']);

            DB::commit();

            return $this->successResponse(
                $equipmentType,
                'Equipment type created successfully',
                201
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to create equipment type',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $equipmentType = EquipmentType::with(['category', 'equipment'])
                ->withCount(['equipment'])
                ->findOrFail($id);

            // Include recent equipment if requested
            if ($request->boolean('include_recent_equipment')) {
                $equipmentType->load(['equipment' => function($query) {
                    $query->latest()->limit(10);
                }]);
            }

            return $this->successResponse($equipmentType);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Equipment type not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve equipment type', 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function update(UpdateEquipmentTypeRequest $request, $id): JsonResponse
    {
        try {
            $equipmentType = EquipmentType::findOrFail($id);

            $validatedData = $request->validate([
                'category_id' => 'required|exists:equipment_categories,id',
                'name' => 'required|string|max:255',
                'code' => ['required', 'string', 'max:20', Rule::unique('equipment_types', 'code')->ignore($equipmentType->id)],
                'description' => 'nullable|string|max:1000',
                'specifications' => 'nullable|array',
                'operating_weight_min' => 'nullable|numeric|min:0',
                'operating_weight_max' => 'nullable|numeric|min:0|gte:operating_weight_min',
                'engine_power_min' => 'nullable|numeric|min:0',
                'engine_power_max' => 'nullable|numeric|min:0|gte:engine_power_min',
                'bucket_capacity_min' => 'nullable|numeric|min:0',
                'bucket_capacity_max' => 'nullable|numeric|min:0|gte:bucket_capacity_min',
                'is_active' => 'boolean',
            ]);

            $validatedData['code'] = strtoupper($validatedData['code']);

            DB::beginTransaction();

            $equipmentType->update($validatedData);
            $equipmentType->load(['category'])->loadCount(['equipment']);

            DB::commit();

            return $this->successResponse(
                $equipmentType,
                'Equipment type updated successfully'
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Equipment type not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to update equipment type',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $equipmentType = EquipmentType::findOrFail($id);

            // Check if equipment type has equipment
            $equipmentCount = $equipmentType->equipment()->count();
            if ($equipmentCount > 0) {
                return $this->errorResponse(
                    "Cannot delete equipment type. It has {$equipmentCount} equipment(s) associated with it.",
                    400,
                    ['equipment_count' => $equipmentCount]
                );
            }

            DB::beginTransaction();

            $equipmentType->delete();

            DB::commit();

            return $this->successResponse(null, 'Equipment type deleted successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Equipment type not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to delete equipment type',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    public function toggleStatus($id): JsonResponse
    {
        try {
            $equipmentType = EquipmentType::findOrFail($id);

            DB::beginTransaction();

            $equipmentType->update([
                'is_active' => !$equipmentType->is_active
            ]);

            DB::commit();

            return $this->successResponse([
                'id' => $equipmentType->id,
                'is_active' => $equipmentType->is_active
            ], 'Equipment type status updated successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Equipment type not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to update equipment type status',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    public function byCategory($categoryId): JsonResponse
    {
        try {
            $category = EquipmentCategory::findOrFail($categoryId);
            
            $equipmentTypes = EquipmentType::where('category_id', $categoryId)
                ->where('is_active', true)
                ->withCount(['equipment'])
                ->orderBy('name')
                ->get();

            return $this->successResponse([
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'code' => $category->code,
                ],
                'equipment_types' => $equipmentTypes
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Category not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve equipment types by category', 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function equipmentByType(Request $request, $id): JsonResponse
    {
        try {
            $equipmentType = EquipmentType::with(['category'])->findOrFail($id);

            $query = $equipmentType->equipment()->with(['manufacturer']);

            // Filter by status
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('asset_number', 'like', '%' . $search . '%')
                      ->orWhere('serial_number', 'like', '%' . $search . '%')
                      ->orWhere('model', 'like', '%' . $search . '%');
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($request->input('per_page', 15), 100);
            $equipment = $query->paginate($perPage);

            return $this->successResponse([
                'equipment_type' => [
                    'id' => $equipmentType->id,
                    'name' => $equipmentType->name,
                    'code' => $equipmentType->code,
                    'category' => $equipmentType->category,
                ],
                'equipment' => $equipment
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Equipment type not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve equipment', 500, [
                'error' => $e->getMessage()
            ]);
        }
    }
}
