<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClassOccurrence;
use App\Services\ConflictDetectionService;
use App\Services\NotificationService;
use App\Exceptions\ConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClassOccurrenceController extends Controller
{
    public function __construct(
        private readonly ConflictDetectionService $conflictService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * List all class occurrences (admin only)
     *
     * GET /api/admin/class-occurrences
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClassOccurrence::with(['template', 'room', 'trainer'])
            ->orderBy('starts_at');

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('starts_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('starts_at', '<=', $request->input('date_to'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $occurrences = $query->paginate(50);

        return ApiResponse::success($occurrences);
    }

    /**
     * Show a specific class occurrence
     *
     * GET /api/admin/class-occurrences/{id}
     */
    public function show(int $id): JsonResponse
    {
        $occurrence = ClassOccurrence::with([
            'template',
            'room',
            'trainer',
            'registrations.client.user'
        ])->findOrFail($id);

        return ApiResponse::success($occurrence);
    }

    /**
     * Create a new class occurrence (manual override)
     *
     * POST /api/admin/class-occurrences
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_template_id' => ['required_without:template_id', 'integer', 'exists:class_templates,id'],
            'template_id' => ['required_without:class_template_id', 'integer', 'exists:class_templates,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_capacity' => ['required_without:capacity', 'integer', 'min:1'],
            'capacity' => ['required_without:max_capacity', 'integer', 'min:1'],
            'credits_required' => ['nullable', 'integer', 'min:1'],
            'is_recurring' => ['sometimes', 'boolean'],
            'repeat_from' => ['required_if:is_recurring,true', 'nullable', 'date'],
            'repeat_until' => ['required_if:is_recurring,true', 'nullable', 'date', 'after_or_equal:repeat_from'],
        ]);

        // Normalize field names (frontend uses class_template_id, max_capacity)
        $templateId = $validated['template_id'] ?? $validated['class_template_id'];
        $capacity = $validated['capacity'] ?? $validated['max_capacity'];
        $isRecurring = $validated['is_recurring'] ?? false;
        $repeatFrom = $validated['repeat_from'] ?? null;
        $repeatUntil = $validated['repeat_until'] ?? null;

        try {
            return DB::transaction(function () use ($validated, $templateId, $capacity, $isRecurring, $repeatFrom, $repeatUntil) {
                $createdOccurrences = [];

                // Get time from starts_at (the time of day for the recurring events)
                $startsAt = new \DateTime($validated['starts_at']);
                $endsAt = new \DateTime($validated['ends_at']);
                $startTime = $startsAt->format('H:i:s');
                $endTime = $endsAt->format('H:i:s');
                $dayOfWeek = (int) $startsAt->format('N'); // 1=Monday, 7=Sunday

                if ($isRecurring && $repeatFrom && $repeatUntil) {
                    // Create occurrences within the interval
                    $repeatFromDate = new \DateTime($repeatFrom);
                    $repeatUntilDate = new \DateTime($repeatUntil);

                    // Find the first occurrence date (same day of week as starts_at, on or after repeat_from)
                    $currentDate = clone $repeatFromDate;
                    $currentDayOfWeek = (int) $currentDate->format('N');

                    // Calculate days to add to get to the correct day of week
                    $daysToAdd = ($dayOfWeek - $currentDayOfWeek + 7) % 7;
                    if ($daysToAdd > 0) {
                        $currentDate->modify("+{$daysToAdd} days");
                    }

                    // Create occurrences every week until repeat_until
                    while ($currentDate <= $repeatUntilDate) {
                        $occurrenceStartsAt = new \DateTime($currentDate->format('Y-m-d') . ' ' . $startTime);
                        $occurrenceEndsAt = new \DateTime($currentDate->format('Y-m-d') . ' ' . $endTime);

                        // Check for conflicts (skip if conflict, continue with others)
                        try {
                            $this->conflictService->checkConflicts(
                                roomId: (int) $validated['room_id'],
                                startsAt: $occurrenceStartsAt,
                                endsAt: $occurrenceEndsAt,
                                staffId: (int) $validated['trainer_id']
                            );

                            $occurrence = ClassOccurrence::create([
                                'template_id' => $templateId,
                                'room_id' => $validated['room_id'],
                                'trainer_id' => $validated['trainer_id'],
                                'starts_at' => $occurrenceStartsAt->format('Y-m-d H:i:s'),
                                'ends_at' => $occurrenceEndsAt->format('Y-m-d H:i:s'),
                                'capacity' => $capacity,
                                'credits_required' => $validated['credits_required'] ?? null,
                                'status' => 'scheduled',
                            ]);

                            $createdOccurrences[] = $occurrence;
                        } catch (ConflictException $e) {
                            // Skip this occurrence due to conflict, continue with others
                            // Only throw if it's the first occurrence
                            if (empty($createdOccurrences)) {
                                throw $e;
                            }
                            // Otherwise, just skip and continue
                        }

                        // Move to next week
                        $currentDate->modify('+1 week');
                    }
                } else {
                    // Single occurrence - use the original starts_at and ends_at
                    $this->conflictService->checkConflicts(
                        roomId: (int) $validated['room_id'],
                        startsAt: $startsAt,
                        endsAt: $endsAt,
                        staffId: (int) $validated['trainer_id']
                    );

                    $occurrence = ClassOccurrence::create([
                        'template_id' => $templateId,
                        'room_id' => $validated['room_id'],
                        'trainer_id' => $validated['trainer_id'],
                        'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                        'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                        'capacity' => $capacity,
                        'credits_required' => $validated['credits_required'] ?? null,
                        'status' => 'scheduled',
                    ]);

                    $createdOccurrences[] = $occurrence;
                }

                $message = count($createdOccurrences) > 1
                    ? count($createdOccurrences) . ' csoportos óra létrehozva'
                    : 'Csoportos óra létrehozva';

                // Return the first occurrence for single create, or all for recurring
                if (count($createdOccurrences) === 1) {
                    return ApiResponse::created($createdOccurrences[0]->load(['template', 'room', 'trainer']), $message);
                }

                return ApiResponse::created([
                    'count' => count($createdOccurrences),
                    'occurrences' => collect($createdOccurrences)->map(fn($o) => $o->load(['template', 'room', 'trainer'])),
                ], $message);
            });
        } catch (ConflictException $e) {
            return ApiResponse::conflict($e->getMessage(), $e->getDetails());
        }
    }

    /**
     * Update a class occurrence
     *
     * PATCH /api/admin/class-occurrences/{id}
     *
     * If there's a room/time conflict, returns 409 with conflict details.
     * To override the conflict, include force_override: true in the request.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $occurrence = ClassOccurrence::with('registrations')->findOrFail($id);

        $validated = $request->validate([
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after:starts_at'],
            'room_id' => ['sometimes', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['sometimes', 'integer', 'exists:staff_profiles,id'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['scheduled', 'completed', 'cancelled'])],
            'force_override' => ['sometimes', 'boolean'],
        ]);

        $forceOverride = $validated['force_override'] ?? false;
        unset($validated['force_override']);

        // Check for conflicts if time or room is being changed
        $isTimeOrRoomChanged = isset($validated['starts_at']) || isset($validated['ends_at']) || isset($validated['room_id']);

        if ($isTimeOrRoomChanged && !$forceOverride) {
            $roomId = (int) ($validated['room_id'] ?? $occurrence->room_id);
            $startsAt = isset($validated['starts_at'])
                ? \Carbon\Carbon::parse($validated['starts_at'])
                : $occurrence->starts_at;
            $endsAt = isset($validated['ends_at'])
                ? \Carbon\Carbon::parse($validated['ends_at'])
                : $occurrence->ends_at;

            $conflicts = $this->conflictService->detectConflicts(
                roomId: $roomId,
                startsAt: $startsAt,
                endsAt: $endsAt,
                excludeEventId: null // This is a class occurrence, not an event
            );

            // Filter out the current occurrence from conflicts (detectConflicts doesn't have excludeClassOccurrenceId)
            $conflicts = array_filter($conflicts, function ($conflict) use ($occurrence) {
                return !($conflict['event_type'] === 'class_occurrence' && $conflict['event_id'] === $occurrence->id);
            });

            if (!empty($conflicts)) {
                return ApiResponse::conflict(
                    'Az esemény ütközik egy másik foglalással ebben a teremben. Biztosan módosítani szeretnéd?',
                    [
                        'conflicts' => array_values($conflicts),
                        'requires_confirmation' => true,
                    ]
                );
            }
        }

        return DB::transaction(function () use ($validated, $occurrence) {
            // If time, room, or trainer changed, notify registered clients
            $shouldNotify = isset($validated['starts_at']) || isset($validated['ends_at']) ||
                           isset($validated['room_id']) || isset($validated['trainer_id']);

            $occurrence->update($validated);

            if ($shouldNotify && $occurrence->registrations->isNotEmpty()) {
                foreach ($occurrence->registrations as $registration) {
                    $this->notificationService->sendClassRescheduled($registration);
                }
            }

            return ApiResponse::success($occurrence->fresh(['template', 'room', 'trainer']), 'Class occurrence updated');
        });
    }

    /**
     * Force move/reschedule a class occurrence (admin override)
     *
     * PATCH /api/admin/class-occurrences/{id}/force-move
     */
    public function forceMove(Request $request, int $id): JsonResponse
    {
        $occurrence = ClassOccurrence::with('registrations')->findOrFail($id);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'room_id' => ['sometimes', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['sometimes', 'integer', 'exists:staff_profiles,id'],
        ]);

        try {
            return DB::transaction(function () use ($validated, $occurrence, $request) {
                // Check for conflicts (with new time/room/trainer)
                $this->conflictService->checkConflicts(
                    roomId: (int) ($validated['room_id'] ?? $occurrence->room_id),
                    startsAt: new \DateTime($validated['starts_at']),
                    endsAt: new \DateTime($validated['ends_at']),
                    staffId: (int) ($validated['trainer_id'] ?? $occurrence->trainer_id),
                    excludeClassOccurrenceId: $occurrence->id
                );

                $occurrence->update($validated);

                // Notify all registered clients
                foreach ($occurrence->registrations as $registration) {
                    $this->notificationService->sendClassRescheduled($registration);
                }

                return ApiResponse::success($occurrence->fresh(['template', 'room', 'trainer']), 'Class occurrence moved (admin override)');
            });
        } catch (ConflictException $e) {
            return ApiResponse::conflict($e->getMessage(), $e->getDetails());
        }
    }

    /**
     * Cancel a class occurrence
     *
     * DELETE /api/admin/class-occurrences/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $occurrence = ClassOccurrence::with('registrations')->findOrFail($id);

        return DB::transaction(function () use ($occurrence) {
            $occurrence->update(['status' => 'cancelled']);

            // Notify all registered clients
            foreach ($occurrence->registrations as $registration) {
                $registration->update(['status' => 'cancelled']);
                $this->notificationService->sendClassCancelled($registration);
            }

            return ApiResponse::success(null, 'Class occurrence cancelled');
        });
    }
}
