<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ReportExport;
use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateReportExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes
    public array $backoff = [60, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ReportExport $reportExport
    ) {
        $this->onQueue('reports');
    }

    /**
     * Execute the job.
     */
    public function handle(ReportService $reportService): void
    {
        Log::info('Starting report export generation', [
            'export_id' => $this->reportExport->id,
            'report_key' => $this->reportExport->report_key,
        ]);

        $this->reportExport->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            // Generate report data based on report key
            $reportData = $this->generateReportData($reportService);

            // Convert to desired format and save
            $filePath = $this->saveReportFile($reportData);

            // Update export record
            $this->reportExport->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'file_size' => Storage::disk('local')->size($filePath),
                'completed_at' => now(),
            ]);

            Log::info('Report export generated successfully', [
                'export_id' => $this->reportExport->id,
                'file_path' => $filePath,
            ]);
        } catch (\Exception $e) {
            Log::error('Report export generation failed', [
                'export_id' => $this->reportExport->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->reportExport->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate report data using ReportService
     */
    private function generateReportData(ReportService $reportService): array
    {
        $params = $this->reportExport->params;
        $reportKey = $this->reportExport->report_key;

        return match ($reportKey) {
            'admin.trainer-summary' => $reportService->generateAdminTrainerSummary(
                from: $params['from'],
                to: $params['to'],
                groupBy: $params['groupBy'],
                trainerId: $params['trainerId'] ?? null,
                serviceType: $params['serviceType'] ?? null,
                siteId: $params['site'] ?? null
            ),
            'admin.site-client-list' => $reportService->generateAdminSiteClientList(
                from: $params['from'],
                to: $params['to'],
                siteId: $params['site'],
                roomId: $params['roomId'] ?? null
            ),
            'admin.finance-overview' => $reportService->generateAdminFinanceOverview(
                from: $params['from'],
                to: $params['to'],
                groupBy: $params['groupBy']
            ),
            'staff.my-summary' => $reportService->generateStaffMySummary(
                staffId: $params['staffId'],
                from: $params['from'],
                to: $params['to'],
                groupBy: $params['groupBy']
            ),
            'staff.my-clients' => $reportService->generateStaffMyClients(
                staffId: $params['staffId'],
                from: $params['from'],
                to: $params['to']
            ),
            'staff.my-trends' => $reportService->generateStaffMyTrends(
                staffId: $params['staffId'],
                from: $params['from'],
                to: $params['to'],
                granularity: $params['granularity']
            ),
            'client.my-activity' => $reportService->generateClientMyActivity(
                clientId: $params['clientId'],
                from: $params['from'],
                to: $params['to'],
                groupBy: $params['groupBy']
            ),
            'client.my-finance' => $reportService->generateClientMyFinance(
                clientId: $params['clientId'],
                from: $params['from'],
                to: $params['to'],
                groupBy: $params['groupBy']
            ),
            default => throw new \InvalidArgumentException("Unknown report key: {$reportKey}"),
        };
    }

    /**
     * Save report file to storage
     */
    private function saveReportFile(array $reportData): string
    {
        $format = $this->reportExport->format;
        $filename = sprintf(
            'reports/%s_%s_%d.%s',
            str_replace('.', '_', $this->reportExport->report_key),
            now()->format('Ymd_His'),
            $this->reportExport->id,
            $format
        );

        $content = match ($format) {
            'json' => json_encode($reportData, JSON_PRETTY_PRINT),
            'csv' => $this->convertToCsv($reportData),
            'xlsx' => $this->convertToXlsx($reportData),
            default => json_encode($reportData, JSON_PRETTY_PRINT),
        };

        Storage::disk('local')->put($filename, $content);

        return $filename;
    }

    /**
     * Convert report data to CSV format
     */
    private function convertToCsv(array $reportData): string
    {
        $csv = '';

        // Add headers
        if (isset($reportData['grouped_data']) && is_array($reportData['grouped_data'])) {
            $firstRow = reset($reportData['grouped_data']);
            if ($firstRow) {
                $csv .= implode(',', array_keys($firstRow)) . "\n";

                // Add data rows
                foreach ($reportData['grouped_data'] as $row) {
                    $csv .= implode(',', array_values($row)) . "\n";
                }
            }
        }

        return $csv;
    }

    /**
     * Convert report data to XLSX format
     * Note: This is a placeholder. For production, use a package like PhpSpreadsheet or Laravel Excel.
     */
    private function convertToXlsx(array $reportData): string
    {
        // For now, return JSON as placeholder
        // In production, use: https://github.com/SpartnerNL/Laravel-Excel
        return json_encode($reportData, JSON_PRETTY_PRINT);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Report export job failed permanently', [
            'export_id' => $this->reportExport->id,
            'error' => $exception->getMessage(),
        ]);

        $this->reportExport->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
