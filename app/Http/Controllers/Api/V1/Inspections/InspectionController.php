<?php

namespace App\Http\Controllers\Api\V1\Inspections;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Inspections\StoreInspectionRequest;
use App\Http\Requests\Api\V1\Inspections\UpdateInspectionRequest;
use App\Http\Resources\Api\V1\Inspections\InspectionResource;
use App\Http\Resources\Api\V1\Inspections\InspectionCollection;
use App\Models\Inspection;
use App\Services\Inspections\InspectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionController extends BaseApiController
{
    protected InspectionService $inspectionService;

    public function __construct(InspectionService $inspectionService)
    {
        $this->inspectionService = $inspectionService;
    }

    /**
     * Display a listing of inspections
     */
    public function index(Request $request): JsonResponse
    {
        $inspections = $this->inspectionService->getAllInspections($request->all());
        
        return $this->successResponse(
            new InspectionCollection($inspections),
            'Inspections retrieved successfully',
            []
        );
    }

    /**
     * Store a newly created inspection
     */
    public function store(StoreInspectionRequest $request): JsonResponse
    {
        $inspection = $this->inspectionService->createInspection($request->validated());
        
        return $this->successResponse(
            new InspectionResource($inspection),
            'Inspection created successfully',
            [],
            201
        );
    }

    /**
     * Display the specified inspection
     */
    public function show(Inspection $inspection): JsonResponse
    {
        $inspection = $this->inspectionService->getInspectionById($inspection->id);
        
        return $this->successResponse(
            new InspectionResource($inspection),
            'Inspection retrieved successfully',
            []
        );
    }

    /**
     * Update the specified inspection
     */
    public function update(UpdateInspectionRequest $request, Inspection $inspection): JsonResponse
    {
        $inspection = $this->inspectionService->updateInspection($inspection->id, $request->validated());
        
        return $this->successResponse(
            new InspectionResource($inspection),
            'Inspection updated successfully',
            []
        );
    }

    /**
     * Remove the specified inspection from storage
     */
    public function destroy(Inspection $inspection): JsonResponse
    {
        $this->inspectionService->deleteInspection($inspection->id);
        
        return $this->successResponse(
            null,
            'Inspection deleted successfully',
            []
        );
    }

    /**
     * Restore a soft-deleted inspection
     */
    public function restore(int $inspectionId): JsonResponse
    {
        $inspection = $this->inspectionService->restoreInspection($inspectionId);
        
        return $this->successResponse(
            new InspectionResource($inspection),
            'Inspection restored successfully',
            []
        );
    }

    /**
     * Start an inspection (change status to in_progress)
     */
    public function start(Inspection $inspection): JsonResponse
    {
        $inspection = $this->inspectionService->startInspection($inspection->id);
        
        return $this->successResponse(
            new InspectionResource($inspection),
            'Inspection started successfully',
            []
        );
    }

    /**
     * Complete an inspection
     */
    public function complete(Inspection $inspection, Request $request): JsonResponse
    {
        $inspection = $this->inspectionService->completeInspection(
            $inspection->id, 
            $request->all()
        );
        
        return $this->successResponse(
            new InspectionResource($inspection),
            'Inspection completed successfully',
            []
        );
    }

    /**
     * Cancel an inspection
     */
    public function cancel(Inspection $inspection): JsonResponse
    {
        $inspection = $this->inspectionService->cancelInspection($inspection->id);
        
        return $this->successResponse(
            new InspectionResource($inspection),
            'Inspection cancelled successfully',
            []
        );
    }

    /**
     * Get inspections by equipment
     */
    public function byEquipment(int $equipmentId, Request $request): JsonResponse
    {
        $inspections = $this->inspectionService->getInspectionsByEquipment($equipmentId, $request->all());
        
        return $this->successResponse(
            new InspectionCollection($inspections),
            'Equipment inspections retrieved successfully',
            []
        );
    }

    /**
     * Get inspections by inspector
     */
    public function byInspector(int $inspectorId, Request $request): JsonResponse
    {
        $inspections = $this->inspectionService->getInspectionsByInspector($inspectorId, $request->all());
        
        return $this->successResponse(
            new InspectionCollection($inspections),
            'Inspector inspections retrieved successfully',
            []
        );
    }

    /**
     * Get today's inspections
     */
    public function today(Request $request): JsonResponse
    {
        $inspections = $this->inspectionService->getTodaysInspections($request->all());
        
        return $this->successResponse(
            new InspectionCollection($inspections),
            'Today\'s inspections retrieved successfully',
            []
        );
    }

    /**
     * Get this week's inspections
     */
    public function thisWeek(Request $request): JsonResponse
    {
        $inspections = $this->inspectionService->getThisWeeksInspections($request->all());
        
        return $this->successResponse(
            new InspectionCollection($inspections),
            'This week\'s inspections retrieved successfully',
            []
        );
    }

    /**
     * Get overdue inspections
     */
    public function overdue(Request $request): JsonResponse
    {
        $inspections = $this->inspectionService->getOverdueInspections($request->all());
        
        return $this->successResponse(
            new InspectionCollection($inspections),
            'Overdue inspections retrieved successfully',
            []
        );
    }

    /**
     * Get inspections requiring action
     */
    public function requiresAction(Request $request): JsonResponse
    {
        $inspections = $this->inspectionService->getInspectionsRequiringAction($request->all());
        
        return $this->successResponse(
            new InspectionCollection($inspections),
            'Inspections requiring action retrieved successfully',
            []
        );
    }

    /**
     * Get inspection statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $statistics = $this->inspectionService->getInspectionStatistics($request->all());
        
        return $this->successResponse(
            $statistics,
            'Inspection statistics retrieved successfully',
            []
        );
    }

    /**
     * Search inspections
     */
    public function search(Request $request): JsonResponse
    {
        $inspections = $this->inspectionService->searchInspections($request->all());
        
        return $this->successResponse(
            new InspectionCollection($inspections),
            'Inspection search results retrieved successfully',
            []
        );
    }

    /**
     * Get inspection summary for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->inspectionService->getInspectionSummary($request->all());
        
        return $this->successResponse(
            $summary,
            'Inspection summary retrieved successfully',
            []
        );
    }

    /**
     * Duplicate an inspection
     */
    public function duplicate(Inspection $inspection): JsonResponse
    {
        $newInspection = $this->inspectionService->duplicateInspection($inspection->id);
        
        return $this->successResponse(
            new InspectionResource($newInspection),
            'Inspection duplicated successfully',
            []
        );
    }

    /**
     * Generate inspection from template
     */
    public function generateFromTemplate(Request $request): JsonResponse
    {
        $inspection = $this->inspectionService->generateInspectionFromTemplate($request->all());
        
        return $this->successResponse(
            new InspectionResource($inspection),
            'Inspection generated from template successfully',
            []
        );
    }

    /**
     * Get inspection types
     */
    public function types(): JsonResponse
    {
        $types = Inspection::getInspectionTypes();
        
        return $this->successResponse(
            $types,
            'Inspection types retrieved successfully',
            []
        );
    }

    /**
     * Get inspection statuses
     */
    public function statuses(): JsonResponse
    {
        $statuses = Inspection::getInspectionStatuses();
        
        return $this->successResponse(
            $statuses,
            'Inspection statuses retrieved successfully',
            []
        );
    }

    /**
     * Get inspection results
     */
    public function results(): JsonResponse
    {
        $results = Inspection::getInspectionResults();
        
        return $this->successResponse(
            $results,
            'Inspection results retrieved successfully',
            []
        );
    }
}