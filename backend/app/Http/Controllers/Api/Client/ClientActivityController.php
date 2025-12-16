<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClassRegistration;
use App\Models\Event;
use App\Models\Pass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientActivityController extends Controller
{
    /**
     * Get client's activity history (classes + 1:1 events)
     *
     * GET /api/clients/{clientId}/activity
     */
    public function index(Request $request, int $clientId): JsonResponse
    {
        $user = $request->user();

        // Authorization: clients can only view their own activity
        if (!$user->isAdmin() && $user->client?->id !== $clientId) {
            return ApiResponse::forbidden('Cannot view other clients\' activity');
        }

        $dateFrom = $request->input('date_from', now()->subMonths(3)->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());
        $type = $request->input('type'); // 'class' or 'event'
        $attended = $request->input('attended'); // boolean filter

        // Fetch class registrations
        $classRegistrations = ClassRegistration::with(['occurrence.template', 'occurrence.room', 'occurrence.trainer.user'])
            ->where('client_id', $clientId)
            ->whereHas('occurrence', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('starts_at', [$dateFrom, $dateTo]);
            })
            ->orderBy('booked_at', 'desc')
            ->get();

        // Fetch 1:1 events
        $individualEvents = Event::with(['room', 'staff.user'])
            ->where('client_id', $clientId)
            ->where('type', 'individual')
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->orderBy('starts_at', 'desc')
            ->get();

        // Transform to unified activity items
        $activities = collect();

        // Add class registrations
        if (!$type || $type === 'class') {
            foreach ($classRegistrations as $registration) {
                $occurrence = $registration->occurrence;
                if (!$occurrence) continue;

                $isAttended = $registration->status === 'attended';
                $isNoShow = $registration->status === 'no_show';

                // Apply attended filter
                if ($attended !== null && $attended !== '' && $isAttended !== filter_var($attended, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $activities->push([
                    'id' => (string)$registration->id,
                    'type' => 'class',
                    'title' => $occurrence->template->title ?? 'Class',
                    'date' => $occurrence->starts_at->toDateString(),
                    'start_time' => $occurrence->starts_at->format('H:i'),
                    'end_time' => $occurrence->ends_at->format('H:i'),
                    'attended' => $isAttended ? true : ($isNoShow ? false : null),
                    'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
                    'trainer' => $occurrence->trainer->user->name ?? null,
                    'room' => $occurrence->room->name ?? null,
                    'credits_used' => $occurrence->template->credits_required ?? 1,
                    'notes' => null,
                ]);
            }
        }

        // Add individual events
        if (!$type || $type === 'event') {
            foreach ($individualEvents as $event) {
                $isAttended = $event->status === 'completed';
                $isNoShow = $event->status === 'no_show';

                // Apply attended filter
                if ($attended !== null && $attended !== '' && $isAttended !== filter_var($attended, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $activities->push([
                    'id' => (string)$event->id,
                    'type' => 'event',
                    'title' => $event->title ?? '1:1 Session',
                    'date' => $event->starts_at->toDateString(),
                    'start_time' => $event->starts_at->format('H:i'),
                    'end_time' => $event->ends_at->format('H:i'),
                    'attended' => $isAttended ? true : ($isNoShow ? false : null),
                    'checked_in_at' => $event->checked_in_at?->toIso8601String(),
                    'trainer' => $event->staff->user->name ?? null,
                    'room' => $event->room->name ?? null,
                    'credits_used' => 0, // 1:1 events don't use pass credits
                    'notes' => $event->notes,
                ]);
            }
        }

        // Calculate summary
        $totalSessions = $activities->count();
        $attendedSessions = $activities->where('attended', true)->count();
        $noShows = $activities->where('attended', false)->count();
        $totalCreditsUsed = $activities->sum('credits_used');
        $attendanceRate = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

        return ApiResponse::success([
            'activities' => $activities->values()->all(),
            'summary' => [
                'total_sessions' => $totalSessions,
                'attended_sessions' => $attendedSessions,
                'no_shows' => $noShows,
                'upcoming_sessions' => 0, // Not calculated in history view
                'total_credits_used' => $totalCreditsUsed,
                'attendance_rate' => round($attendanceRate, 2),
            ],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 1,
                'per_page' => $totalSessions,
                'total' => $totalSessions,
            ],
        ]);
    }

    /**
     * Get client's pass credits
     *
     * GET /api/clients/{clientId}/passes
     */
    public function passes(Request $request, int $clientId): JsonResponse
    {
        $user = $request->user();

        // Authorization: clients can only view their own passes
        if (!$user->isAdmin() && $user->client?->id !== $clientId) {
            return ApiResponse::forbidden('Cannot view other clients\' passes');
        }

        $passes = Pass::where('client_id', $clientId)
            ->orderBy('purchased_at', 'desc')
            ->get();

        $activePasses = $passes->where('status', 'active')->values();
        $expiredPasses = $passes->whereIn('status', ['expired', 'depleted'])->values();
        $totalCreditsRemaining = $activePasses->sum('credits_remaining') ?? 0;

        return ApiResponse::success([
            'active_passes' => $activePasses->all(),
            'expired_passes' => $expiredPasses->all(),
            'total_credits_remaining' => $totalCreditsRemaining,
        ]);
    }

    /**
     * Get client's upcoming bookings
     *
     * GET /api/clients/{clientId}/upcoming
     */
    public function upcoming(Request $request, int $clientId): JsonResponse
    {
        $user = $request->user();

        // Authorization: clients can only view their own bookings
        if (!$user->isAdmin() && $user->client?->id !== $clientId) {
            return ApiResponse::forbidden('Cannot view other clients\' bookings');
        }

        $upcomingBookings = collect();

        // Upcoming class registrations
        $upcomingClasses = ClassRegistration::with(['occurrence.template', 'occurrence.room', 'occurrence.trainer.user'])
            ->where('client_id', $clientId)
            ->whereIn('status', ['booked', 'waitlist'])
            ->whereHas('occurrence', function ($query) {
                $query->where('starts_at', '>', now())
                      ->where('status', 'scheduled');
            })
            ->get();

        foreach ($upcomingClasses as $registration) {
            $occurrence = $registration->occurrence;
            if (!$occurrence) continue;

            // Calculate cancellation deadline (24 hours before)
            $cancellationDeadline = $occurrence->starts_at->copy()->subHours(24);
            $canCancel = now()->isBefore($cancellationDeadline);

            $upcomingBookings->push([
                'id' => (string)$registration->id,
                'occurrence_id' => (string)$occurrence->id,
                'type' => 'class',
                'title' => $occurrence->template->title ?? 'Class',
                'starts_at' => $occurrence->starts_at->toIso8601String(),
                'ends_at' => $occurrence->ends_at->toIso8601String(),
                'trainer' => $occurrence->trainer->user->name ?? null,
                'room' => $occurrence->room->name ?? null,
                'can_cancel' => $canCancel,
                'cancellation_deadline' => $cancellationDeadline->toIso8601String(),
            ]);
        }

        // Upcoming 1:1 events
        $upcomingEvents = Event::with(['room', 'staff.user'])
            ->where('client_id', $clientId)
            ->where('type', 'individual')
            ->where('starts_at', '>', now())
            ->get();

        foreach ($upcomingEvents as $event) {
            // Calculate cancellation deadline (24 hours before)
            $cancellationDeadline = $event->starts_at->copy()->subHours(24);
            $canCancel = now()->isBefore($cancellationDeadline);

            $upcomingBookings->push([
                'id' => (string)$event->id,
                'type' => 'event',
                'title' => $event->title ?? '1:1 Session',
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at->toIso8601String(),
                'trainer' => $event->staff->user->name ?? null,
                'room' => $event->room->name ?? null,
                'can_cancel' => $canCancel,
                'cancellation_deadline' => $cancellationDeadline->toIso8601String(),
            ]);
        }

        // Sort by start time
        $upcomingBookings = $upcomingBookings->sortBy('starts_at')->values();

        return ApiResponse::success($upcomingBookings->all());
    }
}
