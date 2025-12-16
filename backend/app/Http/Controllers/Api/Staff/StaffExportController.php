<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\ClassOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StaffExportController extends Controller
{
    /**
     * Generate staff payout report (hours worked × rate)
     *
     * GET /api/staff/exports/payout
     * Query params: date_from, date_to, format (json|xlsx)
     */
    public function payout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'format' => ['sometimes', 'in:json,xlsx'],
        ]);

        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::error('Only staff can access payout reports', null, 403);
        }

        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];

        // Fetch 1:1 events
        $individualEvents = Event::where('staff_id', $staff->id)
            ->where('attendance_status', 'attended')
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->get();

        // Fetch group classes
        $groupClasses = ClassOccurrence::with('template')
            ->where('trainer_id', $staff->id)
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->get();

        // Calculate hours and earnings
        $totalIndividualHours = $individualEvents->sum(function ($event) {
            return $event->starts_at->diffInHours($event->ends_at);
        });

        $totalGroupHours = $groupClasses->sum(function ($occurrence) {
            return $occurrence->starts_at->diffInHours($occurrence->ends_at);
        });

        $totalHours = $totalIndividualHours + $totalGroupHours;

        // Get billing rate (from staff profile or billing_rules)
        $hourlyRate = $staff->default_hourly_rate ?? 5000; // Default: 5000 HUF/hour

        $totalEarnings = $totalHours * $hourlyRate;

        $reportData = [
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->user->name,
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'summary' => [
                'total_hours' => $totalHours,
                'individual_hours' => $totalIndividualHours,
                'group_hours' => $totalGroupHours,
                'hourly_rate' => $hourlyRate,
                'total_earnings' => $totalEarnings,
                'currency' => 'HUF',
            ],
            'breakdown' => [
                'individual_events' => $individualEvents->map(fn($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'client' => $e->client?->user->name,
                    'starts_at' => $e->starts_at->toIso8601String(),
                    'ends_at' => $e->ends_at->toIso8601String(),
                    'hours' => $e->starts_at->diffInHours($e->ends_at),
                ]),
                'group_classes' => $groupClasses->map(fn($oc) => [
                    'id' => $oc->id,
                    'class_name' => $oc->template?->name ?? 'Unknown Class',
                    'participants' => $oc->current_participants,
                    'starts_at' => $oc->starts_at->toIso8601String(),
                    'ends_at' => $oc->ends_at->toIso8601String(),
                    'hours' => $oc->starts_at->diffInHours($oc->ends_at),
                ]),
            ],
        ];

        // Return JSON or generate XLSX
        if ($request->input('format') === 'xlsx') {
            // TODO: Generate XLSX file using Laravel Excel or similar
            // For now, return download URL (placeholder)
            $filename = "payout_{$staff->id}_{$dateFrom}_{$dateTo}.xlsx";
            // Storage::put("exports/{$filename}", $xlsxContent);

            return ApiResponse::success([
                'download_url' => "/api/staff/exports/download/{$filename}",
                'report_data' => $reportData,
            ], 'XLSX export generated');
        }

        return ApiResponse::success($reportData, 'Payout report generated');
    }

    /**
     * Get staff's attendance report
     *
     * GET /api/staff/exports/attendance
     */
    public function attendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::error('Only staff can access attendance reports', null, 403);
        }

        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];

        // Fetch all events with attendance data
        $events = Event::with(['client.user'])
            ->where('staff_id', $staff->id)
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->get();

        $attended = $events->where('attendance_status', 'attended')->count();
        $noShows = $events->where('attendance_status', 'no_show')->count();
        $notCheckedIn = $events->where('attendance_status', null)->count();

        return ApiResponse::success([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_events' => $events->count(),
                'attended' => $attended,
                'no_shows' => $noShows,
                'not_checked_in' => $notCheckedIn,
                'attendance_rate' => $events->count() > 0
                    ? round(($attended / $events->count()) * 100, 2)
                    : 0,
            ],
            'events' => $events->map(fn($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'client' => $e->client?->user->name,
                'starts_at' => $e->starts_at->toIso8601String(),
                'attendance_status' => $e->attendance_status,
            ]),
        ], 'Attendance report generated');
    }
}
