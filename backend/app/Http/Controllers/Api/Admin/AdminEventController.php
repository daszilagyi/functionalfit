<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Services\ConflictDetectionService;
use App\Services\EventPricingService;
use App\Services\NotificationService;
use App\Exceptions\ConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEventController extends Controller
{
    public function __construct(
        private readonly ConflictDetectionService $conflictService,
        private readonly NotificationService $notificationService,
        private readonly EventPricingService $pricingService
    ) {}

    /**
     * Get all events with optional room filtering
     *
     * GET /api/admin/events
     */
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->has('date_from')
            ? \Carbon\Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $dateTo = $request->has('date_to')
            ? \Carbon\Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $query = Event::with(['client.user', 'additionalClients.user', 'staff.user', 'room', 'pricing'])
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
     * Create a new event (admin can assign to any staff)
     *
     * POST /api/admin/events
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:INDIVIDUAL,GROUP_CLASS,BLOCK',
            'staff_id' => 'required|exists:staff_profiles,id',
            'client_id' => 'nullable|exists:clients,id',
            'additional_client_ids' => 'nullable|array',
            'additional_client_ids.*' => 'integer|distinct', // Allow negative IDs for technical guests
            'room_id' => 'required|exists:rooms,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'notes' => 'nullable|string|max:1000',
            'service_type_id' => 'nullable|exists:service_types,id',
        ]);

        try {
            return DB::transaction(function () use ($request, $validated) {
                // Check for conflicts
                $this->conflictService->checkConflicts(
                    roomId: (int) $validated['room_id'],
                    startsAt: \Carbon\Carbon::parse($validated['starts_at']),
                    endsAt: \Carbon\Carbon::parse($validated['ends_at']),
                    staffId: (int) $validated['staff_id']
                );

                $eventData = [
                    'type' => $validated['type'],
                    'staff_id' => $validated['staff_id'],
                    'client_id' => $validated['client_id'] ?? null,
                    'room_id' => $validated['room_id'],
                    'starts_at' => $validated['starts_at'],
                    'ends_at' => $validated['ends_at'],
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'scheduled',
                    'created_by' => $request->user()->id,
                ];

                // For INDIVIDUAL events, set service_type_id and resolve main client pricing
                $serviceTypeId = null;
                if ($validated['type'] === 'INDIVIDUAL' && !empty($validated['service_type_id'])) {
                    $serviceTypeId = (int) $validated['service_type_id'];
                    $eventData['service_type_id'] = $serviceTypeId;

                    // Resolve pricing for main client
                    if (!empty($validated['client_id']) && (int) $validated['client_id'] > 0) {
                        $mainClientPricing = $this->pricingService->resolvePricingForClient(
                            (int) $validated['client_id'],
                            $serviceTypeId
                        );
                        $eventData = array_merge($eventData, $mainClientPricing);
                    }
                }

                $event = Event::create($eventData);

                // Attach additional clients with pricing if provided
                if (!empty($validated['additional_client_ids'])) {
                    $this->attachAdditionalClientsWithPricing(
                        $event,
                        $validated['additional_client_ids'],
                        $serviceTypeId
                    );
                }

                // Queue notification
                $this->notificationService->sendEventConfirmation($event);

                return ApiResponse::created(
                    $event->load(['client.user', 'additionalClients.user', 'staff.user', 'room', 'serviceType']),
                    'Event created'
                );
            });
        } catch (ConflictException $e) {
            return ApiResponse::conflict($e->getMessage(), $e->getDetails());
        }
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
     * Get a specific event by ID
     *
     * GET /api/admin/events/{id}
     */
    public function show(int $id): JsonResponse
    {
        $event = Event::with(['client.user', 'additionalClients.user', 'staff.user', 'room', 'pricing'])
            ->findOrFail($id);

        return ApiResponse::success($event);
    }

    /**
     * Update an event
     *
     * PUT /api/admin/events/{id}
     *
     * If there's a room/time conflict, returns 409 with conflict details.
     * To override the conflict, include force_override: true in the request.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|string|in:INDIVIDUAL,GROUP_CLASS,BLOCK',
            'staff_id' => 'sometimes|nullable|exists:staff_profiles,id',
            'client_id' => 'sometimes|nullable|exists:clients,id',
            'additional_client_ids' => 'sometimes|nullable|array',
            'additional_client_ids.*' => 'integer|distinct', // Allow negative IDs for technical guests
            'room_id' => 'sometimes|exists:rooms,id',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
            'notes' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:scheduled,completed,cancelled',
            'service_type_id' => 'sometimes|nullable|exists:service_types,id',
            'force_override' => 'sometimes|boolean',
        ]);

        $forceOverride = $validated['force_override'] ?? false;
        unset($validated['force_override']);

        // Check for conflicts if time or room is being changed
        $isTimeOrRoomChanged = isset($validated['starts_at']) || isset($validated['ends_at']) || isset($validated['room_id']);

        if ($isTimeOrRoomChanged && !$forceOverride) {
            $roomId = $validated['room_id'] ?? $event->room_id;
            $startsAt = isset($validated['starts_at'])
                ? \Carbon\Carbon::parse($validated['starts_at'])
                : $event->starts_at;
            $endsAt = isset($validated['ends_at'])
                ? \Carbon\Carbon::parse($validated['ends_at'])
                : $event->ends_at;

            $conflicts = $this->conflictService->detectConflicts(
                roomId: (int) $roomId,
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

        return DB::transaction(function () use ($event, $validated, $request) {
            // Get the service type ID (from update or existing event)
            $serviceTypeId = isset($validated['service_type_id'])
                ? (int) $validated['service_type_id']
                : $event->service_type_id;

            // Update main client pricing if service type or client changed
            $clientChanged = isset($validated['client_id']) && $validated['client_id'] != $event->client_id;
            $serviceTypeChanged = isset($validated['service_type_id']) && $validated['service_type_id'] != $event->service_type_id;

            if ($serviceTypeId && ($clientChanged || $serviceTypeChanged)) {
                $clientId = $validated['client_id'] ?? $event->client_id;
                if ($clientId && (int) $clientId > 0) {
                    $mainClientPricing = $this->pricingService->resolvePricingForClient(
                        (int) $clientId,
                        $serviceTypeId
                    );
                    $validated = array_merge($validated, $mainClientPricing);
                }
            }

            $event->update($validated);

            // Sync additional clients with pricing if provided
            if (array_key_exists('additional_client_ids', $validated)) {
                $additionalClientIds = $validated['additional_client_ids'] ?? [];
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
            }

            $event->load(['client.user', 'additionalClients.user', 'staff.user', 'room', 'serviceType']);

            return ApiResponse::success($event, 'Event updated successfully');
        });
    }

    /**
     * Delete an event
     *
     * DELETE /api/admin/events/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return ApiResponse::success(null, 'Event deleted successfully');
    }
}
