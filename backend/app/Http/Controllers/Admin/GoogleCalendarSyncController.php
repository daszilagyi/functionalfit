<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\GoogleCalendarSyncConfig;
use App\Models\GoogleCalendarSyncLog;
use App\Services\GoogleCalendarImportService;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoogleCalendarSyncController extends Controller
{
    public function __construct(
        private GoogleCalendarService $googleCalendarService,
        private GoogleCalendarImportService $importService
    ) {
    }

    /**
     * Get all sync configurations.
     */
    public function index(): JsonResponse
    {
        $configs = GoogleCalendarSyncConfig::with(['room', 'syncLogs' => function ($query) {
            $query->latest()->limit(5);
        }])->get();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Create a new sync configuration.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'google_calendar_id' => 'required|string|max:255',
            'room_id' => 'nullable|exists:rooms,id',
            'sync_enabled' => 'boolean',
            'sync_direction' => 'required|in:import,export,both',
            'service_account_json' => 'nullable|string',
            'sync_options' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $config = GoogleCalendarSyncConfig::create($validator->validated());

        Log::info('Google Calendar sync config created', [
            'config_id' => $config->id,
            'name' => $config->name,
        ]);

        return response()->json([
            'success' => true,
            'data' => $config->load('room'),
            'message' => 'Sync configuration created successfully',
        ], 201);
    }

    /**
     * Update a sync configuration.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $config = GoogleCalendarSyncConfig::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'google_calendar_id' => 'sometimes|string|max:255',
            'room_id' => 'nullable|exists:rooms,id',
            'sync_enabled' => 'boolean',
            'sync_direction' => 'sometimes|in:import,export,both',
            'service_account_json' => 'nullable|string',
            'sync_options' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $config->update($validator->validated());

        Log::info('Google Calendar sync config updated', [
            'config_id' => $config->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $config->load('room'),
            'message' => 'Sync configuration updated successfully',
        ]);
    }

    /**
     * Delete a sync configuration.
     */
    public function destroy(int $id): JsonResponse
    {
        $config = GoogleCalendarSyncConfig::findOrFail($id);
        $config->delete();

        Log::info('Google Calendar sync config deleted', [
            'config_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sync configuration deleted successfully',
        ]);
    }

    /**
     * Import events from Google Calendar.
     */
    public function import(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sync_config_id' => 'required|exists:google_calendar_sync_configs,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'room_id' => 'nullable|exists:rooms,id',
            'auto_resolve_conflicts' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $config = GoogleCalendarSyncConfig::findOrFail($request->sync_config_id);

        if (!$config->isImportEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Import is not enabled for this configuration',
            ], 403);
        }

        try {
            $startDate = new \DateTime($request->start_date);
            $endDate = new \DateTime($request->end_date);
            $autoResolve = $request->boolean('auto_resolve_conflicts', false);

            $log = $this->importService->importEvents(
                $config,
                $startDate,
                $endDate,
                $request->room_id,
                $autoResolve
            );

            $response = [
                'success' => true,
                'data' => $log,
                'message' => 'Import completed successfully',
            ];

            if ($log->hasConflicts() && !$autoResolve) {
                $response['message'] = 'Import completed with conflicts requiring resolution';
                $response['conflicts'] = $log->conflicts;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Import failed', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export events to Google Calendar.
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sync_config_id' => 'required|exists:google_calendar_sync_configs,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'room_id' => 'nullable|exists:rooms,id',
            'overwrite_existing' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $config = GoogleCalendarSyncConfig::findOrFail($request->sync_config_id);

        if (!$config->isExportEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Export is not enabled for this configuration',
            ], 403);
        }

        try {
            // Create sync log
            $log = GoogleCalendarSyncLog::create([
                'sync_config_id' => $config->id,
                'operation' => 'export',
                'status' => 'in_progress',
                'started_at' => now(),
                'filters' => [
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'room_id' => $request->room_id,
                ],
            ]);

            // Build query for events to export
            $query = Event::whereBetween('starts_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59',
            ]);

            if ($request->room_id) {
                $query->where('room_id', $request->room_id);
            } elseif ($config->room_id) {
                $query->where('room_id', $config->room_id);
            }

            $events = $query->with(['room', 'staff', 'client'])->get();

            $log->update(['events_processed' => $events->count()]);

            // Export to Google Calendar
            $results = $this->googleCalendarService->exportEventsToGoogleCalendar(
                $config->google_calendar_id,
                $events->all(),
                $request->boolean('overwrite_existing', false)
            );

            // Update log with results
            $log->update([
                'status' => 'completed',
                'completed_at' => now(),
                'events_created' => $results['created'],
                'events_updated' => $results['updated'],
                'events_skipped' => $results['skipped'],
                'events_failed' => $results['failed'],
                'metadata' => [
                    'errors' => $results['errors'],
                ],
            ]);

            // Update config last export timestamp
            $config->update(['last_export_at' => now()]);

            return response()->json([
                'success' => true,
                'data' => $log,
                'message' => 'Export completed successfully',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            if (isset($log)) {
                $log->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
            }

            Log::error('Export failed', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sync logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $query = GoogleCalendarSyncLog::with('syncConfig.room');

        if ($request->has('sync_config_id')) {
            $query->where('sync_config_id', $request->sync_config_id);
        }

        if ($request->has('operation')) {
            $query->where('operation', $request->operation);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get a specific sync log with details.
     */
    public function showLog(int $id): JsonResponse
    {
        $log = GoogleCalendarSyncLog::with('syncConfig.room')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * Resolve conflicts from an import operation.
     */
    public function resolveConflicts(Request $request, int $logId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resolutions' => 'required|array',
            'resolutions.*' => 'required|in:overwrite,skip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $log = GoogleCalendarSyncLog::with('syncConfig')->findOrFail($logId);

        if (!$log->hasConflicts()) {
            return response()->json([
                'success' => false,
                'message' => 'This log has no conflicts to resolve',
            ], 400);
        }

        if ($log->operation !== 'import') {
            return response()->json([
                'success' => false,
                'message' => 'Only import logs can have conflicts',
            ], 400);
        }

        try {
            $updatedLog = $this->importService->resolveConflicts(
                $log,
                $request->resolutions
            );

            return response()->json([
                'success' => true,
                'data' => $updatedLog,
                'message' => 'Conflicts resolved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Conflict resolution failed', [
                'log_id' => $logId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conflict resolution failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Google Calendar connection.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'google_calendar_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Try to fetch calendar info
            $calendar = $this->googleCalendarService->getCalendarService()
                ->calendars->get($request->google_calendar_id);

            return response()->json([
                'success' => true,
                'message' => 'Connection successful',
                'data' => [
                    'calendar_summary' => $calendar->getSummary(),
                    'calendar_timezone' => $calendar->getTimeZone(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel an in-progress sync operation.
     */
    public function cancelSync(int $logId): JsonResponse
    {
        $log = GoogleCalendarSyncLog::findOrFail($logId);

        if (!$log->isInProgress()) {
            return response()->json([
                'success' => false,
                'message' => 'Only in-progress operations can be cancelled',
            ], 400);
        }

        $log->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        Log::info('Sync operation cancelled', [
            'log_id' => $logId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sync operation cancelled',
        ]);
    }
}
