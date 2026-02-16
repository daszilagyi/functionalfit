<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\EventChange;
use App\Services\ConflictDetectionService;
use App\Services\EventPricingService;
use App\Services\NotificationService;
use App\Exceptions\ConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffEventController extends Controller
{
    public function __construct(
        private readonly ConflictDetectionService $conflictService,
        private readonly NotificationService $notificationService,
        private readonly EventPricingService $pricingService
    ) {}

    /**
     * Get staff member's personal calendar
     *
     * GET /api/staff/my-events
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $staff = $user->staffProfile;

        // Allow admin to access, otherwise require staff profile
        if (!$staff && $user->role !== 'admin') {
            return ApiResponse::error('Only staff can access this endpoint', null, 403);
        }

        $dateFrom = $request->has('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfWeek()->startOfDay();
        $dateTo = $request->has('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfWeek()->endOfDay();

        // If staff, return their events. If admin, return empty (admin has no events)
        $query = Event::with(['client.user', 'additionalClients.user', 'room'])
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->orderBy('starts_at');

        if ($staff) {
            $query->where('staff_id', $staff->id);
        } else {
            // Admin with no staff profile - return empty array
            $query->whereRaw('1 = 0');
        }

        // Filter by client name if provided
        if ($request->filled('client_search')) {
            $search = $request->input('client_search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('client.user', function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', "%{$search}%");
                })->orWhereHas('additionalClients.user', function ($sub) use ($search) {
                    $sub->where('name', 'LIKE', "%{$search}%");
                });
            });
        }

        return ApiResponse::success($query->get());
    }

    /**
     * Get all events (staff can view all events on the calendar)
     *
     * GET /api/staff/all-events
     */
    public function allEvents(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only staff and admin can access
        if (!in_array($user->role, ['staff', 'admin'])) {
            return ApiResponse::error('Only staff can access this endpoint', null, 403);
        }

        $dateFrom = $request->has('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfWeek()->startOfDay();
        $dateTo = $request->has('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfWeek()->endOfDay();

        $query = Event::with(['client.user', 'additionalClients.user', 'staff.user', 'room'])
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->orderBy('starts_at');

        // Filter by room if provided
        if ($request->has('room_id')) {
            $roomId = $request->input('room_id');
            if ($roomId !== 'all' && $roomId !== null) {
                $query->where('room_id', $roomId);
            }
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return ApiResponse::success($query->get());
    }

    /**
     * Create a new 1:1 event (or batch create for recurring events)
     *
     * POST /api/staff/events
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::error('Only staff can create events', null, 403);
        }

        // Handle recurring events
        if ($request->boolean('is_recurring')) {
            return $this->storeRecurring($request, $staff);
        }

        // Single event creation
        return $this->storeSingle($request, $staff);
    }

    /**
     * Create a single event
     */
    private function storeSingle(StoreEventRequest $request, $staff): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $staff) {
                // Check for conflicts
                $this->conflictService->checkConflicts(
                    roomId: $request->integer('room_id'),
                    startsAt: $request->date('starts_at'),
                    endsAt: $request->date('ends_at'),
                    staffId: $staff->id
                );

                $eventData = [
                    'type' => $request->input('type', 'INDIVIDUAL'),
                    'staff_id' => $staff->id,
                    'client_id' => $request->input('client_id'),
                    'room_id' => $request->integer('room_id'),
                    'starts_at' => $request->date('starts_at'),
                    'ends_at' => $request->date('ends_at'),
                    'notes' => $request->input('notes'),
                    'status' => 'scheduled',
                    'created_by' => $request->user()->id,
                ];

                // For INDIVIDUAL events, set service_type_id and resolve main client pricing
                $serviceTypeId = null;
                if ($request->input('type') === 'INDIVIDUAL' && $request->filled('service_type_id')) {
                    $serviceTypeId = $request->integer('service_type_id');
                    $eventData['service_type_id'] = $serviceTypeId;

                    // Resolve pricing for main client
                    if ($request->filled('client_id') && $request->integer('client_id') > 0) {
                        $mainClientPricing = $this->pricingService->resolvePricingForClient(
                            $request->integer('client_id'),
                            $serviceTypeId
                        );
                        $eventData = array_merge($eventData, $mainClientPricing);
                    }
                }

                $event = Event::create($eventData);

                // Attach additional clients with pricing if provided
                if ($request->has('additional_client_ids') && is_array($request->input('additional_client_ids'))) {
                    $this->attachAdditionalClientsWithPricing(
                        $event,
                        $request->input('additional_client_ids'),
                        $serviceTypeId
                    );
                }

                // Queue notification
                $this->notificationService->sendEventConfirmation($event);

                return ApiResponse::created($event->load(['client.user', 'additionalClients.user', 'room', 'serviceType']), 'Event created');
            });
        } catch (ConflictException $e) {
            return ApiResponse::conflict($e->getMessage(), $e->getDetails());
        }
    }

    /**
     * Create recurring events (batch creation)
     */
    private function storeRecurring(StoreEventRequest $request, $staff): JsonResponse
    {
        $repeatFrom = \Carbon\Carbon::parse($request->input('repeat_from'));
        $repeatUntil = \Carbon\Carbon::parse($request->input('repeat_until'));
        $startsAt = \Carbon\Carbon::parse($request->input('starts_at'));
        $endsAt = \Carbon\Carbon::parse($request->input('ends_at'));
        $roomId = $request->integer('room_id');
        $skipDates = $request->input('skip_dates', []);

        // Calculate the duration of the event
        $durationMinutes = $startsAt->diffInMinutes($endsAt);

        // Get the day of the week from starts_at
        $dayOfWeek = $startsAt->dayOfWeek;
        $timeOfDay = $startsAt->format('H:i:s');

        // Prepare base event data
        $baseEventData = [
            'type' => $request->input('type', 'INDIVIDUAL'),
            'staff_id' => $staff->id,
            'client_id' => $request->input('client_id'),
            'room_id' => $roomId,
            'notes' => $request->input('notes'),
            'status' => 'scheduled',
            'created_by' => $request->user()->id,
        ];

        // For INDIVIDUAL events, set service_type_id and resolve main client pricing
        $serviceTypeId = null;
        if ($request->input('type') === 'INDIVIDUAL' && $request->filled('service_type_id')) {
            $serviceTypeId = $request->integer('service_type_id');
            $baseEventData['service_type_id'] = $serviceTypeId;

            // Resolve pricing for main client
            if ($request->filled('client_id') && $request->integer('client_id') > 0) {
                $mainClientPricing = $this->pricingService->resolvePricingForClient(
                    $request->integer('client_id'),
                    $serviceTypeId
                );
                $baseEventData = array_merge($baseEventData, $mainClientPricing);
            }
        }

        $additionalClientIds = $request->input('additional_client_ids', []);
        $createdEvents = [];
        $skippedDates = [];

        try {
            return DB::transaction(function () use (
                $repeatFrom, $repeatUntil, $dayOfWeek, $timeOfDay, $durationMinutes,
                $roomId, $skipDates, $baseEventData, $serviceTypeId, $additionalClientIds,
                $staff, &$createdEvents, &$skippedDates
            ) {
                // Find the first occurrence of the correct day of week
                $current = $repeatFrom->copy();
                while ($current->dayOfWeek !== $dayOfWeek) {
                    $current->addDay();
                }

                // Iterate through all weekly occurrences
                while ($current->lte($repeatUntil)) {
                    $dateString = $current->format('Y-m-d');

                    // Skip if date is in skip_dates
                    if (in_array($dateString, $skipDates)) {
                        $skippedDates[] = $dateString;
                        $current->addWeek();
                        continue;
                    }

                    $eventStartsAt = $current->copy()->setTimeFromTimeString($timeOfDay);
                    $eventEndsAt = $eventStartsAt->copy()->addMinutes($durationMinutes);

                    // Check for conflicts (skip if conflict)
                    $conflicts = $this->conflictService->detectConflicts(
                        roomId: $roomId,
                        startsAt: $eventStartsAt,
                        endsAt: $eventEndsAt
                    );

                    if (!empty($conflicts)) {
                        $skippedDates[] = $dateString;
                        $current->addWeek();
                        continue;
                    }

                    // Create the event
                    $eventData = array_merge($baseEventData, [
                        'starts_at' => $eventStartsAt,
                        'ends_at' => $eventEndsAt,
                    ]);

                    $event = Event::create($eventData);

                    // Attach additional clients with pricing if provided
                    if (is_array($additionalClientIds) && !empty($additionalClientIds)) {
                        $this->attachAdditionalClientsWithPricing(
                            $event,
                            $additionalClientIds,
                            $serviceTypeId
                        );
                    }

                    // Queue notification
                    $this->notificationService->sendEventConfirmation($event);

                    $createdEvents[] = $event->load(['client.user', 'additionalClients.user', 'room', 'serviceType']);
                    $current->addWeek();
                }

                if (empty($createdEvents)) {
                    return ApiResponse::error('All dates have conflicts or were skipped. No events created.', null, 422);
                }

                $message = count($createdEvents) . ' event(s) created';
                if (!empty($skippedDates)) {
                    $message .= ', ' . count($skippedDates) . ' date(s) skipped';
                }

                return ApiResponse::created([
                    'count' => count($createdEvents),
                    'events' => $createdEvents,
                    'skipped_dates' => $skippedDates,
                ], $message);
            });
        } catch (ConflictException $e) {
            return ApiResponse::conflict($e->getMessage(), $e->getDetails());
        }
    }

    /**
     * Preview recurring events (check for conflicts without creating)
     *
     * POST /api/staff/events/preview-recurring
     */
    public function previewRecurring(StoreEventRequest $request): JsonResponse
    {
        $staff = $request->user()->staffProfile;

        if (!$staff) {
            return ApiResponse::error('Only staff can preview events', null, 403);
        }

        $repeatFrom = \Carbon\Carbon::parse($request->input('repeat_from'));
        $repeatUntil = \Carbon\Carbon::parse($request->input('repeat_until'));
        $startsAt = \Carbon\Carbon::parse($request->input('starts_at'));
        $endsAt = \Carbon\Carbon::parse($request->input('ends_at'));
        $roomId = $request->integer('room_id');

        // Calculate the duration of the event
        $durationMinutes = $startsAt->diffInMinutes($endsAt);

        // Get the day of the week from starts_at
        $dayOfWeek = $startsAt->dayOfWeek;
        $timeOfDay = $startsAt->format('H:i:s');

        // Generate all dates in the interval
        $dates = [];
        $current = $repeatFrom->copy();

        // Find the first occurrence of the correct day of week
        while ($current->dayOfWeek !== $dayOfWeek) {
            $current->addDay();
        }

        // Generate all weekly occurrences
        while ($current->lte($repeatUntil)) {
            $eventStartsAt = $current->copy()->setTimeFromTimeString($timeOfDay);
            $eventEndsAt = $eventStartsAt->copy()->addMinutes($durationMinutes);

            // Check for conflicts
            $conflicts = $this->conflictService->detectConflicts(
                roomId: $roomId,
                startsAt: $eventStartsAt,
                endsAt: $eventEndsAt
            );

            $dateEntry = [
                'date' => $current->format('Y-m-d'),
                'starts_at' => $eventStartsAt->toIso8601String(),
                'ends_at' => $eventEndsAt->toIso8601String(),
                'status' => empty($conflicts) ? 'ok' : 'conflict',
            ];

            if (!empty($conflicts)) {
                // Get the first conflict's description
                $firstConflict = $conflicts[0];
                $conflictTime = \Carbon\Carbon::parse($firstConflict['starts_at'])->format('H:i');
                $dateEntry['conflict_with'] = ($firstConflict['client_name'] ?? 'Foglalás') . ' - ' . $conflictTime;
            }

            $dates[] = $dateEntry;
            $current->addWeek();
        }

        $okCount = count(array_filter($dates, fn($d) => $d['status'] === 'ok'));
        $conflictCount = count(array_filter($dates, fn($d) => $d['status'] === 'conflict'));

        return ApiResponse::success([
            'dates' => $dates,
            'total' => count($dates),
            'ok_count' => $okCount,
            'conflict_count' => $conflictCount,
        ]);
    }

    /**
     * Attach additional clients with pricing resolved per guest.
     */
    private function attachAdditionalClientsWithPricing(Event $event, array $additionalClientIds, ?int $serviceTypeId): void
    {
        $technicalGuestId = \App\Models\Client::getTechnicalGuestId();
        $syncData = [];
        $technicalGuestCount = 0;

        foreach ($additionalClientIds as $clientId) {
            if ($clientId < 0) {
                // Negative IDs are technical guests - count them
                $technicalGuestCount++;
            } else {
                // Real client - resolve pricing and increment quantity if duplicate
                if (isset($syncData[$clientId])) {
                    $syncData[$clientId]['quantity']++;
                } else {
                    $pivotData = ['quantity' => 1];

                    // Add pricing if service type is set
                    if ($serviceTypeId) {
                        $pricing = $this->pricingService->resolvePricingForClient($clientId, $serviceTypeId);
                        $pivotData = array_merge($pivotData, $pricing);
                    }

                    $syncData[$clientId] = $pivotData;
                }
            }
        }

        // Add technical guests with their count and service type default pricing
        if ($technicalGuestCount > 0 && $technicalGuestId) {
            $pivotData = ['quantity' => $technicalGuestCount];

            if ($serviceTypeId) {
                $pricing = $this->pricingService->resolvePricingForTechnicalGuest($serviceTypeId);
                $pivotData = array_merge($pivotData, $pricing);
            }

            $syncData[$technicalGuestId] = $pivotData;
        }

        if (!empty($syncData)) {
            foreach ($syncData as $clientId => $pivotData) {
                $event->additionalClients()->attach($clientId, $pivotData);
            }
        }
    }

    /**
     * Update an existing event (same-day only for staff)
     *
     * PATCH /api/staff/events/{id}
     *
     * If there's a room/time conflict, returns 409 with conflict details.
     * To override the conflict, include force_override: true in the request.
     */
    public function update(UpdateEventRequest $request, int $id): JsonResponse
    {
        // Load the event
        $event = Event::with(['staff', 'client.user', 'additionalClients.user', 'room'])->findOrFail($id);

        // Authorization check via policy
        $this->authorize('update', $event);

        $forceOverride = $request->boolean('force_override', false);

        // Check for conflicts if time or room is being changed
        $isTimeOrRoomChanged = $request->has('starts_at') || $request->has('ends_at') || $request->has('room_id');

        if ($isTimeOrRoomChanged && !$forceOverride) {
            $roomId = $request->input('room_id') ? (int) $request->input('room_id') : $event->room_id;
            $startsAt = $request->date('starts_at') ?? $event->starts_at;
            $endsAt = $request->date('ends_at') ?? $event->ends_at;

            $conflicts = $this->conflictService->detectConflicts(
                roomId: $roomId,
                startsAt: $startsAt,
                endsAt: $endsAt,
                excludeEventId: $event->id
            );

            if (!empty($conflicts)) {
                return ApiResponse::conflict(
                    'Az esemény ütközik egy másik foglalással ebben a teremben. Biztosan módosítani szeretnéd?',
                    [
                        'conflicts' => $conflicts,
                        'requires_confirmation' => true,
                    ]
                );
            }
        }

        return DB::transaction(function () use ($request, $event) {
            // Store old values for audit
            $oldData = $event->only(['starts_at', 'ends_at', 'room_id']);

            $event->update($request->validated());

            // Sync additional clients with pricing if provided
            if ($request->has('additional_client_ids')) {
                $additionalClientIds = $request->input('additional_client_ids');
                $serviceTypeId = $event->service_type_id;

                if (is_array($additionalClientIds)) {
                    $technicalGuestId = \App\Models\Client::getTechnicalGuestId();
                    $syncData = [];
                    $technicalGuestCount = 0;

                    foreach ($additionalClientIds as $clientId) {
                        if ($clientId < 0) {
                            // Negative IDs are technical guests - count them
                            $technicalGuestCount++;
                        } else {
                            // Real client - resolve pricing and increment quantity if duplicate
                            if (isset($syncData[$clientId])) {
                                $syncData[$clientId]['quantity']++;
                            } else {
                                $pivotData = ['quantity' => 1];

                                // Add pricing if service type is set
                                if ($serviceTypeId) {
                                    $pricing = $this->pricingService->resolvePricingForClient($clientId, $serviceTypeId);
                                    $pivotData = array_merge($pivotData, $pricing);
                                }

                                $syncData[$clientId] = $pivotData;
                            }
                        }
                    }

                    // Add technical guests with their count and service type default pricing
                    if ($technicalGuestCount > 0 && $technicalGuestId) {
                        $pivotData = ['quantity' => $technicalGuestCount];

                        if ($serviceTypeId) {
                            $pricing = $this->pricingService->resolvePricingForTechnicalGuest($serviceTypeId);
                            $pivotData = array_merge($pivotData, $pricing);
                        }

                        $syncData[$technicalGuestId] = $pivotData;
                    }

                    $event->additionalClients()->sync($syncData);
                } else {
                    $event->additionalClients()->sync([]);
                }
            }

            // Log the change
            EventChange::create([
                'event_id' => $event->id,
                'by_user_id' => $request->user()->id,
                'action' => 'updated',
                'meta' => [
                    'old' => $oldData,
                    'new' => $event->only(['starts_at', 'ends_at', 'room_id']),
                ],
                'created_at' => now(),
            ]);

            // Notify client about change
            $this->notificationService->sendEventUpdate($event);

            return ApiResponse::success($event->load(['client.user', 'additionalClients.user', 'room']), 'Event updated');
        });
    }

    /**
     * Cancel an event
     *
     * DELETE /api/staff/events/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $event = Event::with('staff')->findOrFail($id);

        // Authorization check via policy
        $this->authorize('delete', $event);

        // Prevent deletion of past events
        if ($event->starts_at < now()) {
            return ApiResponse::error('Past events cannot be deleted', 403);
        }

        return DB::transaction(function () use ($request, $event) {
            // Soft delete the event
            $event->delete();

            // Log the deletion
            EventChange::create([
                'event_id' => $event->id,
                'by_user_id' => $request->user()->id,
                'action' => 'deleted',
                'meta' => [
                    'deleted_at' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);

            // Notify client
            $this->notificationService->sendEventCancellation($event);

            return ApiResponse::noContent();
        });
    }

    /**
     * Get dashboard stats for staff member
     *
     * GET /api/staff/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $staff = $user->staffProfile;

        // Allow admin to access, otherwise require staff profile
        if (!$staff && $user->role !== 'admin') {
            return ApiResponse::error('Only staff can access this endpoint', null, 403);
        }

        // If admin with no staff profile, return zeros
        if (!$staff) {
            return ApiResponse::success([
                'today_sessions' => 0,
                'today_completed' => 0,
                'today_remaining' => 0,
                'week_total_hours' => 0,
                'upcoming_session' => null,
            ]);
        }

        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        // Today's sessions
        $todayEvents = Event::where('staff_id', $staff->id)
            ->whereBetween('starts_at', [$today, $todayEnd])
            ->get();

        $todaySessions = $todayEvents->count();
        $todayCompleted = $todayEvents->where('attendance_status', 'attended')->count();
        $todayRemaining = $todayEvents->where('attendance_status', '!=', 'attended')
            ->where('starts_at', '>', now())
            ->count();

        // Week total hours
        $weekEvents = Event::where('staff_id', $staff->id)
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->get();

        $weekTotalHours = $weekEvents->sum(function ($event) {
            return $event->starts_at->diffInMinutes($event->ends_at) / 60.0;
        });

        // Upcoming session (next event from now)
        $upcomingEvent = Event::with(['client.user', 'room'])
            ->where('staff_id', $staff->id)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->first();

        $upcomingSession = null;
        if ($upcomingEvent) {
            $upcomingSession = [
                'id' => $upcomingEvent->id,
                'client_name' => $upcomingEvent->client?->user?->name,
                'starts_at' => $upcomingEvent->starts_at->toIso8601String(),
                'ends_at' => $upcomingEvent->ends_at->toIso8601String(),
                'room_name' => $upcomingEvent->room?->name ?? 'N/A',
                'duration_minutes' => $upcomingEvent->starts_at->diffInMinutes($upcomingEvent->ends_at),
            ];
        }

        return ApiResponse::success([
            'today_sessions' => $todaySessions,
            'today_completed' => $todayCompleted,
            'today_remaining' => $todayRemaining,
            'week_total_hours' => round($weekTotalHours, 1),
            'upcoming_session' => $upcomingSession,
        ]);
    }
}
