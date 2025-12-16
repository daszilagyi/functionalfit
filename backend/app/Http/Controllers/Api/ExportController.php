<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\CreateExportRequest;
use App\Http\Responses\ApiResponse;
use App\Jobs\GenerateReportExport;
use App\Models\ReportExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * POST /api/v1/exports/reports
     *
     * Create a new report export job.
     * Returns job ID for status checking.
     */
    public function createReport(CreateExportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Check rate limiting: max 5 pending exports per user
        $pendingCount = ReportExport::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount >= 5) {
            return ApiResponse::error(
                'Too many export requests. Please wait for previous exports to complete.',
                null,
                429
            );
        }

        // Enrich params with user context for scoped reports
        $params = $validated['params'];
        if (str_starts_with($validated['report_key'], 'staff.')) {
            $params['staffId'] = $request->user()->staffProfile?->id;
        } elseif (str_starts_with($validated['report_key'], 'client.')) {
            $params['clientId'] = $request->user()->client?->id;
        }

        // Create export record
        $export = ReportExport::create([
            'user_id' => $request->user()->id,
            'report_key' => $validated['report_key'],
            'params' => $params,
            'format' => $validated['format'] ?? 'xlsx',
            'status' => 'pending',
        ]);

        // Dispatch job
        GenerateReportExport::dispatch($export);

        return ApiResponse::created([
            'export_id' => $export->id,
            'status' => $export->status,
            'created_at' => $export->created_at->toIso8601String(),
        ], 'Export job created successfully');
    }

    /**
     * GET /api/v1/exports/{id}
     *
     * Get export status and download link if ready.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $export = ReportExport::findOrFail($id);

        // Authorization: user can only view their own exports
        if ($export->user_id !== $request->user()->id) {
            return ApiResponse::forbidden('You do not have permission to view this export');
        }

        $response = [
            'export_id' => $export->id,
            'report_key' => $export->report_key,
            'format' => $export->format,
            'status' => $export->status,
            'created_at' => $export->created_at->toIso8601String(),
            'started_at' => $export->started_at?->toIso8601String(),
            'completed_at' => $export->completed_at?->toIso8601String(),
        ];

        if ($export->isReady()) {
            $response['download_url'] = route('exports.download', ['id' => $export->id]);
            $response['file_size'] = $export->file_size;
        } elseif ($export->hasFailed()) {
            $response['error_message'] = $export->error_message;
        }

        return ApiResponse::success($response, 'Export details retrieved');
    }

    /**
     * GET /api/v1/exports/{id}/download
     *
     * Download the generated export file.
     */
    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $export = ReportExport::findOrFail($id);

        // Authorization: user can only download their own exports
        if ($export->user_id !== $request->user()->id) {
            return ApiResponse::forbidden('You do not have permission to download this export');
        }

        if (!$export->isReady()) {
            return ApiResponse::error('Export is not ready for download', [
                'status' => $export->status,
            ], 400);
        }

        if (!Storage::disk('local')->exists($export->file_path)) {
            return ApiResponse::error('Export file not found', null, 404);
        }

        $filename = sprintf(
            '%s_%s.%s',
            str_replace('.', '_', $export->report_key),
            $export->created_at->format('Ymd'),
            $export->format
        );

        return Storage::disk('local')->download($export->file_path, $filename);
    }

    /**
     * GET /api/v1/exports
     *
     * List user's export history.
     */
    public function index(Request $request): JsonResponse
    {
        $exports = ReportExport::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn($export) => [
                'export_id' => $export->id,
                'report_key' => $export->report_key,
                'format' => $export->format,
                'status' => $export->status,
                'created_at' => $export->created_at->toIso8601String(),
                'completed_at' => $export->completed_at?->toIso8601String(),
                'download_url' => $export->isReady() ? route('exports.download', ['id' => $export->id]) : null,
            ]);

        return ApiResponse::success([
            'exports' => $exports,
            'total' => $exports->count(),
        ], 'Export history retrieved');
    }
}
