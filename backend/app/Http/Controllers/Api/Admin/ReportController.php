<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\Pass;
use App\Models\Client;
use App\Models\StaffProfile;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * Get attendance report
     *
     * GET /api/admin/reports/attendance
     */
    public function attendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        // Convert to Carbon instances with proper start/end of day
        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        // Individual events attendance
        $individualEvents = Event::whereBetween('starts_at', [$dateFrom, $dateTo])->get();
        $individualAttended = $individualEvents->where('attendance_status', 'attended')->count();
        $individualNoShows = $individualEvents->where('attendance_status', 'no_show')->count();

        // Class registrations attendance
        $classRegistrations = ClassRegistration::whereHas('occurrence', function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('starts_at', [$dateFrom, $dateTo]);
        })->get();

        $classAttended = $classRegistrations->where('attendance_status', 'attended')->count();
        $classNoShows = $classRegistrations->where('attendance_status', 'no_show')->count();

        $totalSessions = $individualEvents->count() + $classRegistrations->count();
        $totalAttended = $individualAttended + $classAttended;
        $totalNoShows = $individualNoShows + $classNoShows;

        return ApiResponse::success([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_sessions' => $totalSessions,
                'total_attended' => $totalAttended,
                'total_no_shows' => $totalNoShows,
                'not_checked_in' => $totalSessions - $totalAttended - $totalNoShows,
                'attendance_rate' => $totalSessions > 0 ? round(($totalAttended / $totalSessions) * 100, 2) : 0,
                'no_show_rate' => $totalSessions > 0 ? round(($totalNoShows / $totalSessions) * 100, 2) : 0,
            ],
            'by_type' => [
                'individual' => [
                    'total' => $individualEvents->count(),
                    'attended' => $individualAttended,
                    'no_shows' => $individualNoShows,
                ],
                'group_classes' => [
                    'total' => $classRegistrations->count(),
                    'attended' => $classAttended,
                    'no_shows' => $classNoShows,
                ],
            ],
        ]);
    }

    /**
     * Get staff payouts report
     *
     * GET /api/admin/reports/payouts
     */
    public function payouts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        // Convert to Carbon instances with proper start/end of day
        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $staffMembers = StaffProfile::with('user')->get();

        $payoutData = $staffMembers->map(function ($staff) use ($dateFrom, $dateTo) {
            // Individual events with detailed info (all events, not just attended)
            $individualEvents = Event::with(['client.user', 'room', 'serviceType'])
                ->where('staff_id', $staff->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->orderBy('starts_at')
                ->get();

            // Group classes with detailed info
            $groupClasses = ClassOccurrence::with(['template', 'room'])
                ->where('trainer_id', $staff->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->orderBy('starts_at')
                ->get();

            // Calculate entry fees and trainer fees from individual events
            $totalEntryFee = $individualEvents->sum('entry_fee_brutto') ?? 0;
            $totalTrainerFee = $individualEvents->sum('trainer_fee_brutto') ?? 0;

            // Group class fees (from class occurrence or template)
            $groupEntryFee = $groupClasses->sum(function ($oc) {
                return $oc->entry_fee_brutto ?? $oc->template?->entry_fee_brutto ?? 0;
            });
            $groupTrainerFee = $groupClasses->sum(function ($oc) {
                return $oc->trainer_fee_brutto ?? $oc->template?->trainer_fee_brutto ?? 0;
            });

            // Session counts
            $individualCount = $individualEvents->count();
            $groupCount = $groupClasses->count();

            // Detailed breakdown for individual sessions
            $individualDetails = $individualEvents->map(fn($e) => [
                'id' => $e->id,
                'date' => $e->starts_at->format('Y-m-d'),
                'time' => $e->starts_at->format('H:i') . ' - ' . $e->ends_at->format('H:i'),
                'client_name' => $e->client?->user?->name ?? '-',
                'service_type' => $e->serviceType?->name ?? '-',
                'room' => $e->room?->name ?? '-',
                'entry_fee' => $e->entry_fee_brutto ?? 0,
                'trainer_fee' => $e->trainer_fee_brutto ?? 0,
                'total_fee' => ($e->entry_fee_brutto ?? 0) + ($e->trainer_fee_brutto ?? 0),
                'attendance_status' => $e->attendance_status, // 'attended', 'no_show', or null
            ]);

            // Detailed breakdown for group classes
            $groupDetails = $groupClasses->map(fn($oc) => [
                'id' => $oc->id,
                'date' => $oc->starts_at->format('Y-m-d'),
                'time' => $oc->starts_at->format('H:i') . ' - ' . $oc->ends_at->format('H:i'),
                'class_name' => $oc->template?->name ?? 'Ismeretlen óra',
                'room' => $oc->room?->name ?? '-',
                'participants' => $oc->current_participants ?? 0,
                'entry_fee' => $oc->entry_fee_brutto ?? $oc->template?->entry_fee_brutto ?? 0,
                'trainer_fee' => $oc->trainer_fee_brutto ?? $oc->template?->trainer_fee_brutto ?? 0,
            ]);

            $totalEntryFeeAll = $totalEntryFee + $groupEntryFee;
            $totalTrainerFeeAll = $totalTrainerFee + $groupTrainerFee;
            $totalRevenue = $totalEntryFeeAll + $totalTrainerFeeAll;

            return [
                'staff_id' => $staff->id,
                'name' => $staff->user?->name ?? 'Ismeretlen',
                'entry_fee' => $totalEntryFeeAll,
                'trainer_fee' => $totalTrainerFeeAll,
                'individual_count' => $individualCount,
                'group_count' => $groupCount,
                'total_revenue' => $totalRevenue,
                // Detailed breakdown
                'individual_sessions' => $individualDetails,
                'group_sessions' => $groupDetails,
            ];
        })->filter(fn($s) => $s['individual_count'] > 0 || $s['group_count'] > 0)->values();

        $totalEntryFee = $payoutData->sum('entry_fee');
        $totalTrainerFee = $payoutData->sum('trainer_fee');
        $totalRevenue = $payoutData->sum('total_revenue');

        return ApiResponse::success([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_entry_fee' => $totalEntryFee,
                'total_trainer_fee' => $totalTrainerFee,
                'total_revenue' => $totalRevenue,
                'staff_count' => $payoutData->count(),
                'currency' => 'HUF',
            ],
            'staff_payouts' => $payoutData,
        ]);
    }

    /**
     * Get revenue report (pass purchases)
     *
     * GET /api/admin/reports/revenue
     */
    public function revenue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        // Convert to Carbon instances with proper start/end of day
        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $passes = Pass::whereBetween('purchased_at', [$dateFrom, $dateTo])->get();

        $totalRevenue = $passes->sum('price');
        $totalPasses = $passes->count();

        $byStatus = [
            'active' => $passes->where('status', 'active')->count(),
            'expired' => $passes->where('status', 'expired')->count(),
            'fully_used' => $passes->where('status', 'fully_used')->count(),
        ];

        return ApiResponse::success([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_passes_sold' => $totalPasses,
                'average_pass_price' => $totalPasses > 0 ? round($totalRevenue / $totalPasses, 2) : 0,
                'currency' => 'HUF',
            ],
            'by_status' => $byStatus,
        ]);
    }

    /**
     * Get utilization report (room and staff usage)
     *
     * GET /api/admin/reports/utilization
     */
    public function utilization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        // Convert to Carbon instances with proper start/end of day
        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        // Room utilization
        $totalEvents = Event::whereBetween('starts_at', [$dateFrom, $dateTo])->count();
        $totalClasses = ClassOccurrence::whereBetween('starts_at', [$dateFrom, $dateTo])->count();

        // Staff utilization
        $staffMembers = StaffProfile::with('user')->get();
        $staffUtilization = $staffMembers->map(function ($staff) use ($dateFrom, $dateTo) {
            $eventCount = Event::where('staff_id', $staff->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->count();

            $classCount = ClassOccurrence::where('trainer_id', $staff->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->count();

            return [
                'staff_id' => $staff->id,
                'name' => $staff->user->name,
                'individual_sessions' => $eventCount,
                'group_classes' => $classCount,
                'total_sessions' => $eventCount + $classCount,
            ];
        })->sortByDesc('total_sessions')->values();

        return ApiResponse::success([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_individual_sessions' => $totalEvents,
                'total_group_classes' => $totalClasses,
                'total_sessions' => $totalEvents + $totalClasses,
            ],
            'staff_utilization' => $staffUtilization,
        ]);
    }

    /**
     * Get client activity report
     *
     * GET /api/admin/reports/clients
     */
    public function clients(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        // Convert to Carbon instances with proper start/end of day
        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $clients = Client::with('user')->get();

        $clientData = $clients->map(function ($client) use ($dateFrom, $dateTo) {
            // Skip clients without associated user
            if (!$client->user) {
                return null;
            }

            $classRegistrations = ClassRegistration::with(['occurrence.template', 'occurrence.room', 'occurrence.trainer.user'])
                ->where('client_id', $client->id)
                ->whereHas('occurrence', function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('starts_at', [$dateFrom, $dateTo]);
                })
                ->get();

            $individualEvents = Event::with(['staff.user', 'room', 'serviceType'])
                ->where('client_id', $client->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->orderBy('starts_at')
                ->get();

            $totalSessions = $classRegistrations->count() + $individualEvents->count();
            $attended = $classRegistrations->where('attendance_status', 'attended')->count() +
                       $individualEvents->where('attendance_status', 'attended')->count();

            // Detailed breakdown for individual sessions
            $individualDetails = $individualEvents->map(fn($e) => [
                'id' => $e->id,
                'type' => 'individual',
                'date' => $e->starts_at->format('Y-m-d'),
                'time' => $e->starts_at->format('H:i') . ' - ' . $e->ends_at->format('H:i'),
                'trainer_name' => $e->staff?->user?->name ?? '-',
                'service_type' => $e->serviceType?->name ?? '-',
                'room' => $e->room?->name ?? '-',
                'entry_fee' => $e->entry_fee_brutto ?? 0,
                'trainer_fee' => $e->trainer_fee_brutto ?? 0,
                'total_fee' => ($e->entry_fee_brutto ?? 0) + ($e->trainer_fee_brutto ?? 0),
                'attendance_status' => $e->attendance_status,
            ]);

            // Detailed breakdown for group class registrations
            $groupDetails = $classRegistrations->map(fn($reg) => [
                'id' => $reg->id,
                'type' => 'group',
                'date' => $reg->occurrence?->starts_at?->format('Y-m-d') ?? '-',
                'time' => $reg->occurrence ? $reg->occurrence->starts_at->format('H:i') . ' - ' . $reg->occurrence->ends_at->format('H:i') : '-',
                'trainer_name' => $reg->occurrence?->trainer?->user?->name ?? '-',
                'service_type' => $reg->occurrence?->template?->name ?? 'Csoportos óra',
                'room' => $reg->occurrence?->room?->name ?? '-',
                'entry_fee' => $reg->occurrence?->entry_fee_brutto ?? $reg->occurrence?->template?->entry_fee_brutto ?? 0,
                'trainer_fee' => $reg->occurrence?->trainer_fee_brutto ?? $reg->occurrence?->template?->trainer_fee_brutto ?? 0,
                'total_fee' => ($reg->occurrence?->entry_fee_brutto ?? $reg->occurrence?->template?->entry_fee_brutto ?? 0) +
                              ($reg->occurrence?->trainer_fee_brutto ?? $reg->occurrence?->template?->trainer_fee_brutto ?? 0),
                'attendance_status' => $reg->attendance_status,
            ])->sortBy('date')->values();

            // Combine and sort all sessions by date
            $allSessions = $individualDetails->concat($groupDetails)->sortBy('date')->values();

            return [
                'client_id' => $client->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
                'total_sessions' => $totalSessions,
                'attended' => $attended,
                'no_shows' => $classRegistrations->where('attendance_status', 'no_show')->count() +
                             $individualEvents->where('attendance_status', 'no_show')->count(),
                'sessions' => $allSessions,
            ];
        })->filter(fn($c) => $c !== null && $c['total_sessions'] > 0)->sortByDesc('total_sessions')->values();

        return ApiResponse::success([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_active_clients' => $clientData->count(),
            ],
            'clients' => $clientData,
        ]);
    }

    /**
     * Trainer Summary Report - Optimized SQL aggregation
     *
     * GET /api/v1/admin/reports/trainer-summary
     * Query params: date_from, date_to, trainer_id (optional), site_id (optional), room_id (optional), service_type_id (optional)
     *
     * Groups by: trainer_id + room
     * Returns: total_trainer_fee_brutto, total_entry_fee_brutto, total_hours, total_sessions
     */
    public function trainerSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'trainer_id' => ['sometimes', 'integer', 'exists:staff_profiles,id'],
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
            'room_id' => ['sometimes', 'integer', 'exists:rooms,id'],
            'service_type_id' => ['sometimes', 'integer', 'exists:service_types,id'],
        ]);

        // Convert to Carbon instances with Europe/Budapest timezone
        $filters = [
            'from' => Carbon::parse($validated['date_from'])->timezone('Europe/Budapest')->startOfDay(),
            'to' => Carbon::parse($validated['date_to'])->timezone('Europe/Budapest')->endOfDay(),
        ];

        // Add optional filters
        if (isset($validated['trainer_id'])) {
            $filters['trainer_id'] = $validated['trainer_id'];
        }
        if (isset($validated['site_id'])) {
            $filters['site_id'] = $validated['site_id'];
        }
        if (isset($validated['room_id'])) {
            $filters['room_id'] = $validated['room_id'];
        }
        if (isset($validated['service_type_id'])) {
            $filters['service_type_id'] = $validated['service_type_id'];
        }

        $data = $this->reportService->trainerSummary($filters);

        return ApiResponse::success([
            'period' => [
                'from' => $validated['date_from'],
                'to' => $validated['date_to'],
            ],
            'filters' => array_intersect_key($validated, array_flip(['trainer_id', 'site_id', 'room_id', 'service_type_id'])),
            'data' => $data,
            'summary' => [
                'total_trainer_fee_brutto' => $data->sum('total_trainer_fee_brutto'),
                'total_entry_fee_brutto' => $data->sum('total_entry_fee_brutto'),
                'total_hours' => round($data->sum('total_hours'), 2),
                'total_sessions' => $data->sum('total_sessions'),
                'currency' => 'HUF',
            ],
        ], 'Trainer summary report generated');
    }

    /**
     * Site-Client-List Report - Optimized SQL aggregation
     *
     * GET /api/v1/admin/reports/site-client-list
     * Query params: date_from, date_to, site_id (optional), room_id (optional)
     *
     * Groups by: client_id
     * Returns: client name/email, total_entry_fee_brutto, total_hours, services_breakdown
     */
    public function siteClientList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
            'room_id' => ['sometimes', 'integer', 'exists:rooms,id'],
        ]);

        // Convert to Carbon instances with Europe/Budapest timezone
        $filters = [
            'from' => Carbon::parse($validated['date_from'])->timezone('Europe/Budapest')->startOfDay(),
            'to' => Carbon::parse($validated['date_to'])->timezone('Europe/Budapest')->endOfDay(),
        ];

        // Add optional filters
        if (isset($validated['site_id'])) {
            $filters['site_id'] = $validated['site_id'];
        }
        if (isset($validated['room_id'])) {
            $filters['room_id'] = $validated['room_id'];
        }

        $data = $this->reportService->siteClientList($filters);

        return ApiResponse::success([
            'period' => [
                'from' => $validated['date_from'],
                'to' => $validated['date_to'],
            ],
            'filters' => array_intersect_key($validated, array_flip(['site_id', 'room_id'])),
            'data' => $data,
            'summary' => [
                'total_clients' => $data->count(),
                'total_entry_fee_brutto' => $data->sum('total_entry_fee_brutto'),
                'total_hours' => round($data->sum('total_hours'), 2),
                'total_sessions' => $data->sum('total_sessions'),
                'currency' => 'HUF',
            ],
        ], 'Site client list report generated');
    }

    /**
     * Trends Report - Time-series KPIs with week/month granularity
     *
     * GET /api/v1/admin/reports/trends
     * Query params: date_from, date_to, granularity (week|month), trainer_id (optional), site_id (optional)
     *
     * Returns: time-series data with sessions, hours, entry_fee, trainer_fee, no_show_ratio
     */
    public function trends(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'granularity' => ['required', 'in:week,month'],
            'trainer_id' => ['sometimes', 'integer', 'exists:staff_profiles,id'],
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
        ]);

        // Convert to Carbon instances with Europe/Budapest timezone
        $filters = [
            'from' => Carbon::parse($validated['date_from'])->timezone('Europe/Budapest')->startOfDay(),
            'to' => Carbon::parse($validated['date_to'])->timezone('Europe/Budapest')->endOfDay(),
            'granularity' => $validated['granularity'],
        ];

        // Add optional filters
        if (isset($validated['trainer_id'])) {
            $filters['trainer_id'] = $validated['trainer_id'];
        }
        if (isset($validated['site_id'])) {
            $filters['site_id'] = $validated['site_id'];
        }

        $data = $this->reportService->trends($filters);

        return ApiResponse::success([
            'period' => [
                'from' => $validated['date_from'],
                'to' => $validated['date_to'],
            ],
            'granularity' => $validated['granularity'],
            'filters' => array_intersect_key($validated, array_flip(['trainer_id', 'site_id'])),
            'data' => $data,
            'summary' => [
                'total_sessions' => $data->sum('total_sessions'),
                'total_hours' => round($data->sum('total_hours'), 2),
                'total_entry_fee' => $data->sum('total_entry_fee'),
                'total_trainer_fee' => $data->sum('total_trainer_fee'),
                'avg_no_show_ratio' => $data->count() > 0 ? round($data->avg('no_show_ratio'), 2) : 0,
                'avg_attendance_rate' => $data->count() > 0 ? round($data->avg('attendance_rate'), 2) : 0,
                'currency' => 'HUF',
            ],
        ], 'Trends report generated');
    }

    /**
     * Drilldown Report - Trainer → Site → Client → Item-level list
     *
     * GET /api/v1/admin/reports/drilldown
     * Query params: date_from, date_to, trainer_id (required), site_id (optional), client_id (optional)
     *
     * Returns: detailed session list for a specific trainer
     */
    public function drilldown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'trainer_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
        ]);

        // Convert to Carbon instances with Europe/Budapest timezone
        $filters = [
            'from' => Carbon::parse($validated['date_from'])->timezone('Europe/Budapest')->startOfDay(),
            'to' => Carbon::parse($validated['date_to'])->timezone('Europe/Budapest')->endOfDay(),
            'trainer_id' => $validated['trainer_id'],
        ];

        // Add optional filters
        if (isset($validated['site_id'])) {
            $filters['site_id'] = $validated['site_id'];
        }
        if (isset($validated['client_id'])) {
            $filters['client_id'] = $validated['client_id'];
        }

        $data = $this->reportService->drilldownTrainerSessions($filters);

        return ApiResponse::success([
            'period' => [
                'from' => $validated['date_from'],
                'to' => $validated['date_to'],
            ],
            'filters' => $validated,
            'data' => $data,
            'summary' => [
                'total_sessions' => $data->count(),
                'total_hours' => round($data->sum('hours'), 2),
                'total_entry_fee_brutto' => $data->sum('entry_fee_brutto'),
                'total_trainer_fee_brutto' => $data->sum('trainer_fee_brutto'),
                'currency' => 'HUF',
            ],
        ], 'Drilldown report generated');
    }

    /**
     * Export payouts report to XLSX - itemized by session
     */
    public function exportPayouts(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $staffMembers = StaffProfile::with('user')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kifizetések');

        // Header row - itemized columns
        $headers = ['Edző', 'Típus', 'Dátum', 'Időpont', 'Ügyfél / Óra neve', 'Terem', 'Belépődíj (HUF)', 'Edzői díj (HUF)', 'Összesen (HUF)', 'Állapot'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Style header
        $this->styleHeaderRow($sheet, 'A1:J1');

        $row = 2;
        $totalEntryFee = 0;
        $totalTrainerFee = 0;

        foreach ($staffMembers as $staff) {
            $staffName = $staff->user?->name ?? 'Ismeretlen';

            // Individual events (1:1)
            $individualEvents = Event::with(['client.user', 'room', 'serviceType'])
                ->where('staff_id', $staff->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->orderBy('starts_at')
                ->get();

            foreach ($individualEvents as $event) {
                $entryFee = $event->entry_fee_brutto ?? 0;
                $trainerFee = $event->trainer_fee_brutto ?? 0;

                $sheet->setCellValue('A' . $row, $staffName);
                $sheet->setCellValue('B' . $row, '1:1 Edzés');
                $sheet->setCellValue('C' . $row, $event->starts_at->format('Y-m-d'));
                $sheet->setCellValue('D' . $row, $event->starts_at->format('H:i') . ' - ' . $event->ends_at->format('H:i'));
                $sheet->setCellValue('E' . $row, $event->client?->user?->name ?? '-');
                $sheet->setCellValue('F' . $row, $event->room?->name ?? '-');
                $sheet->setCellValue('G' . $row, $entryFee);
                $sheet->setCellValue('H' . $row, $trainerFee);
                $sheet->setCellValue('I' . $row, $entryFee + $trainerFee);
                $sheet->setCellValue('J' . $row, $this->translateAttendanceStatus($event->attendance_status));
                $row++;

                $totalEntryFee += $entryFee;
                $totalTrainerFee += $trainerFee;
            }

            // Group classes
            $groupClasses = ClassOccurrence::with(['template', 'room'])
                ->where('trainer_id', $staff->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->orderBy('starts_at')
                ->get();

            foreach ($groupClasses as $oc) {
                $entryFee = $oc->entry_fee_brutto ?? $oc->template?->entry_fee_brutto ?? 0;
                $trainerFee = $oc->trainer_fee_brutto ?? $oc->template?->trainer_fee_brutto ?? 0;

                $sheet->setCellValue('A' . $row, $staffName);
                $sheet->setCellValue('B' . $row, 'Csoportos óra');
                $sheet->setCellValue('C' . $row, $oc->starts_at->format('Y-m-d'));
                $sheet->setCellValue('D' . $row, $oc->starts_at->format('H:i') . ' - ' . $oc->ends_at->format('H:i'));
                $sheet->setCellValue('E' . $row, $oc->template?->name ?? 'Ismeretlen óra');
                $sheet->setCellValue('F' . $row, $oc->room?->name ?? '-');
                $sheet->setCellValue('G' . $row, $entryFee);
                $sheet->setCellValue('H' . $row, $trainerFee);
                $sheet->setCellValue('I' . $row, $entryFee + $trainerFee);
                $sheet->setCellValue('J' . $row, ($oc->current_participants ?? 0) . ' résztvevő');
                $row++;

                $totalEntryFee += $entryFee;
                $totalTrainerFee += $trainerFee;
            }
        }

        // Summary row
        $sheet->setCellValue('A' . $row, 'Összesen');
        $sheet->setCellValue('G' . $row, $totalEntryFee);
        $sheet->setCellValue('H' . $row, $totalTrainerFee);
        $sheet->setCellValue('I' . $row, $totalEntryFee + $totalTrainerFee);
        $this->styleSummaryRow($sheet, 'A' . $row . ':J' . $row);

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadXlsx($spreadsheet, 'kifizetes_riport_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export clients report to XLSX - itemized by session
     */
    public function exportClients(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $clients = Client::with('user')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ügyfelek');

        // Header row - itemized columns (no summary columns)
        $headers = ['Ügyfél', 'Email', 'Típus', 'Dátum', 'Időpont', 'Edző', 'Szolgáltatás', 'Terem', 'Belépődíj (HUF)', 'Edzői díj (HUF)', 'Összesen (HUF)', 'Állapot'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Style header
        $this->styleHeaderRow($sheet, 'A1:L1');

        $row = 2;
        $totalEntryFee = 0;
        $totalTrainerFee = 0;

        foreach ($clients as $client) {
            if (!$client->user) {
                continue;
            }

            $clientName = $client->user->name;
            $clientEmail = $client->user->email;

            // Individual events (1:1)
            $individualEvents = Event::with(['staff.user', 'room', 'serviceType'])
                ->where('client_id', $client->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->orderBy('starts_at')
                ->get();

            foreach ($individualEvents as $event) {
                $entryFee = $event->entry_fee_brutto ?? 0;
                $trainerFee = $event->trainer_fee_brutto ?? 0;

                $sheet->setCellValue('A' . $row, $clientName);
                $sheet->setCellValue('B' . $row, $clientEmail);
                $sheet->setCellValue('C' . $row, '1:1 Edzés');
                $sheet->setCellValue('D' . $row, $event->starts_at->format('Y-m-d'));
                $sheet->setCellValue('E' . $row, $event->starts_at->format('H:i') . ' - ' . $event->ends_at->format('H:i'));
                $sheet->setCellValue('F' . $row, $event->staff?->user?->name ?? '-');
                $sheet->setCellValue('G' . $row, $event->serviceType?->name ?? '-');
                $sheet->setCellValue('H' . $row, $event->room?->name ?? '-');
                $sheet->setCellValue('I' . $row, $entryFee);
                $sheet->setCellValue('J' . $row, $trainerFee);
                $sheet->setCellValue('K' . $row, $entryFee + $trainerFee);
                $sheet->setCellValue('L' . $row, $this->translateAttendanceStatus($event->attendance_status));
                $row++;

                $totalEntryFee += $entryFee;
                $totalTrainerFee += $trainerFee;
            }

            // Group class registrations
            $classRegistrations = ClassRegistration::with(['occurrence.template', 'occurrence.room', 'occurrence.trainer.user'])
                ->where('client_id', $client->id)
                ->whereHas('occurrence', function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('starts_at', [$dateFrom, $dateTo]);
                })
                ->get();

            foreach ($classRegistrations as $reg) {
                $entryFee = $reg->occurrence?->entry_fee_brutto ?? $reg->occurrence?->template?->entry_fee_brutto ?? 0;
                $trainerFee = $reg->occurrence?->trainer_fee_brutto ?? $reg->occurrence?->template?->trainer_fee_brutto ?? 0;

                $sheet->setCellValue('A' . $row, $clientName);
                $sheet->setCellValue('B' . $row, $clientEmail);
                $sheet->setCellValue('C' . $row, 'Csoportos óra');
                $sheet->setCellValue('D' . $row, $reg->occurrence?->starts_at?->format('Y-m-d') ?? '-');
                $sheet->setCellValue('E' . $row, $reg->occurrence ? $reg->occurrence->starts_at->format('H:i') . ' - ' . $reg->occurrence->ends_at->format('H:i') : '-');
                $sheet->setCellValue('F' . $row, $reg->occurrence?->trainer?->user?->name ?? '-');
                $sheet->setCellValue('G' . $row, $reg->occurrence?->template?->name ?? 'Csoportos óra');
                $sheet->setCellValue('H' . $row, $reg->occurrence?->room?->name ?? '-');
                $sheet->setCellValue('I' . $row, $entryFee);
                $sheet->setCellValue('J' . $row, $trainerFee);
                $sheet->setCellValue('K' . $row, $entryFee + $trainerFee);
                $sheet->setCellValue('L' . $row, $this->translateAttendanceStatus($reg->attendance_status));
                $row++;

                $totalEntryFee += $entryFee;
                $totalTrainerFee += $trainerFee;
            }
        }

        // Summary row
        $sheet->setCellValue('A' . $row, 'Összesen');
        $sheet->setCellValue('I' . $row, $totalEntryFee);
        $sheet->setCellValue('J' . $row, $totalTrainerFee);
        $sheet->setCellValue('K' . $row, $totalEntryFee + $totalTrainerFee);
        $this->styleSummaryRow($sheet, 'A' . $row . ':L' . $row);

        // Auto-size columns
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadXlsx($spreadsheet, 'ugyfel_riport_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export attendance report to XLSX
     */
    public function exportAttendance(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $individualEvents = Event::whereBetween('starts_at', [$dateFrom, $dateTo])->get();
        $classRegistrations = ClassRegistration::whereHas('occurrence', function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('starts_at', [$dateFrom, $dateTo]);
        })->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jelenlét');

        // Header row
        $headers = ['Típus', 'Összes', 'Megjelent', 'Nem jelent meg', 'Jelenlét %', 'Hiányzás %'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $this->styleHeaderRow($sheet, 'A1:F1');

        // Individual events
        $indivAttended = $individualEvents->where('attendance_status', 'attended')->count();
        $indivNoShows = $individualEvents->where('attendance_status', 'no_show')->count();
        $indivTotal = $individualEvents->count();

        $sheet->setCellValue('A2', 'Egyéni alkalmak');
        $sheet->setCellValue('B2', $indivTotal);
        $sheet->setCellValue('C2', $indivAttended);
        $sheet->setCellValue('D2', $indivNoShows);
        $sheet->setCellValue('E2', $indivTotal > 0 ? round(($indivAttended / $indivTotal) * 100, 2) . '%' : '0%');
        $sheet->setCellValue('F2', $indivTotal > 0 ? round(($indivNoShows / $indivTotal) * 100, 2) . '%' : '0%');

        // Group classes
        $classAttended = $classRegistrations->where('attendance_status', 'attended')->count();
        $classNoShows = $classRegistrations->where('attendance_status', 'no_show')->count();
        $classTotal = $classRegistrations->count();

        $sheet->setCellValue('A3', 'Csoportos órák');
        $sheet->setCellValue('B3', $classTotal);
        $sheet->setCellValue('C3', $classAttended);
        $sheet->setCellValue('D3', $classNoShows);
        $sheet->setCellValue('E3', $classTotal > 0 ? round(($classAttended / $classTotal) * 100, 2) . '%' : '0%');
        $sheet->setCellValue('F3', $classTotal > 0 ? round(($classNoShows / $classTotal) * 100, 2) . '%' : '0%');

        // Summary
        $totalAll = $indivTotal + $classTotal;
        $totalAttended = $indivAttended + $classAttended;
        $totalNoShows = $indivNoShows + $classNoShows;

        $sheet->setCellValue('A4', 'Összesen');
        $sheet->setCellValue('B4', $totalAll);
        $sheet->setCellValue('C4', $totalAttended);
        $sheet->setCellValue('D4', $totalNoShows);
        $sheet->setCellValue('E4', $totalAll > 0 ? round(($totalAttended / $totalAll) * 100, 2) . '%' : '0%');
        $sheet->setCellValue('F4', $totalAll > 0 ? round(($totalNoShows / $totalAll) * 100, 2) . '%' : '0%');

        $this->styleSummaryRow($sheet, 'A4:F4');

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadXlsx($spreadsheet, 'jelenlet_riport_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export revenue report to XLSX
     */
    public function exportRevenue(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $passes = Pass::whereBetween('purchased_at', [$dateFrom, $dateTo])->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bevétel');

        // Summary section
        $sheet->setCellValue('A1', 'Bevétel riport');
        $sheet->setCellValue('A2', 'Időszak: ' . $dateFrom->format('Y-m-d') . ' - ' . $dateTo->format('Y-m-d'));

        $sheet->setCellValue('A4', 'Összesítés');
        $this->styleHeaderRow($sheet, 'A4:B4');

        $sheet->setCellValue('A5', 'Összes bevétel (HUF)');
        $sheet->setCellValue('B5', $passes->sum('price'));
        $sheet->setCellValue('A6', 'Eladott bérletek');
        $sheet->setCellValue('B6', $passes->count());
        $sheet->setCellValue('A7', 'Átlagos bérlet ár');
        $sheet->setCellValue('B7', $passes->count() > 0 ? round($passes->sum('price') / $passes->count(), 2) : 0);

        $sheet->setCellValue('A9', 'Státusz szerinti bontás');
        $this->styleHeaderRow($sheet, 'A9:B9');

        $sheet->setCellValue('A10', 'Aktív');
        $sheet->setCellValue('B10', $passes->where('status', 'active')->count());
        $sheet->setCellValue('A11', 'Lejárt');
        $sheet->setCellValue('B11', $passes->where('status', 'expired')->count());
        $sheet->setCellValue('A12', 'Felhasznált');
        $sheet->setCellValue('B12', $passes->where('status', 'fully_used')->count());

        foreach (range('A', 'B') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadXlsx($spreadsheet, 'bevetel_riport_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export utilization report to XLSX
     */
    public function exportUtilization(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        $staffMembers = StaffProfile::with('user')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kihasználtság');

        $headers = ['Edző', 'Egyéni alkalmak', 'Csoportos órák', 'Összesen'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        $this->styleHeaderRow($sheet, 'A1:D1');

        $row = 2;
        $totalIndiv = 0;
        $totalGroup = 0;

        foreach ($staffMembers as $staff) {
            $eventCount = Event::where('staff_id', $staff->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->count();

            $classCount = ClassOccurrence::where('trainer_id', $staff->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->count();

            if ($eventCount > 0 || $classCount > 0) {
                $sheet->setCellValue('A' . $row, $staff->user->name);
                $sheet->setCellValue('B' . $row, $eventCount);
                $sheet->setCellValue('C' . $row, $classCount);
                $sheet->setCellValue('D' . $row, $eventCount + $classCount);
                $row++;

                $totalIndiv += $eventCount;
                $totalGroup += $classCount;
            }
        }

        $sheet->setCellValue('A' . $row, 'Összesen');
        $sheet->setCellValue('B' . $row, $totalIndiv);
        $sheet->setCellValue('C' . $row, $totalGroup);
        $sheet->setCellValue('D' . $row, $totalIndiv + $totalGroup);

        $this->styleSummaryRow($sheet, 'A' . $row . ':D' . $row);

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadXlsx($spreadsheet, 'kihasznaltsag_riport_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d') . '.xlsx');
    }

    /**
     * Export per-client payouts report to XLSX with 2 worksheets
     * (Summary + Detailed items)
     */
    public function exportPayoutsPerClient(Request $request): Response
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
        $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

        // Fetch all events in range with relations
        $events = Event::with(['client.user', 'additionalClients.user', 'staff.user', 'room', 'serviceType'])
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->orderBy('starts_at')
            ->get();

        // Build per-client data: client_id => [name, email, sessions[], totals]
        $clientData = [];

        foreach ($events as $event) {
            // Main client (events.client_id)
            if ($event->client_id && $event->client) {
                $clientId = $event->client_id;
                if (!isset($clientData[$clientId])) {
                    $clientData[$clientId] = [
                        'name' => $event->client->user?->name ?? 'Ismeretlen',
                        'email' => $event->client->user?->email ?? '-',
                        'total_entry_fee' => 0,
                        'total_trainer_fee' => 0,
                        'session_count' => 0,
                        'details' => [],
                    ];
                }

                $entryFee = $event->entry_fee_brutto ?? 0;
                $trainerFee = $event->trainer_fee_brutto ?? 0;

                $clientData[$clientId]['total_entry_fee'] += $entryFee;
                $clientData[$clientId]['total_trainer_fee'] += $trainerFee;
                $clientData[$clientId]['session_count']++;
                $clientData[$clientId]['details'][] = [
                    'date' => $event->starts_at->format('Y-m-d'),
                    'time' => $event->starts_at->format('H:i') . ' - ' . $event->ends_at->format('H:i'),
                    'trainer' => $event->staff?->user?->name ?? '-',
                    'type' => '1:1 Edzés',
                    'service' => $event->serviceType?->name ?? '-',
                    'room' => $event->room?->name ?? '-',
                    'entry_fee' => $entryFee,
                    'trainer_fee' => $trainerFee,
                    'status' => $this->translateAttendanceStatus($event->attendance_status),
                ];
            }

            // Additional clients from pivot table
            foreach ($event->additionalClients as $addClient) {
                $clientId = $addClient->id;
                if (!isset($clientData[$clientId])) {
                    $clientData[$clientId] = [
                        'name' => $addClient->user?->name ?? 'Ismeretlen',
                        'email' => $addClient->user?->email ?? '-',
                        'total_entry_fee' => 0,
                        'total_trainer_fee' => 0,
                        'session_count' => 0,
                        'details' => [],
                    ];
                }

                $entryFee = $addClient->pivot->entry_fee_brutto ?? 0;
                $trainerFee = $addClient->pivot->trainer_fee_brutto ?? 0;

                $clientData[$clientId]['total_entry_fee'] += $entryFee;
                $clientData[$clientId]['total_trainer_fee'] += $trainerFee;
                $clientData[$clientId]['session_count']++;
                $clientData[$clientId]['details'][] = [
                    'date' => $event->starts_at->format('Y-m-d'),
                    'time' => $event->starts_at->format('H:i') . ' - ' . $event->ends_at->format('H:i'),
                    'trainer' => $event->staff?->user?->name ?? '-',
                    'type' => '1:1 Edzés (vendég)',
                    'service' => $event->serviceType?->name ?? '-',
                    'room' => $event->room?->name ?? '-',
                    'entry_fee' => $entryFee,
                    'trainer_fee' => $trainerFee,
                    'status' => $this->translateAttendanceStatus($addClient->pivot->attendance_status ?? null),
                ];
            }
        }

        // Sort by client name
        uasort($clientData, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        // Create spreadsheet with 2 worksheets
        $spreadsheet = new Spreadsheet();

        // ---- Sheet 1: Összesítő (Summary) ----
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Összesítő');

        $summaryHeaders = ['Vendég neve', 'Email', 'Alkalmak száma', 'Belépődíj összesen (HUF)', 'Edzői díj összesen (HUF)', 'Összesen (HUF)'];
        $col = 'A';
        foreach ($summaryHeaders as $header) {
            $summarySheet->setCellValue($col . '1', $header);
            $col++;
        }
        $this->styleHeaderRow($summarySheet, 'A1:F1');

        $row = 2;
        $grandTotalEntry = 0;
        $grandTotalTrainer = 0;
        $grandTotalSessions = 0;

        foreach ($clientData as $data) {
            $total = $data['total_entry_fee'] + $data['total_trainer_fee'];
            $summarySheet->setCellValue('A' . $row, $data['name']);
            $summarySheet->setCellValue('B' . $row, $data['email']);
            $summarySheet->setCellValue('C' . $row, $data['session_count']);
            $summarySheet->setCellValue('D' . $row, $data['total_entry_fee']);
            $summarySheet->setCellValue('E' . $row, $data['total_trainer_fee']);
            $summarySheet->setCellValue('F' . $row, $total);
            $row++;

            $grandTotalEntry += $data['total_entry_fee'];
            $grandTotalTrainer += $data['total_trainer_fee'];
            $grandTotalSessions += $data['session_count'];
        }

        // Summary row
        $summarySheet->setCellValue('A' . $row, 'Összesen');
        $summarySheet->setCellValue('C' . $row, $grandTotalSessions);
        $summarySheet->setCellValue('D' . $row, $grandTotalEntry);
        $summarySheet->setCellValue('E' . $row, $grandTotalTrainer);
        $summarySheet->setCellValue('F' . $row, $grandTotalEntry + $grandTotalTrainer);
        $this->styleSummaryRow($summarySheet, 'A' . $row . ':F' . $row);

        foreach (range('A', 'F') as $c) {
            $summarySheet->getColumnDimension($c)->setAutoSize(true);
        }

        // ---- Sheet 2: Részletes tételek (Detailed items) ----
        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Részletes tételek');

        $detailHeaders = ['Vendég neve', 'Dátum', 'Időpont', 'Edző', 'Típus', 'Szolgáltatás', 'Terem', 'Belépődíj (HUF)', 'Edzői díj (HUF)', 'Összesen (HUF)', 'Státusz'];
        $col = 'A';
        foreach ($detailHeaders as $header) {
            $detailSheet->setCellValue($col . '1', $header);
            $col++;
        }
        $this->styleHeaderRow($detailSheet, 'A1:K1');

        $row = 2;
        $detailTotalEntry = 0;
        $detailTotalTrainer = 0;

        foreach ($clientData as $data) {
            // Sort details by date
            $details = $data['details'];
            usort($details, fn($a, $b) => strcmp($a['date'], $b['date']));

            foreach ($details as $detail) {
                $detailSheet->setCellValue('A' . $row, $data['name']);
                $detailSheet->setCellValue('B' . $row, $detail['date']);
                $detailSheet->setCellValue('C' . $row, $detail['time']);
                $detailSheet->setCellValue('D' . $row, $detail['trainer']);
                $detailSheet->setCellValue('E' . $row, $detail['type']);
                $detailSheet->setCellValue('F' . $row, $detail['service']);
                $detailSheet->setCellValue('G' . $row, $detail['room']);
                $detailSheet->setCellValue('H' . $row, $detail['entry_fee']);
                $detailSheet->setCellValue('I' . $row, $detail['trainer_fee']);
                $detailSheet->setCellValue('J' . $row, $detail['entry_fee'] + $detail['trainer_fee']);
                $detailSheet->setCellValue('K' . $row, $detail['status']);
                $row++;

                $detailTotalEntry += $detail['entry_fee'];
                $detailTotalTrainer += $detail['trainer_fee'];
            }
        }

        // Summary row
        $detailSheet->setCellValue('A' . $row, 'Összesen');
        $detailSheet->setCellValue('H' . $row, $detailTotalEntry);
        $detailSheet->setCellValue('I' . $row, $detailTotalTrainer);
        $detailSheet->setCellValue('J' . $row, $detailTotalEntry + $detailTotalTrainer);
        $this->styleSummaryRow($detailSheet, 'A' . $row . ':K' . $row);

        foreach (range('A', 'K') as $c) {
            $detailSheet->getColumnDimension($c)->setAutoSize(true);
        }

        // Set active sheet back to summary
        $spreadsheet->setActiveSheetIndex(0);

        return $this->downloadXlsx($spreadsheet, 'vendeg_kifizetes_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d') . '.xlsx');
    }

    /**
     * Style header row
     */
    private function styleHeaderRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    /**
     * Style summary row
     */
    private function styleSummaryRow($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2EFDA'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
    }

    /**
     * Generate XLSX download response
     */
    private function downloadXlsx(Spreadsheet $spreadsheet, string $filename): Response
    {
        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Translate attendance status to Hungarian
     */
    private function translateAttendanceStatus(?string $status): string
    {
        return match ($status) {
            'attended' => 'Megjelent',
            'no_show' => 'Nem jelent meg',
            default => 'Nem ellenőrzött',
        };
    }
}
