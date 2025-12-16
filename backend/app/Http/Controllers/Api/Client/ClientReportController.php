<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ClientMyActivityRequest;
use App\Http\Requests\Reports\ClientMyFinanceRequest;
use App\Http\Responses\ApiResponse;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ClientReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService
    ) {}

    /**
     * GET /api/v1/reports/client/my-activity
     *
     * Generate activity report for authenticated client.
     * Scoped to only show data where client_id == auth user's client id.
     */
    public function myActivity(ClientMyActivityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $client = $request->user()->client;

        if (!$client) {
            return ApiResponse::forbidden('Only clients can access this report');
        }

        try {
            $report = $this->reportService->generateClientMyActivity(
                clientId: $client->id,
                from: $validated['from'],
                to: $validated['to'],
                groupBy: $validated['groupBy']
            );

            return ApiResponse::success($report, 'Client activity report generated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to generate client activity report', [
                'error' => $e->getMessage(),
                'client_id' => $client->id,
                'params' => $validated,
            ]);

            return ApiResponse::error('Failed to generate report', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/v1/reports/client/my-finance
     *
     * Generate finance report for authenticated client.
     * Shows pass purchases and credit usage grouped by month.
     */
    public function myFinance(ClientMyFinanceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $client = $request->user()->client;

        if (!$client) {
            return ApiResponse::forbidden('Only clients can access this report');
        }

        try {
            $report = $this->reportService->generateClientMyFinance(
                clientId: $client->id,
                from: $validated['from'],
                to: $validated['to'],
                groupBy: $validated['groupBy']
            );

            return ApiResponse::success($report, 'Client finance report generated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to generate client finance report', [
                'error' => $e->getMessage(),
                'client_id' => $client->id,
                'params' => $validated,
            ]);

            return ApiResponse::error('Failed to generate report', ['error' => $e->getMessage()], 500);
        }
    }
}
