<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\EquipmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentCategoryController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = EquipmentCategory::withCount(['equipmentTypes', 'equipment']);

        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->active();
        }

        $categories = $query->orderBy('name')->get();

        return $this->successResponse($categories);
    }

    public function show($id): JsonResponse
    {
        $category = EquipmentCategory::withCount(['equipmentTypes', 'equipment'])
            ->findOrFail($id);

        return $this->successResponse($category);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:equipment_categories',
            'code' => 'required|string|max:10|unique:equipment_categories',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:255',
        ]);

        $category = EquipmentCategory::create(array_merge(
            $request->all(),
            ['is_active' => true]
        ));

        return $this->successResponse($category, 'Equipment category created successfully', [], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $category = EquipmentCategory::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255|unique:equipment_categories,name,' . $id,
            'code' => 'sometimes|string|max:10|unique:equipment_categories,code,' . $id,
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $category->update($request->all());

        return $this->successResponse($category, 'Equipment category updated successfully');
    }

    public function destroy($id): JsonResponse
    {
        $category = EquipmentCategory::findOrFail($id);

        if ($category->equipment()->count() > 0) {
            return $this->errorResponse('Cannot delete category that has equipment assigned to it', 422);
        }

        $category->delete();

        return $this->successResponse(null, 'Equipment category deleted successfully');
    }
}
