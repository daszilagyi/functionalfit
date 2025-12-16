<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StaffMyClientsRequest;
use App\Http\Requests\Reports\StaffMySummaryRequest;
use App\Http\Requests\Reports\StaffMyTrendsRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class StaffReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    /**
     * GET /api/v1/reports/staff/my-summary
     *
     * Generate summary report for authenticated staff member.
     * Scoped to only show data where staff_id == auth user's staff profile id.
     */
    public function mySummary(StaffMySummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::forbidden('Only staff members can access this report');
        }

        try {
            $report = $this->reportService->generateStaffMySummary(
                staffId: $staff->id,
                from: $validated['from'],
                to: $validated['to'],
                groupBy: $validated['groupBy']
            );

            return ApiResponse::success($report, 'Staff summary report generated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to generate staff summary report', [
                'error' => $e->getMessage(),
                'staff_id' => $staff->id,
                'params' => $validated,
            ]);

            return ApiResponse::error('Failed to generate report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/reports/staff/my-clients
     *
     * Generate client list for authenticated staff member.
     * Shows all clients who had sessions with this staff member.
     */
    public function myClients(StaffMyClientsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::forbidden('Only staff members can access this report');
        }

        try {
            $report = $this->reportService->generateStaffMyClients(
                staffId: $staff->id,
                from: $validated['from'],
                to: $validated['to']
            );

            return ApiResponse::success($report, 'Staff clients report generated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to generate staff clients report', [
                'error' => $e->getMessage(),
                'staff_id' => $staff->id,
                'params' => $validated,
            ]);

            return ApiResponse::error('Failed to generate report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/reports/staff/my-trends
     *
     * Generate trends report for authenticated staff member.
     * Shows activity trends grouped by week or month.
     */
    public function myTrends(StaffMyTrendsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::forbidden('Only staff members can access this report');
        }

        try {
            $report = $this->reportService->generateStaffMyTrends(
                staffId: $staff->id,
                from: $validated['from'],
                to: $validated['to'],
                granularity: $validated['granularity']
            );

            return ApiResponse::success($report, 'Staff trends report generated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to generate staff trends report', [
                'error' => $e->getMessage(),
                'staff_id' => $staff->id,
                'params' => $validated,
            ]);

            return ApiResponse::error('Failed to generate report', ['error' => $e->getMessage()], 500);
        }
    }
}
