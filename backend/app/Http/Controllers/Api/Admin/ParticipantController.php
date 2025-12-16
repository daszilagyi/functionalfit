<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

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
 * Admin controller for managing participants in classes and events.
 * Admin has full access to add/remove participants from any class or event.
 */
class ParticipantController extends Controller
{
    public function __construct(
        private readonly PassCreditService $passCreditService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * List participants for a class occurrence
     *
     * GET /api/v1/admin/class-occurrences/{id}/participants
     */
    public function listClassParticipants(int $occurrenceId): JsonResponse
    {
        $occurrence = ClassOccurrence::with(['registrations.client.user'])->findOrFail($occurrenceId);

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
     * Add a participant to a class occurrence
     *
     * POST /api/v1/admin/class-occurrences/{id}/participants
     */
    public function addClassParticipant(Request $request, int $occurrenceId): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'status' => ['nullable', 'string', 'in:booked,waitlist'],
            'skip_payment' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $occurrence = ClassOccurrence::with(['registrations', 'template'])->findOrFail($occurrenceId);
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

        // If admin forces 'booked' but class is full, allow override
        $skipPayment = $validated['skip_payment'] ?? false;

        return DB::transaction(function () use ($occurrence, $client, $status, $skipPayment) {
            $creditsUsed = 0;
            $paymentStatus = 'pending';

            // Only process payment for actual bookings (not waitlist) unless skip_payment
            if ($status === 'booked' && !$skipPayment) {
                $hasActivePass = $this->passCreditService->hasAvailableCredits($client);
                $creditsRequired = $occurrence->template->credits_required ?? 1;
                $creditPriceHuf = (int) ($occurrence->template->base_price_huf ?? config('booking.credit_price_huf', 1000));

                if ($hasActivePass) {
                    $this->passCreditService->deductCredit(
                        $client,
                        "Admin booked class: {$occurrence->template->title}"
                    );
                    $creditsUsed = $creditsRequired;
                    $paymentStatus = 'paid';
                } else {
                    // Add to unpaid balance
                    $unpaidAmount = $creditPriceHuf * $creditsRequired;
                    $client->increment('unpaid_balance', $unpaidAmount);
                    $paymentStatus = 'unpaid';
                }
            } elseif ($skipPayment && $status === 'booked') {
                $paymentStatus = 'comped'; // Complimentary booking
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
     * Remove a participant from a class occurrence
     *
     * DELETE /api/v1/admin/class-occurrences/{id}/participants/{clientId}
     */
    public function removeClassParticipant(Request $request, int $occurrenceId, int $clientId): JsonResponse
    {
        $validated = $request->validate([
            'refund' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $registration = ClassRegistration::where('occurrence_id', $occurrenceId)
            ->where('client_id', $clientId)
            ->whereIn('status', ['booked', 'waitlist'])
            ->first();

        if (!$registration) {
            return ApiResponse::error('Registration not found', null, 404);
        }

        $occurrence = ClassOccurrence::with('template')->findOrFail($occurrenceId);
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
                        "Admin removed from class: {$occurrence->template->title}"
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
     * Get the client assigned to an event
     *
     * GET /api/v1/admin/events/{id}/participant
     */
    public function getEventParticipant(int $eventId): JsonResponse
    {
        $event = Event::with(['client.user'])->findOrFail($eventId);

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
     * Assign a client to an event (1:1 session)
     *
     * POST /api/v1/admin/events/{id}/participant
     */
    public function assignEventParticipant(Request $request, int $eventId): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
        ]);

        $event = Event::findOrFail($eventId);
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
     * Remove the client from an event
     *
     * DELETE /api/v1/admin/events/{id}/participant
     */
    public function removeEventParticipant(int $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);

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
