<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Services\PassCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventCheckinController extends Controller
{
    public function __construct(
        private readonly PassCreditService $passCreditService
    ) {}

    /**
     * Check in a client for a 1:1 event
     *
     * POST /api/staff/events/{eventId}/checkin
     * Body: {
     *   "attendance_status": "attended" | "no_show",
     *   "client_id": int (optional for multi-guest),
     *   "guest_index": int (optional, for identifying specific guest when same client_id appears multiple times)
     * }
     *
     * For single-guest events: checks in the main client
     * For multi-guest events: if client_id + guest_index is provided, checks in that specific guest
     *                         if client_id is not provided and only main client, checks in main client
     */
    public function checkinEvent(Request $request, int $eventId): JsonResponse
    {
        $validated = $request->validate([
            'attendance_status' => ['required', Rule::in(['attended', 'no_show'])],
            'client_id' => ['nullable', 'integer'],
            'guest_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $event = Event::with(['client', 'additionalClients'])->findOrFail($eventId);

        // Authorization: only assigned staff or admin
        $user = $request->user();
        if (!$user->isAdmin() && $user->staffProfile?->id !== $event->staff_id) {
            return ApiResponse::forbidden('Cannot check in for other staff members\' events');
        }

        $clientId = $validated['client_id'] ?? null;
        $guestIndex = $validated['guest_index'] ?? null;

        return DB::transaction(function () use ($event, $validated, $clientId, $guestIndex) {
            // Determine which client to check in
            if ($clientId) {
                // Check if it's the main client (cast to int for comparison)
                if ((int) $event->client_id === (int) $clientId) {
                    return $this->checkinMainClient($event, $validated['attendance_status']);
                }

                // Check if it's an additional client - use guest_index if provided
                $additionalClient = $event->additionalClients->first(function ($client) use ($clientId, $guestIndex) {
                    $clientMatch = (int) $client->id === (int) $clientId;
                    if ($guestIndex !== null) {
                        return $clientMatch && (int) $client->pivot->guest_index === (int) $guestIndex;
                    }
                    return $clientMatch;
                });

                if ($additionalClient) {
                    return $this->checkinAdditionalClient($event, $additionalClient, $validated['attendance_status']);
                }

                return ApiResponse::error('Client not found in this event', 404);
            }

            // No client_id specified - check in main client (for backwards compatibility)
            return $this->checkinMainClient($event, $validated['attendance_status']);
        });
    }

    /**
     * Check in the main client of an event
     */
    private function checkinMainClient(Event $event, string $attendanceStatus): JsonResponse
    {
        $previousStatus = $event->attendance_status;

        $event->update([
            'attendance_status' => $attendanceStatus,
            'checked_in_at' => now(),
        ]);

        // Deduct pass credit if changing to attended (and wasn't already attended)
        $creditDeducted = false;
        if ($attendanceStatus === 'attended' && $previousStatus !== 'attended' && $event->client) {
            try {
                $this->passCreditService->deductCredit(
                    $event->client,
                    "1:1 session check-in for event #{$event->id}"
                );
                $creditDeducted = true;
            } catch (\Exception $e) {
                \Log::warning("Failed to deduct credit for event {$event->id}: {$e->getMessage()}");
            }
        }

        $event->load(['client', 'additionalClients']);

        return ApiResponse::success([
            'event' => $event,
            'pass_credit_deducted' => $creditDeducted,
            'checked_in_client_id' => $event->client_id,
        ], 'Check-in recorded');
    }

    /**
     * Check in an additional client of an event
     */
    private function checkinAdditionalClient(Event $event, $client, string $attendanceStatus): JsonResponse
    {
        $previousStatus = $client->pivot->attendance_status;
        $guestIndex = $client->pivot->guest_index ?? 0;

        // Update pivot record using direct DB query to target specific guest_index
        DB::table('event_additional_clients')
            ->where('event_id', $event->id)
            ->where('client_id', $client->id)
            ->where('guest_index', $guestIndex)
            ->update([
                'attendance_status' => $attendanceStatus,
                'checked_in_at' => now(),
                'updated_at' => now(),
            ]);

        // Deduct pass credit if changing to attended (and wasn't already attended)
        $creditDeducted = false;
        if ($attendanceStatus === 'attended' && $previousStatus !== 'attended') {
            try {
                $this->passCreditService->deductCredit(
                    $client,
                    "1:1 session check-in for event #{$event->id} (additional guest)"
                );
                $creditDeducted = true;
            } catch (\Exception $e) {
                \Log::warning("Failed to deduct credit for additional client {$client->id} in event {$event->id}: {$e->getMessage()}");
            }
        }

        $event->load(['client', 'additionalClients']);

        return ApiResponse::success([
            'event' => $event,
            'pass_credit_deducted' => $creditDeducted,
            'checked_in_client_id' => $client->id,
            'checked_in_guest_index' => $guestIndex,
        ], 'Check-in recorded for additional guest');
    }

    /**
     * Check in clients for a group class
     *
     * POST /api/staff/classes/{occurrenceId}/checkin
     * Body: { "registrations": [{"registration_id": 123, "attendance_status": "attended"}] }
     */
    public function checkinClass(Request $request, int $occurrenceId): JsonResponse
    {
        $validated = $request->validate([
            'registrations' => ['required', 'array', 'min:1'],
            'registrations.*.registration_id' => ['required', 'integer', 'exists:class_registrations,id'],
            'registrations.*.attendance_status' => ['required', Rule::in(['attended', 'no_show'])],
        ]);

        $occurrence = ClassOccurrence::findOrFail($occurrenceId);

        // Authorization: only assigned trainer or admin
        $user = $request->user();
        if (!$user->isAdmin() && $user->staffProfile?->id !== $occurrence->trainer_id) {
            return ApiResponse::forbidden('Cannot check in for other trainers\' classes');
        }

        $results = DB::transaction(function () use ($validated, $occurrence) {
            $results = [];

            foreach ($validated['registrations'] as $item) {
                $registration = ClassRegistration::with('client')->findOrFail($item['registration_id']);

                // Skip if already checked in
                if ($registration->attendance_status !== null) {
                    $results[] = [
                        'registration_id' => $registration->id,
                        'status' => 'skipped',
                        'reason' => 'Already checked in',
                    ];
                    continue;
                }

                $registration->update([
                    'attendance_status' => $item['attendance_status'],
                    'checked_in_at' => now(),
                ]);

                // Deduct pass credit if attended
                if ($item['attendance_status'] === 'attended' && $registration->client) {
                    $creditsToDeduct = $occurrence->credits_required ?? 1;
                    try {
                        $this->passCreditService->deductCredit(
                            $registration->client,
                            $creditsToDeduct,
                            "Group class: {$occurrence->classTemplate->name}"
                        );

                        $registration->update(['credits_used' => $creditsToDeduct]);
                    } catch (\Exception $e) {
                        \Log::warning("Failed to deduct credit for registration {$registration->id}: {$e->getMessage()}");
                    }
                }

                $results[] = [
                    'registration_id' => $registration->id,
                    'status' => 'success',
                    'attendance_status' => $item['attendance_status'],
                ];
            }

            return $results;
        });

        return ApiResponse::success([
            'occurrence_id' => $occurrenceId,
            'results' => $results,
        ], 'Check-in completed');
    }
}
