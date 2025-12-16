<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\BookClassRequest;
use App\Http\Requests\Classes\CancelBookingRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Services\NotificationService;
use App\Services\PassCreditService;
use App\Exceptions\PolicyViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClassBookingController extends Controller
{
    public function __construct(
        private readonly PassCreditService $passCreditService,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Book a class or join waitlist
     *
     * POST /api/classes/{occurrenceId}/book
     */
    public function book(BookClassRequest $request, int $occurrenceId): JsonResponse
    {
        $occurrence = ClassOccurrence::with(['registrations', 'template'])->findOrFail($occurrenceId);
        $client = $request->user()->client;

        if (!$client) {
            return ApiResponse::error('Only clients can book classes', null, 403);
        }

        // Check if already registered
        $existingRegistration = ClassRegistration::where('occurrence_id', $occurrenceId)
            ->where('client_id', $client->id)
            ->whereIn('status', ['booked', 'waitlist'])
            ->first();

        if ($existingRegistration) {
            return ApiResponse::error('Already registered for this class', null, 409);
        }

        // Business rule validations (451)
        if ($occurrence->status === 'cancelled') {
            throw new PolicyViolationException('Cannot book cancelled class');
        }

        if ($occurrence->starts_at < now()) {
            throw new PolicyViolationException('Cannot book past class');
        }

        // Check if class is full
        $confirmedCount = $occurrence->registrations()->whereIn('status', ['booked', 'attended'])->count();
        $isFull = $confirmedCount >= $occurrence->capacity;

        // Check if client has active pass
        $hasActivePass = $this->passCreditService->hasAvailableCredits($client);

        // Get credit price from template or fall back to config
        $creditPriceHuf = (int) ($occurrence->template->base_price_huf ?? config('booking.credit_price_huf', 1000));
        $creditsRequired = $occurrence->template->credits_required ?? 1;

        return DB::transaction(function () use ($occurrence, $client, $isFull, $hasActivePass, $creditPriceHuf, $creditsRequired) {
            $creditsUsed = 0;
            $paymentStatus = 'pending'; // Default for waitlist

            // Only process payment/credits for actual bookings (not waitlist)
            if (!$isFull) {
                if ($hasActivePass) {
                    // Has active pass - deduct credits
                    $this->passCreditService->deductCredit(
                        $client,
                        "Booked class: {$occurrence->template->title}"
                    );
                    $creditsUsed = $creditsRequired;
                    $paymentStatus = 'paid';
                } else {
                    // No active pass - add to unpaid balance
                    $unpaidAmount = $creditPriceHuf * $creditsRequired;
                    $client->increment('unpaid_balance', $unpaidAmount);
                    $paymentStatus = 'unpaid';
                }
            }

            $registration = ClassRegistration::create([
                'occurrence_id' => $occurrence->id,
                'client_id' => $client->id,
                'status' => $isFull ? 'waitlist' : 'booked',
                'booked_at' => now(),
                'credits_used' => $creditsUsed,
                'payment_status' => $paymentStatus,
            ]);

            // Queue notification
            $this->notificationService->sendBookingConfirmation($registration);

            $message = $isFull ? 'Added to waitlist' : 'Booking confirmed';
            if (!$isFull && !$hasActivePass) {
                $message = 'Booking confirmed (added to unpaid balance)';
            }

            return ApiResponse::created(
                $registration->load(['occurrence', 'client']),
                $message
            );
        });
    }

    /**
     * Cancel a class booking
     *
     * POST /api/classes/{occurrenceId}/cancel
     */
    public function cancel(CancelBookingRequest $request, int $occurrenceId): JsonResponse
    {
        $client = $request->user()->client;

        if (!$client) {
            return ApiResponse::error('Only clients can cancel bookings', null, 403);
        }

        // Check if registration exists
        $registration = ClassRegistration::where('occurrence_id', $occurrenceId)
            ->where('client_id', $client->id)
            ->first();

        if (!$registration) {
            return ApiResponse::error('No booking found for this class', null, 404);
        }

        if ($registration->status === 'cancelled') {
            return ApiResponse::error('Booking is already cancelled', null, 409);
        }

        $occurrence = ClassOccurrence::with('template')->findOrFail($occurrenceId);

        // Check 24h cancellation window (configurable)
        $cancellationWindowHours = (int) config('booking.cancellation_window_hours', 24);
        $hoursUntilClass = now()->diffInHours($occurrence->starts_at, absolute: false);
        $canCancelFreely = $hoursUntilClass >= $cancellationWindowHours;

        if (!$canCancelFreely) {
            return ApiResponse::error('Cannot cancel within 24 hours of class start', null, 423);
        }

        // Get credit price from template or fall back to config
        $creditPriceHuf = (int) ($occurrence->template->base_price_huf ?? config('booking.credit_price_huf', 1000));
        $creditsRequired = $occurrence->template->credits_required ?? 1;

        return DB::transaction(function () use ($registration, $occurrence, $canCancelFreely, $creditPriceHuf, $creditsRequired) {
            // Store original status and payment info before updating
            $wasBooked = $registration->status === 'booked';
            $creditsUsed = $registration->credits_used;
            $paymentStatus = $registration->payment_status;

            $registration->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Promote next waitlist person if was booked
            if ($wasBooked) {
                $this->promoteFromWaitlist($occurrence);
            }

            $refundMessage = '';

            // Handle refunds based on payment status
            if ($canCancelFreely && $wasBooked) {
                if ($paymentStatus === 'paid' && $creditsUsed > 0) {
                    // Refund pass credits
                    $this->passCreditService->refundCredit(
                        $registration->client,
                        $creditsUsed,
                        "Refund for cancelled class: {$occurrence->template->title}"
                    );
                    $refundMessage = ' and credit refunded';
                } elseif ($paymentStatus === 'unpaid') {
                    // Refund unpaid balance
                    $refundAmount = $creditPriceHuf * $creditsRequired;
                    $registration->client->decrement('unpaid_balance', $refundAmount);
                    $refundMessage = ' and unpaid balance reduced';
                }
            }

            $message = 'Booking cancelled' . $refundMessage;

            return ApiResponse::success([
                'registration' => $registration,
                'credits_refunded' => ($canCancelFreely && $paymentStatus === 'paid') ? $creditsUsed : 0,
                'unpaid_balance_reduced' => ($canCancelFreely && $paymentStatus === 'unpaid') ? ($creditPriceHuf * $creditsRequired) : 0,
            ], $message);
        });
    }

    /**
     * Promote the first waitlisted person to confirmed
     *
     * When promoting from waitlist, we need to handle payment:
     * - If client has active pass: deduct credits, set payment_status = 'paid'
     * - If no active pass: add to unpaid_balance, set payment_status = 'unpaid'
     */
    private function promoteFromWaitlist(ClassOccurrence $occurrence): void
    {
        // Ensure template is loaded
        if (!$occurrence->relationLoaded('template')) {
            $occurrence->load('template');
        }

        $nextWaitlisted = ClassRegistration::with(['client.user'])
            ->where('occurrence_id', $occurrence->id)
            ->where('status', 'waitlist')
            ->orderBy('booked_at')
            ->first();

        if ($nextWaitlisted) {
            $client = $nextWaitlisted->client;
            $hasActivePass = $this->passCreditService->hasAvailableCredits($client);
            $creditsRequired = $occurrence->template?->credits_required ?? 1;

            $creditsUsed = 0;
            $paymentStatus = 'unpaid';

            if ($hasActivePass) {
                // Has active pass - deduct credits
                $templateTitle = $occurrence->template?->title ?? 'Class';
                $this->passCreditService->deductCredit(
                    $client,
                    "Promoted from waitlist: {$templateTitle}"
                );
                $creditsUsed = $creditsRequired;
                $paymentStatus = 'paid';
            } else {
                // No active pass - add to unpaid balance
                $creditPriceHuf = (int) ($occurrence->template?->base_price_huf ?? config('booking.credit_price_huf', 1000));
                $unpaidAmount = $creditPriceHuf * $creditsRequired;
                $client->increment('unpaid_balance', $unpaidAmount);
            }

            $nextWaitlisted->update([
                'status' => 'booked',
                'credits_used' => $creditsUsed,
                'payment_status' => $paymentStatus,
            ]);

            // Notify promoted client
            $this->notificationService->sendWaitlistPromotion($nextWaitlisted);
        }
    }
}
