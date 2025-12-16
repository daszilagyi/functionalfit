<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health Check Endpoint - Comprehensive system health verification
Route::get('/health', function () {
    $health = [
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'services' => [],
    ];

    try {
        // Check database connection
        DB::connection()->getPdo();
        $dbVersion = DB::select('SELECT VERSION() as version')[0]->version;
        $health['services']['database'] = [
            'status' => 'up',
            'version' => $dbVersion,
            'connection' => config('database.default'),
        ];
    } catch (\Exception $e) {
        $health['status'] = 'unhealthy';
        $health['services']['database'] = [
            'status' => 'down',
            'error' => $e->getMessage(),
        ];
    }

    // Skip Redis check for now (not running in this environment)
    $health['services']['redis'] = [
        'status' => 'skipped',
        'note' => 'Redis not configured in this environment',
    ];

    try {
        // Check cache functionality
        $cacheKey = 'health_check_' . time();
        Cache::put($cacheKey, 'test', 10);
        $cacheValue = Cache::get($cacheKey);
        Cache::forget($cacheKey);
        
        $health['services']['cache'] = [
            'status' => $cacheValue === 'test' ? 'up' : 'degraded',
            'driver' => config('cache.default'),
        ];
    } catch (\Exception $e) {
        $health['status'] = 'unhealthy';
        $health['services']['cache'] = [
            'status' => 'down',
            'error' => $e->getMessage(),
        ];
    }

    // Check storage writability
    try {
        $testFile = storage_path('logs/health_check.tmp');
        file_put_contents($testFile, 'test');
        unlink($testFile);
        $health['services']['storage'] = ['status' => 'up'];
    } catch (\Exception $e) {
        $health['status'] = 'unhealthy';
        $health['services']['storage'] = [
            'status' => 'down',
            'error' => $e->getMessage(),
        ];
    }

    // Application information
    $health['application'] = [
        'name' => config('app.name'),
        'environment' => app()->environment(),
        'debug' => config('app.debug'),
        'timezone' => config('app.timezone'),
    ];

    // Return appropriate HTTP status code
    $statusCode = $health['status'] === 'healthy' ? 200 : 503;

    return response()->json($health, $statusCode);
});

// Simple ping endpoint for basic availability checks
Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Version endpoint
Route::get('/version', function () {
    return response()->json([
        'application' => config('app.name'),
        'version' => '1.0.0', // Update this as needed
        'laravel' => app()->version(),
        'php' => PHP_VERSION,
    ]);
});

// Authenticated user route (protected by Sanctum)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Import controllers
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\Client\ClassController;
use App\Http\Controllers\Api\Client\ClassBookingController;
use App\Http\Controllers\Api\Client\ClientActivityController;
use App\Http\Controllers\Api\Staff\StaffEventController;
use App\Http\Controllers\Api\Staff\EventCheckinController;
use App\Http\Controllers\Api\Staff\StaffExportController;
use App\Http\Controllers\Api\Staff\RoomController as StaffRoomController;
use App\Http\Controllers\Api\Staff\ClientController as StaffClientController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\RoomController;
use App\Http\Controllers\Api\Admin\SiteController;
use App\Http\Controllers\Api\Admin\ClassTemplateController;
use App\Http\Controllers\Api\Admin\ClassOccurrenceController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\ParticipantController;
use App\Http\Controllers\Api\Admin\EmailTemplateController;
use App\Http\Controllers\Api\Admin\EventChangeController;
use App\Http\Controllers\Api\Admin\CalendarChangeController;
use App\Http\Controllers\Api\Admin\AdminEventController;
use App\Http\Controllers\Api\Staff\StaffParticipantController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\ServiceTypeController;
use App\Http\Controllers\Api\ClientPriceCodeController;
use App\Http\Controllers\Api\PricingResolveController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\Staff\StaffReportController;
use App\Http\Controllers\Api\Client\ClientReportController;
use App\Http\Controllers\Api\ExportController;

// API v1 routes group
Route::prefix('v1')->group(function () {

    // ============================================
    // PUBLIC ROUTES (No authentication required)
    // ============================================

    // Public classes calendar (unauthenticated)
    Route::prefix('public')->group(function () {
        Route::get('/classes', [PublicController::class, 'listClasses']);
    });

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/register-quick', [AuthController::class, 'registerQuick']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Settings routes (public - no authentication required)
    Route::prefix('settings')->group(function () {
        Route::get('/technical-guest-client-id', [SettingsController::class, 'getTechnicalGuestClientId']);
    });

    // Webhook routes (public, verified by signature)
    Route::prefix('webhooks')->group(function () {
        Route::post('/woocommerce', [WebhookController::class, 'woocommerce']);
        Route::post('/stripe', [WebhookController::class, 'stripe']);
    });

    // ============================================
    // PROTECTED ROUTES (Authentication required)
    // ============================================

    Route::middleware('auth:sanctum')->group(function () {

        // ============================================
        // CLIENT ROUTES
        // ============================================
        Route::prefix('classes')->group(function () {
            // Browse classes (all authenticated users)
            Route::get('/', [ClassController::class, 'index']);
            Route::get('/{id}', [ClassController::class, 'show']);

            // Book/cancel (clients only)
            Route::post('/{occurrenceId}/book', [ClassBookingController::class, 'book']);
            Route::post('/{occurrenceId}/cancel', [ClassBookingController::class, 'cancel']);
        });

        Route::prefix('clients/{clientId}')->group(function () {
            Route::get('/activity', [ClientActivityController::class, 'index']);
            Route::get('/passes', [ClientActivityController::class, 'passes']);
            Route::get('/upcoming', [ClientActivityController::class, 'upcoming']);
        });

        // Client reports
        Route::prefix('reports/client')->group(function () {
            Route::get('/my-activity', [ClientReportController::class, 'myActivity']);
            Route::get('/my-finance', [ClientReportController::class, 'myFinance']);
        });

        // Notification preferences (all authenticated users)
        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'index']);
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);

        // ============================================
        // EXPORT ROUTES (All authenticated users)
        // ============================================
        Route::prefix('exports')->group(function () {
            Route::get('/', [ExportController::class, 'index']);
            Route::post('/reports', [ExportController::class, 'createReport']);
            Route::get('/{id}', [ExportController::class, 'show'])->name('exports.show');
            Route::get('/{id}/download', [ExportController::class, 'download'])->name('exports.download');
        });

        // ============================================
        // PRICING RESOLVE (Staff + Admin)
        // ============================================
        Route::middleware('role:staff,admin')->group(function () {
            Route::get('/pricing/resolve', [PricingResolveController::class, 'resolve']);
            Route::get('/pricing/resolve-by-ids', [PricingResolveController::class, 'resolveByIds']);
        });

        // ============================================
        // STAFF ROUTES
        // ============================================
        Route::prefix('staff')->middleware('role:staff,admin')->group(function () {
            // Staff events (1:1)
            Route::get('/my-events', [StaffEventController::class, 'index']);
            Route::post('/events', [StaffEventController::class, 'store']);
            Route::patch('/events/{id}', [StaffEventController::class, 'update']);
            Route::delete('/events/{id}', [StaffEventController::class, 'destroy']);

            // Check-in
            Route::post('/events/{eventId}/checkin', [EventCheckinController::class, 'checkinEvent']);
            Route::post('/classes/{occurrenceId}/checkin', [EventCheckinController::class, 'checkinClass']);

            // Rooms (read-only for staff)
            Route::get('/rooms', [StaffRoomController::class, 'index']);
            Route::get('/rooms/{id}', [StaffRoomController::class, 'show']);

            // Client search (for adding participants)
            Route::get('/clients/search', [StaffClientController::class, 'search']);
            Route::get('/clients/batch', [StaffClientController::class, 'batch']);

            // Exports (legacy)
            Route::get('/exports/payout', [StaffExportController::class, 'payout']);
            Route::get('/exports/attendance', [StaffExportController::class, 'attendance']);

            // Reports
            Route::prefix('reports')->group(function () {
                Route::get('/my-summary', [StaffReportController::class, 'mySummary']);
                Route::get('/my-clients', [StaffReportController::class, 'myClients']);
                Route::get('/my-trends', [StaffReportController::class, 'myTrends']);
            });

            // Participant management (staff's own events/classes only)
            Route::get('/class-occurrences/{id}/participants', [StaffParticipantController::class, 'listClassParticipants']);
            Route::post('/class-occurrences/{id}/participants', [StaffParticipantController::class, 'addClassParticipant']);
            Route::delete('/class-occurrences/{id}/participants/{clientId}', [StaffParticipantController::class, 'removeClassParticipant']);
            Route::get('/events/{id}/participant', [StaffParticipantController::class, 'getEventParticipant']);
            Route::post('/events/{id}/participant', [StaffParticipantController::class, 'assignEventParticipant']);
            Route::delete('/events/{id}/participant', [StaffParticipantController::class, 'removeEventParticipant']);

            // Calendar changes (staff's own changes only)
            Route::get('/calendar-changes', [CalendarChangeController::class, 'staffIndex']);
        });

        // ============================================
        // ADMIN ROUTES
        // ============================================
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            // User management
            Route::apiResource('users', UserController::class);

            // Site management
            Route::apiResource('sites', SiteController::class);
            Route::patch('sites/{id}/toggle-active', [SiteController::class, 'toggleActive']);

            // Room management
            Route::apiResource('rooms', RoomController::class);

            // Class template management
            Route::apiResource('class-templates', ClassTemplateController::class);

            // Class occurrence management
            Route::apiResource('class-occurrences', ClassOccurrenceController::class);
            Route::patch('class-occurrences/{id}/force-move', [ClassOccurrenceController::class, 'forceMove']);

            // Reports (legacy)
            Route::prefix('reports')->group(function () {
                Route::get('/attendance', [ReportController::class, 'attendance']);
                Route::get('/payouts', [ReportController::class, 'payouts']);
                Route::get('/revenue', [ReportController::class, 'revenue']);
                Route::get('/utilization', [ReportController::class, 'utilization']);
                Route::get('/clients', [ReportController::class, 'clients']);

                // Excel export routes
                Route::get('/attendance/export', [ReportController::class, 'exportAttendance']);
                Route::get('/payouts/export', [ReportController::class, 'exportPayouts']);
                Route::get('/revenue/export', [ReportController::class, 'exportRevenue']);
                Route::get('/utilization/export', [ReportController::class, 'exportUtilization']);
                Route::get('/clients/export', [ReportController::class, 'exportClients']);
            });

            // Admin reports (new)
            Route::prefix('reports/admin')->group(function () {
                Route::get('/trainer-summary', [AdminReportController::class, 'trainerSummary']);
                Route::get('/site-client-list', [AdminReportController::class, 'siteClientList']);
                Route::get('/finance-overview', [AdminReportController::class, 'financeOverview']);
            });

            // Audit logs
            Route::get('/audit-logs', [AuditLogController::class, 'index']);
            Route::get('/events/{eventId}/audit-logs', [AuditLogController::class, 'showEventLogs']);

            // Event changes log (calendar modifications - legacy)
            Route::get('/event-changes', [EventChangeController::class, 'index']);

            // Calendar changes log (comprehensive change tracking)
            Route::get('/calendar-changes', [CalendarChangeController::class, 'index']);
            Route::get('/calendar-changes/{id}', [CalendarChangeController::class, 'show']);

            // Admin events (all events with room filtering)
            Route::get('/events', [AdminEventController::class, 'index']);
            Route::post('/events', [AdminEventController::class, 'store']);
            Route::get('/events/{id}', [AdminEventController::class, 'show']);
            Route::put('/events/{id}', [AdminEventController::class, 'update']);
            Route::delete('/events/{id}', [AdminEventController::class, 'destroy']);

            // Participant management (admin can manage any event/class)
            Route::get('/class-occurrences/{id}/participants', [ParticipantController::class, 'listClassParticipants']);
            Route::post('/class-occurrences/{id}/participants', [ParticipantController::class, 'addClassParticipant']);
            Route::delete('/class-occurrences/{id}/participants/{clientId}', [ParticipantController::class, 'removeClassParticipant']);
            Route::get('/events/{id}/participant', [ParticipantController::class, 'getEventParticipant']);
            Route::post('/events/{id}/participant', [ParticipantController::class, 'assignEventParticipant']);
            Route::delete('/events/{id}/participant', [ParticipantController::class, 'removeEventParticipant']);

            // Email template management
            Route::apiResource('email-templates', EmailTemplateController::class);
            Route::get('email-templates-variables', [EmailTemplateController::class, 'getVariables']);
            Route::post('email-templates/{id}/preview', [EmailTemplateController::class, 'preview']);
            Route::post('email-templates/{id}/send-test', [EmailTemplateController::class, 'sendTest']);
            Route::get('email-templates/{id}/versions', [EmailTemplateController::class, 'getVersions']);
            Route::post('email-templates/{id}/restore/{versionId}', [EmailTemplateController::class, 'restore']);

            // Pricing management
            Route::prefix('pricing')->group(function () {
                Route::get('/class-defaults', [App\Http\Controllers\Api\Admin\PricingController::class, 'listDefaults']);
                Route::post('/class-defaults', [App\Http\Controllers\Api\Admin\PricingController::class, 'storeDefault']);
                Route::patch('/class-defaults/{id}', [App\Http\Controllers\Api\Admin\PricingController::class, 'updateDefault']);
                Route::patch('/class-defaults/{id}/toggle-active', [App\Http\Controllers\Api\Admin\PricingController::class, 'toggleActiveDefault']);
                Route::delete('/class-defaults/{id}', [App\Http\Controllers\Api\Admin\PricingController::class, 'destroyDefault']);
                Route::post('/assign', [App\Http\Controllers\Api\Admin\PricingController::class, 'assignPricing']);
                Route::post('/assign-event', [App\Http\Controllers\Api\Admin\PricingController::class, 'assignEventPricing']);
                Route::get('/clients/{clientId}', [App\Http\Controllers\Api\Admin\PricingController::class, 'listClientPricing']);
                Route::post('/client-class', [App\Http\Controllers\Api\Admin\PricingController::class, 'storeClientPricing']);
            });

            // Settlement management
            Route::prefix('settlements')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\Admin\SettlementController::class, 'index']);
                Route::get('/preview', [App\Http\Controllers\Api\Admin\SettlementController::class, 'preview']);
                Route::post('/generate', [App\Http\Controllers\Api\Admin\SettlementController::class, 'generate']);
                Route::get('/{id}', [App\Http\Controllers\Api\Admin\SettlementController::class, 'show']);
                Route::patch('/{id}', [App\Http\Controllers\Api\Admin\SettlementController::class, 'updateStatus']);
            });

            // Service Types management
            Route::apiResource('service-types', ServiceTypeController::class);
            Route::patch('service-types/{service_type}/toggle-active', [ServiceTypeController::class, 'toggleActive']);

            // Client Price Codes management
            Route::get('clients/{client}/price-codes', [ClientPriceCodeController::class, 'index']);
            Route::post('clients/{client}/price-codes', [ClientPriceCodeController::class, 'store']);
            Route::patch('client-price-codes/{clientPriceCode}', [ClientPriceCodeController::class, 'update']);
            Route::delete('client-price-codes/{clientPriceCode}', [ClientPriceCodeController::class, 'destroy']);
            Route::patch('client-price-codes/{clientPriceCode}/toggle-active', [ClientPriceCodeController::class, 'toggleActive']);

            // Google Calendar Sync
            Route::prefix('google-calendar-sync')->group(function () {
                // Configuration management
                Route::get('/configs', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'index']);
                Route::post('/configs', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'store']);
                Route::put('/configs/{id}', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'update']);
                Route::delete('/configs/{id}', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'destroy']);

                // Test connection
                Route::post('/test-connection', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'testConnection']);

                // Import/Export operations
                Route::post('/import', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'import']);
                Route::post('/export', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'export']);

                // Sync logs
                Route::get('/logs', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'logs']);
                Route::get('/logs/{id}', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'showLog']);
                Route::post('/logs/{id}/cancel', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'cancelSync']);

                // Conflict resolution
                Route::post('/logs/{id}/resolve-conflicts', [App\Http\Controllers\Admin\GoogleCalendarSyncController::class, 'resolveConflicts']);
            });
        });
    });
});
