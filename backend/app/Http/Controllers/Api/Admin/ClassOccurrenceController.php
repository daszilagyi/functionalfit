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
            'class_template_id' => ['required', 'integer', 'exists:class_templates,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'max_capacity' => ['required', 'integer', 'min:1'],
            'credits_required' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // Check for conflicts
                $this->conflictService->checkConflicts(
                    roomId: $validated['room_id'],
                    startsAt: new \DateTime($validated['starts_at']),
                    endsAt: new \DateTime($validated['ends_at']),
                    staffId: $validated['trainer_id']
                );

                $occurrence = ClassOccurrence::create([
                    ...$validated,
                    'status' => 'scheduled',
                    'current_participants' => 0,
                ]);

                return ApiResponse::created($occurrence->load(['template', 'room', 'trainer']), 'Class occurrence created');
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
                    roomId: $validated['room_id'] ?? $occurrence->room_id,
                    startsAt: new \DateTime($validated['starts_at']),
                    endsAt: new \DateTime($validated['ends_at']),
                    staffId: $validated['trainer_id'] ?? $occurrence->trainer_id,
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
