<?php

namespace App\Http\Controllers\Api\V1\Maintenance;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Api\V1\Maintenance\UpdateMaintenanceRequest;
use App\Http\Resources\Api\V1\Maintenance\MaintenanceResource;
use App\Http\Resources\Api\V1\Maintenance\MaintenanceCollection;
use App\Models\MaintenanceRecord;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends BaseApiController
{
    protected MaintenanceService $maintenanceService;

    public function __construct(MaintenanceService $maintenanceService)
    {
        $this->maintenanceService = $maintenanceService;
    }

    /**
     * Display a listing of maintenance records
     */
    public function index(Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->getAllMaintenanceRecords($request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'Maintenance records retrieved successfully',
            []
        );
    }

    /**
     * Store a newly created maintenance record
     */
    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->createMaintenanceRecord($request->validated());
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance record created successfully',
            [],
            201
        );
    }

    /**
     * Display the specified maintenance record
     */
    public function show(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->getMaintenanceRecordById($maintenanceRecord->id);
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance record retrieved successfully',
            []
        );
    }

    /**
     * Update the specified maintenance record
     */
    public function update(UpdateMaintenanceRequest $request, MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->updateMaintenanceRecord($maintenanceRecord->id, $request->validated());
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance record updated successfully',
            []
        );
    }

    /**
     * Remove the specified maintenance record from storage
     */
    public function destroy(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $this->maintenanceService->deleteMaintenanceRecord($maintenanceRecord->id);
        
        return $this->successResponse(
            null,
            'Maintenance record deleted successfully',
            []
        );
    }

    /**
     * Restore a soft-deleted maintenance record
     */
    public function restore(int $maintenanceRecordId): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->restoreMaintenanceRecord($maintenanceRecordId);
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance record restored successfully',
            []
        );
    }

    /**
     * Start a maintenance record (change status to in_progress)
     */
    public function start(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->startMaintenance($maintenanceRecord->id);
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance started successfully',
            []
        );
    }

    /**
     * Complete a maintenance record
     */
    public function complete(MaintenanceRecord $maintenanceRecord, Request $request): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->completeMaintenance(
            $maintenanceRecord->id, 
            $request->all()
        );
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance completed successfully',
            []
        );
    }

    /**
     * Put maintenance on hold
     */
    public function hold(MaintenanceRecord $maintenanceRecord, Request $request): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->holdMaintenance($maintenanceRecord->id, $request->get('reason'));
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance put on hold successfully',
            []
        );
    }

    /**
     * Resume maintenance from hold
     */
    public function resume(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->resumeMaintenance($maintenanceRecord->id);
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance resumed successfully',
            []
        );
    }

    /**
     * Cancel a maintenance record
     */
    public function cancel(MaintenanceRecord $maintenanceRecord, Request $request): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->cancelMaintenance($maintenanceRecord->id, $request->get('reason'));
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance cancelled successfully',
            []
        );
    }

    /**
     * Approve a maintenance record
     */
    public function approve(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->approveMaintenance($maintenanceRecord->id);
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance approved successfully',
            []
        );
    }

    /**
     * Reject a maintenance record
     */
    public function reject(MaintenanceRecord $maintenanceRecord, Request $request): JsonResponse
    {
        $maintenanceRecord = $this->maintenanceService->rejectMaintenance($maintenanceRecord->id, $request->get('reason'));
        
        return $this->successResponse(
            new MaintenanceResource($maintenanceRecord),
            'Maintenance rejected successfully',
            []
        );
    }

    /**
     * Get maintenance records by equipment
     */
    public function byEquipment(int $equipmentId, Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->getMaintenanceByEquipment($equipmentId, $request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'Equipment maintenance records retrieved successfully',
            []
        );
    }

    /**
     * Get maintenance records by technician
     */
    public function byTechnician(int $technicianId, Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->getMaintenanceByTechnician($technicianId, $request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'Technician maintenance records retrieved successfully',
            []
        );
    }

    /**
     * Get today's maintenance
     */
    public function today(Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->getTodaysMaintenance($request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'Today\'s maintenance records retrieved successfully',
            []
        );
    }

    /**
     * Get this week's maintenance
     */
    public function thisWeek(Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->getThisWeeksMaintenance($request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'This week\'s maintenance records retrieved successfully',
            []
        );
    }

    /**
     * Get overdue maintenance
     */
    public function overdue(Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->getOverdueMaintenance($request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'Overdue maintenance records retrieved successfully',
            []
        );
    }

    /**
     * Get emergency maintenance
     */
    public function emergency(Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->getEmergencyMaintenance($request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'Emergency maintenance records retrieved successfully',
            []
        );
    }

    /**
     * Get maintenance pending approval
     */
    public function pendingApproval(Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->getMaintenancePendingApproval($request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'Maintenance records pending approval retrieved successfully',
            []
        );
    }

    /**
     * Get maintenance statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $statistics = $this->maintenanceService->getMaintenanceStatistics($request->all());
        
        return $this->successResponse(
            $statistics,
            'Maintenance statistics retrieved successfully',
            []
        );
    }

    /**
     * Search maintenance records
     */
    public function search(Request $request): JsonResponse
    {
        $maintenanceRecords = $this->maintenanceService->searchMaintenanceRecords($request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($maintenanceRecords),
            'Maintenance search results retrieved successfully',
            []
        );
    }

    /**
     * Get maintenance summary for dashboard
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->maintenanceService->getMaintenanceSummary($request->all());
        
        return $this->successResponse(
            $summary,
            'Maintenance summary retrieved successfully',
            []
        );
    }

    /**
     * Duplicate a maintenance record
     */
    public function duplicate(MaintenanceRecord $maintenanceRecord): JsonResponse
    {
        $newMaintenanceRecord = $this->maintenanceService->duplicateMaintenanceRecord($maintenanceRecord->id);
        
        return $this->successResponse(
            new MaintenanceResource($newMaintenanceRecord),
            'Maintenance record duplicated successfully',
            []
        );
    }

    /**
     * Generate work order number
     */
    public function generateWorkOrder(): JsonResponse
    {
        $workOrderNumber = $this->maintenanceService->generateWorkOrderNumber();
        
        return $this->successResponse(
            ['work_order_number' => $workOrderNumber],
            'Work order number generated successfully',
            []
        );
    }

    /**
     * Get maintenance cost analysis
     */
    public function costAnalysis(Request $request): JsonResponse
    {
        $costAnalysis = $this->maintenanceService->getMaintenanceCostAnalysis($request->all());
        
        return $this->successResponse(
            $costAnalysis,
            'Maintenance cost analysis retrieved successfully',
            []
        );
    }

    /**
     * Get maintenance types
     */
    public function types(): JsonResponse
    {
        $types = MaintenanceRecord::getMaintenanceTypes();
        
        return $this->successResponse(
            $types,
            'Maintenance types retrieved successfully',
            []
        );
    }

    /**
     * Get priority levels
     */
    public function priorities(): JsonResponse
    {
        $priorities = MaintenanceRecord::getPriorityLevels();
        
        return $this->successResponse(
            $priorities,
            'Maintenance priority levels retrieved successfully',
            []
        );
    }

    /**
     * Get maintenance statuses
     */
    public function statuses(): JsonResponse
    {
        $statuses = MaintenanceRecord::getStatuses();
        
        return $this->successResponse(
            $statuses,
            'Maintenance statuses retrieved successfully',
            []
        );
    }

    /**
     * Get maintenance history for equipment
     */
    public function equipmentHistory(int $equipmentId, Request $request): JsonResponse
    {
        $history = $this->maintenanceService->getEquipmentMaintenanceHistory($equipmentId, $request->all());
        
        return $this->successResponse(
            new MaintenanceCollection($history),
            'Equipment maintenance history retrieved successfully',
            []
        );
    }

    /**
     * Get technician performance metrics
     */
    public function technicianPerformance(int $technicianId, Request $request): JsonResponse
    {
        $performance = $this->maintenanceService->getTechnicianPerformance($technicianId, $request->all());
        
        return $this->successResponse(
            $performance,
            'Technician performance metrics retrieved successfully',
            []
        );
    }

    /**
     * Get maintenance trends
     */
    public function trends(Request $request): JsonResponse
    {
        $trends = $this->maintenanceService->getMaintenanceTrends($request->all());
        
        return $this->successResponse(
            $trends,
            'Maintenance trends retrieved successfully',
            []
        );
    }

    /**
     * Generate maintenance report
     */
    public function report(Request $request): JsonResponse
    {
        $report = $this->maintenanceService->generateMaintenanceReport($request->all());
        
        return $this->successResponse(
            $report,
            'Maintenance report generated successfully',
            []
        );
    }
}