<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\Client;
use App\Models\Event;
use App\Services\PassCreditService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Staff controller for managing participants in their own classes and events.
 * Staff can only manage participants in events/classes they are assigned to.
 */
class StaffParticipantController extends Controller
{
    public function __construct(
        private readonly PassCreditService $passCreditService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Get the authenticated staff member's profile
     */
    private function getAuthenticatedStaff(Request $request)
    {
        $user = $request->user();
        $staff = $user->staffProfile;

        if (!$staff) {
            abort(403, 'Only staff members can access this resource');
        }

        return $staff;
    }

    /**
     * List participants for a class occurrence (staff's own class only)
     *
     * GET /api/v1/staff/class-occurrences/{id}/participants
     */
    public function listClassParticipants(Request $request, int $occurrenceId): JsonResponse
    {
        $staff = $this->getAuthenticatedStaff($request);
        $occurrence = ClassOccurrence::with(['registrations.client.user'])->findOrFail($occurrenceId);

        // RBAC: Staff can only view their own classes
        if ($occurrence->staff_id !== $staff->id) {
            return ApiResponse::error('You can only view participants in your own classes', null, 403);
        }

        $participants = $occurrence->registrations->map(function ($registration) {
            return [
                'registration_id' => $registration->id,
                'client_id' => $registration->client_id,
                'client_name' => $registration->client?->user?->name ?? 'Unknown',
                'client_email' => $registration->client?->user?->email ?? '',
                'status' => $registration->status,
                'payment_status' => $registration->payment_status,
                'booked_at' => $registration->booked_at,
                'checked_in_at' => $registration->checked_in_at,
            ];
        });

        return ApiResponse::success([
            'occurrence_id' => $occurrenceId,
            'participants' => $participants,
            'total' => $participants->count(),
            'capacity' => $occurrence->capacity,
        ]);
    }

    /**
     * Add a participant to a class occurrence (staff's own class only)
     *
     * POST /api/v1/staff/class-occurrences/{id}/participants
     */
    public function addClassParticipant(Request $request, int $occurrenceId): JsonResponse
    {
        $staff = $this->getAuthenticatedStaff($request);

        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'status' => ['nullable', 'string', 'in:booked,waitlist'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $occurrence = ClassOccurrence::with(['registrations', 'template'])->findOrFail($occurrenceId);

        // RBAC: Staff can only add to their own classes
        if ($occurrence->staff_id !== $staff->id) {
            return ApiResponse::error('You can only add participants to your own classes', null, 403);
        }

        $client = Client::findOrFail($validated['client_id']);

        // Check if already registered
        $existingRegistration = ClassRegistration::where('occurrence_id', $occurrenceId)
            ->where('client_id', $client->id)
            ->whereIn('status', ['booked', 'waitlist'])
            ->first();

        if ($existingRegistration) {
            return ApiResponse::error('Client is already registered for this class', null, 409);
        }

        // Check if class is cancelled
        if ($occurrence->status === 'cancelled') {
            return ApiResponse::error('Cannot add participants to a cancelled class', null, 422);
        }

        // Determine status based on capacity
        $confirmedCount = $occurrence->registrations()->whereIn('status', ['booked', 'attended'])->count();
        $isFull = $confirmedCount >= $occurrence->capacity;
        $status = $validated['status'] ?? ($isFull ? 'waitlist' : 'booked');

        return DB::transaction(function () use ($occurrence, $client, $status) {
            $creditsUsed = 0;
            $paymentStatus = 'pending';

            // Only process payment for actual bookings (not waitlist)
            if ($status === 'booked') {
                $hasActivePass = $this->passCreditService->hasAvailableCredits($client);
                $creditsRequired = $occurrence->template->credits_required ?? 1;
                $creditPriceHuf = (int) ($occurrence->template->base_price_huf ?? config('booking.credit_price_huf', 1000));

                if ($hasActivePass) {
                    $this->passCreditService->deductCredit(
                        $client,
                        "Staff booked class: {$occurrence->template->title}"
                    );
                    $creditsUsed = $creditsRequired;
                    $paymentStatus = 'paid';
                } else {
                    // Add to unpaid balance
                    $unpaidAmount = $creditPriceHuf * $creditsRequired;
                    $client->increment('unpaid_balance', $unpaidAmount);
                    $paymentStatus = 'unpaid';
                }
            }

            $registration = ClassRegistration::create([
                'occurrence_id' => $occurrence->id,
                'client_id' => $client->id,
                'status' => $status,
                'booked_at' => now(),
                'credits_used' => $creditsUsed,
                'payment_status' => $paymentStatus,
            ]);

            // Queue notification
            $this->notificationService->sendBookingConfirmation($registration);

            return ApiResponse::created(
                $registration->load(['client.user']),
                $status === 'waitlist' ? 'Client added to waitlist' : 'Client added to class'
            );
        });
    }

    /**
     * Remove a participant from a class occurrence (staff's own class only)
     *
     * DELETE /api/v1/staff/class-occurrences/{id}/participants/{clientId}
     */
    public function removeClassParticipant(Request $request, int $occurrenceId, int $clientId): JsonResponse
    {
        $staff = $this->getAuthenticatedStaff($request);

        $validated = $request->validate([
            'refund' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $occurrence = ClassOccurrence::with('template')->findOrFail($occurrenceId);

        // RBAC: Staff can only remove from their own classes
        if ($occurrence->staff_id !== $staff->id) {
            return ApiResponse::error('You can only remove participants from your own classes', null, 403);
        }

        $registration = ClassRegistration::where('occurrence_id', $occurrenceId)
            ->where('client_id', $clientId)
            ->whereIn('status', ['booked', 'waitlist'])
            ->first();

        if (!$registration) {
            return ApiResponse::error('Registration not found', null, 404);
        }

        $refund = $validated['refund'] ?? true;

        return DB::transaction(function () use ($registration, $occurrence, $refund) {
            $wasBooked = $registration->status === 'booked';
            $creditsUsed = $registration->credits_used;
            $paymentStatus = $registration->payment_status;

            $registration->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $refundMessage = '';

            // Handle refunds if requested
            if ($refund && $wasBooked) {
                $creditsRequired = $occurrence->template->credits_required ?? 1;
                $creditPriceHuf = (int) ($occurrence->template->base_price_huf ?? config('booking.credit_price_huf', 1000));

                if ($paymentStatus === 'paid' && $creditsUsed > 0) {
                    $this->passCreditService->refundCredit(
                        $registration->client,
                        $creditsUsed,
                        "Staff removed from class: {$occurrence->template->title}"
                    );
                    $refundMessage = ' and credit refunded';
                } elseif ($paymentStatus === 'unpaid') {
                    $refundAmount = $creditPriceHuf * $creditsRequired;
                    $registration->client->decrement('unpaid_balance', $refundAmount);
                    $refundMessage = ' and unpaid balance reduced';
                }
            }

            // Promote from waitlist if there was a booked slot freed
            if ($wasBooked) {
                $this->promoteFromWaitlist($occurrence);
            }

            return ApiResponse::success([
                'message' => 'Participant removed' . $refundMessage,
            ]);
        });
    }

    /**
     * Get the client assigned to a staff's event
     *
     * GET /api/v1/staff/events/{id}/participant
     */
    public function getEventParticipant(Request $request, int $eventId): JsonResponse
    {
        $staff = $this->getAuthenticatedStaff($request);
        $event = Event::with(['client.user'])->findOrFail($eventId);

        // RBAC: Staff can only view their own events
        if ($event->staff_id !== $staff->id) {
            return ApiResponse::error('You can only view participants in your own events', null, 403);
        }

        if (!$event->client) {
            return ApiResponse::success([
                'event_id' => $eventId,
                'participant' => null,
            ]);
        }

        return ApiResponse::success([
            'event_id' => $eventId,
            'participant' => [
                'client_id' => $event->client_id,
                'client_name' => $event->client?->user?->name ?? 'Unknown',
                'client_email' => $event->client?->user?->email ?? '',
            ],
        ]);
    }

    /**
     * Assign a client to a staff's event (1:1 session)
     *
     * POST /api/v1/staff/events/{id}/participant
     */
    public function assignEventParticipant(Request $request, int $eventId): JsonResponse
    {
        $staff = $this->getAuthenticatedStaff($request);

        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
        ]);

        $event = Event::findOrFail($eventId);

        // RBAC: Staff can only assign to their own events
        if ($event->staff_id !== $staff->id) {
            return ApiResponse::error('You can only assign participants to your own events', null, 403);
        }

        $client = Client::findOrFail($validated['client_id']);

        // Check if event is cancelled
        if ($event->status === 'cancelled') {
            return ApiResponse::error('Cannot assign participant to a cancelled event', null, 422);
        }

        // Check if event already has a client
        if ($event->client_id && $event->client_id !== $client->id) {
            return ApiResponse::error('Event already has a participant assigned. Remove them first.', null, 409);
        }

        $event->update(['client_id' => $client->id]);

        return ApiResponse::success(
            $event->load(['client.user', 'staff.user', 'room']),
            'Participant assigned to event'
        );
    }

    /**
     * Remove the client from a staff's event
     *
     * DELETE /api/v1/staff/events/{id}/participant
     */
    public function removeEventParticipant(Request $request, int $eventId): JsonResponse
    {
        $staff = $this->getAuthenticatedStaff($request);
        $event = Event::findOrFail($eventId);

        // RBAC: Staff can only remove from their own events
        if ($event->staff_id !== $staff->id) {
            return ApiResponse::error('You can only remove participants from your own events', null, 403);
        }

        if (!$event->client_id) {
            return ApiResponse::error('Event has no participant to remove', null, 404);
        }

        $event->update(['client_id' => null]);

        return ApiResponse::success(null, 'Participant removed from event');
    }

    /**
     * Promote the first waitlisted person to confirmed
     */
    private function promoteFromWaitlist(ClassOccurrence $occurrence): void
    {
        $nextWaitlisted = ClassRegistration::with(['occurrence.template', 'client.user'])
            ->where('occurrence_id', $occurrence->id)
            ->where('status', 'waitlist')
            ->orderBy('booked_at')
            ->first();

        if ($nextWaitlisted) {
            $client = $nextWaitlisted->client;
            $hasActivePass = $this->passCreditService->hasAvailableCredits($client);
            $creditsRequired = $occurrence->template->credits_required ?? 1;

            $creditsUsed = 0;
            $paymentStatus = 'unpaid';

            if ($hasActivePass) {
                $this->passCreditService->deductCredit(
                    $client,
                    "Promoted from waitlist: {$occurrence->template->title}"
                );
                $creditsUsed = $creditsRequired;
                $paymentStatus = 'paid';
            } else {
                $creditPriceHuf = (int) ($occurrence->template->base_price_huf ?? config('booking.credit_price_huf', 1000));
                $unpaidAmount = $creditPriceHuf * $creditsRequired;
                $client->increment('unpaid_balance', $unpaidAmount);
            }

            $nextWaitlisted->update([
                'status' => 'booked',
                'credits_used' => $creditsUsed,
                'payment_status' => $paymentStatus,
            ]);

            $this->notificationService->sendWaitlistPromotion($nextWaitlisted);
        }
    }
}
