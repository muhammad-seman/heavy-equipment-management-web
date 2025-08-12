<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Equipment\StoreManufacturerRequest;
use App\Http\Requests\Api\V1\Equipment\UpdateManufacturerRequest;
use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManufacturerController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Manufacturer::query();

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhere('country', 'like', '%' . $search . '%');
                });
            }

            // Filter by active status
            if ($request->has('active') && $request->active !== null) {
                $query->where('is_active', $request->boolean('active'));
            }

            // Filter by country
            if ($request->has('country') && !empty($request->country)) {
                $query->where('country', $request->country);
            }

            // Include counts
            $query->withCount(['equipmentTypes', 'equipment']);

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            
            $allowedSorts = ['name', 'code', 'country', 'created_at', 'equipment_types_count', 'equipment_count'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Pagination
            $perPage = min($request->input('per_page', 15), 100);
            
            if ($request->has('paginate') && $request->paginate === 'false') {
                $manufacturers = $query->get();
                return $this->successResponse($manufacturers);
            }

            $manufacturers = $query->paginate($perPage);

            return $this->successResponse($manufacturers);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve manufacturers', 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function store(StoreManufacturerRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();

            DB::beginTransaction();

            $manufacturer = Manufacturer::create($validatedData);
            $manufacturer->loadCount(['equipmentTypes', 'equipment']);

            DB::commit();

            return $this->successResponse(
                $manufacturer,
                'Manufacturer created successfully',
                [],
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
                'Failed to create manufacturer',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $manufacturer = Manufacturer::with(['equipmentTypes', 'equipment'])
                ->withCount(['equipmentTypes', 'equipment'])
                ->findOrFail($id);

            // Include recent equipment if requested
            if ($request->boolean('include_recent_equipment')) {
                $manufacturer->load(['equipment' => function($query) {
                    $query->latest()->limit(10);
                }]);
            }

            return $this->successResponse($manufacturer);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Manufacturer not found', 404);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve manufacturer', 500, [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function update(UpdateManufacturerRequest $request, $id): JsonResponse
    {
        try {
            $manufacturer = Manufacturer::findOrFail($id);

            $validatedData = $request->validated();

            DB::beginTransaction();

            $manufacturer->update($validatedData);
            $manufacturer->loadCount(['equipmentTypes', 'equipment']);

            DB::commit();

            return $this->successResponse(
                $manufacturer,
                'Manufacturer updated successfully'
            );

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Manufacturer not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                ['errors' => $e->errors()]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to update manufacturer',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $manufacturer = Manufacturer::findOrFail($id);

            // Check if manufacturer has equipment
            $equipmentCount = $manufacturer->equipment()->count();
            if ($equipmentCount > 0) {
                return $this->errorResponse(
                    "Cannot delete manufacturer. It has {$equipmentCount} equipment(s) associated with it.",
                    400,
                    ['equipment_count' => $equipmentCount]
                );
            }

            DB::beginTransaction();

            $manufacturer->delete();

            DB::commit();

            return $this->successResponse(null, 'Manufacturer deleted successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Manufacturer not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to delete manufacturer',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    public function toggleStatus($id): JsonResponse
    {
        try {
            $manufacturer = Manufacturer::findOrFail($id);

            DB::beginTransaction();

            $manufacturer->update([
                'is_active' => !$manufacturer->is_active
            ]);

            DB::commit();

            return $this->successResponse([
                'id' => $manufacturer->id,
                'is_active' => $manufacturer->is_active
            ], 'Manufacturer status updated successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Manufacturer not found', 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to update manufacturer status',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }
}
