<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AdminFinanceOverviewRequest;
use App\Http\Requests\Reports\AdminSiteClientListRequest;
use App\Http\Requests\Reports\AdminTrainerSummaryRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    /**
     * GET /api/v1/reports/admin/trainer-summary
     *
     * Generate trainer summary report grouped by site or room.
     * Admin can view all trainers or filter by specific trainer, service type, or site.
     */
    public function trainerSummary(AdminTrainerSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $report = $this->reportService->generateAdminTrainerSummary(
                from: $validated['from'],
                to: $validated['to'],
                groupBy: $validated['groupBy'],
                trainerId: $validated['trainerId'] ?? null,
                serviceType: $validated['serviceType'] ?? null,
                siteId: $validated['site'] ?? null
            );

            return ApiResponse::success($report, 'Trainer summary report generated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to generate trainer summary report', [
                'error' => $e->getMessage(),
                'params' => $validated,
            ]);

            return ApiResponse::error('Failed to generate report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/reports/admin/site-client-list
     *
     * Generate client list for a specific site with activity summary.
     * Can be filtered by room within the site.
     */
    public function siteClientList(AdminSiteClientListRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $report = $this->reportService->generateAdminSiteClientList(
                from: $validated['from'],
                to: $validated['to'],
                siteId: $validated['site'],
                roomId: $validated['roomId'] ?? null
            );

            return ApiResponse::success($report, 'Site client list generated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to generate site client list', [
                'error' => $e->getMessage(),
                'params' => $validated,
            ]);

            return ApiResponse::error('Failed to generate report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/reports/admin/finance-overview
     *
     * Generate financial overview with entry fees, trainer fees, and net income.
     * Groups by month, week, or day.
     */
    public function financeOverview(AdminFinanceOverviewRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $report = $this->reportService->generateAdminFinanceOverview(
                from: $validated['from'],
                to: $validated['to'],
                groupBy: $validated['groupBy']
            );

            return ApiResponse::success($report, 'Finance overview report generated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to generate finance overview', [
                'error' => $e->getMessage(),
                'params' => $validated,
            ]);

            return ApiResponse::error('Failed to generate report', ['error' => $e->getMessage()], 500);
        }
    }
}
