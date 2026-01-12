<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Client;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\Event;
use App\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics.
     *
     * GET /api/v1/dashboard/stats
     */
    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        // Get staff profile if user is staff/admin
        $staffProfile = null;
        if (in_array($user->role, ['staff', 'admin'])) {
            $staffProfile = StaffProfile::where('user_id', $user->id)->first();
        }

        // Today's events count
        $todayEventsQuery = Event::whereDate('starts_at', $today);
        $todayClassesQuery = ClassOccurrence::whereDate('starts_at', $today);

        // If staff, filter to their own events
        if ($staffProfile && $user->role === 'staff') {
            $todayEventsQuery->where('staff_id', $staffProfile->id);
            $todayClassesQuery->where('trainer_id', $staffProfile->id);
        }

        $todayEvents = $todayEventsQuery->count() + $todayClassesQuery->count();

        // This week's total hours
        $weeklyHoursQuery = Event::whereBetween('starts_at', [$weekStart, $weekEnd]);
        $weeklyClassHoursQuery = ClassOccurrence::whereBetween('starts_at', [$weekStart, $weekEnd]);

        if ($staffProfile && $user->role === 'staff') {
            $weeklyHoursQuery->where('staff_id', $staffProfile->id);
            $weeklyClassHoursQuery->where('trainer_id', $staffProfile->id);
        }

        // Calculate hours from events
        $eventHours = $weeklyHoursQuery->get()->sum(function ($event) {
            return $event->starts_at->floatDiffInHours($event->ends_at);
        });

        // Calculate hours from class occurrences
        $classHours = $weeklyClassHoursQuery->get()->sum(function ($occurrence) {
            return $occurrence->starts_at->floatDiffInHours($occurrence->ends_at);
        });

        $weeklyHours = round($eventHours + $classHours, 1);

        // Active clients count
        $activeClients = Client::whereNull('deleted_at')->count();

        // Upcoming events (next 7 days)
        $upcomingEventsQuery = Event::where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addDays(7));
        $upcomingClassesQuery = ClassOccurrence::where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addDays(7));

        if ($staffProfile && $user->role === 'staff') {
            $upcomingEventsQuery->where('staff_id', $staffProfile->id);
            $upcomingClassesQuery->where('trainer_id', $staffProfile->id);
        }

        $upcomingEvents = $upcomingEventsQuery->count() + $upcomingClassesQuery->count();

        // Today's bookings (for classes)
        $todayBookings = ClassRegistration::whereHas('occurrence', function ($q) use ($today) {
            $q->whereDate('starts_at', $today);
        })->where('status', 'booked')->count();

        return ApiResponse::success([
            'today_events' => $todayEvents,
            'weekly_hours' => $weeklyHours,
            'active_clients' => $activeClients,
            'upcoming_events' => $upcomingEvents,
            'today_bookings' => $todayBookings,
        ]);
    }
}
