<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendBookingCancellation;
use App\Jobs\SendClassDeleted;
use App\Jobs\SendClassModified;
use App\Jobs\SendClassReminder;
use App\Jobs\SendEventNotification;
use App\Jobs\SendPasswordReset;
use App\Jobs\SendRegistrationConfirmation;
use App\Jobs\SendUserDeleted;
use App\Jobs\SendWaitlistPromotion;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send registration confirmation email to a newly registered user.
     */
    public function sendRegistrationConfirmation(User $user): void
    {
        try {
            SendRegistrationConfirmation::dispatch($user)
                ->onQueue('notifications');

            Log::info('Registration confirmation queued', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue registration confirmation', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send password reset email to a user.
     *
     * @param User $user The user requesting password reset
     * @param string $token The password reset token
     */
    public function sendPasswordReset(User $user, string $token): void
    {
        try {
            SendPasswordReset::dispatch($user, $token)
                ->onQueue('notifications');

            Log::info('Password reset email queued', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue password reset email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification email when admin deletes a user.
     *
     * @param User $user The user being deleted (must capture data before deletion)
     * @param User $deletedBy The admin who performed the deletion
     */
    public function sendUserDeleted(User $user, User $deletedBy): void
    {
        try {
            // Capture user data before it gets deleted
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];

            SendUserDeleted::dispatch($userData, $deletedBy->name)
                ->onQueue('notifications');

            Log::info('User deleted notification queued', [
                'user_id' => $user->id,
                'deleted_by' => $deletedBy->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue user deleted notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send class modification notifications to all participants.
     *
     * @param ClassOccurrence $occurrence The modified class occurrence
     * @param User $modifiedBy The user who made the modification
     * @param array<string, mixed> $changes Array of changes (old/new values)
     */
    public function sendClassModified(ClassOccurrence $occurrence, User $modifiedBy, array $changes): void
    {
        try {
            // Get all registrations (booked + waitlist)
            $registrations = $occurrence->registrations()
                ->whereIn('status', ['booked', 'waitlist'])
                ->with(['client.user'])
                ->get();

            if ($registrations->isEmpty()) {
                Log::info('No participants to notify for class modification', [
                    'occurrence_id' => $occurrence->id,
                ]);
                return;
            }

            $dispatchedCount = 0;
            foreach ($registrations as $registration) {
                SendClassModified::dispatch($registration, $modifiedBy->name, $changes)
                    ->onQueue('notifications');
                $dispatchedCount++;
            }

            Log::info('Class modified notifications queued', [
                'occurrence_id' => $occurrence->id,
                'modified_by' => $modifiedBy->id,
                'participants_notified' => $dispatchedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue class modified notifications', [
                'occurrence_id' => $occurrence->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send class deletion notifications to all participants.
     *
     * @param ClassOccurrence $occurrence The class occurrence being deleted (data captured before deletion)
     * @param User $deletedBy The admin/staff who deleted the class
     * @param bool $notifyParticipants Whether to send notifications (can be toggled from UI)
     */
    public function sendClassDeleted(ClassOccurrence $occurrence, User $deletedBy, bool $notifyParticipants = true): void
    {
        if (!$notifyParticipants) {
            Log::info('Class deletion notifications skipped by user preference', [
                'occurrence_id' => $occurrence->id,
            ]);
            return;
        }

        try {
            // Capture class data before deletion
            $classData = [
                'occurrence_id' => $occurrence->id,
                'title' => $occurrence->template->title,
                'starts_at' => $occurrence->starts_at->format('Y-m-d H:i'),
                'ends_at' => $occurrence->ends_at->format('Y-m-d H:i'),
                'room' => $occurrence->room->name,
                'trainer' => $occurrence->trainer->user->name,
            ];

            // Get all registrations (booked + waitlist)
            $registrations = $occurrence->registrations()
                ->whereIn('status', ['booked', 'waitlist'])
                ->with(['client.user'])
                ->get();

            if ($registrations->isEmpty()) {
                Log::info('No participants to notify for class deletion', [
                    'occurrence_id' => $occurrence->id,
                ]);
                return;
            }

            $dispatchedCount = 0;
            foreach ($registrations as $registration) {
                // Capture participant data
                $participantData = [
                    'user_id' => $registration->client->user->id,
                    'name' => $registration->client->user->name,
                    'email' => $registration->client->user->email,
                    'registration_status' => $registration->status,
                ];

                SendClassDeleted::dispatch($participantData, $classData, $deletedBy->name)
                    ->onQueue('notifications');
                $dispatchedCount++;
            }

            Log::info('Class deleted notifications queued', [
                'occurrence_id' => $occurrence->id,
                'deleted_by' => $deletedBy->id,
                'participants_notified' => $dispatchedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue class deleted notifications', [
                'occurrence_id' => $occurrence->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send booking confirmation notification.
     */
    public function sendBookingConfirmation(ClassRegistration $registration): void
    {
        try {
            SendBookingConfirmation::dispatch($registration)
                ->onQueue('notifications');

            Log::info('Booking confirmation queued', [
                'registration_id' => $registration->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue booking confirmation', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send booking cancellation notification.
     */
    public function sendBookingCancellation(ClassRegistration $registration): void
    {
        try {
            SendBookingCancellation::dispatch($registration)
                ->onQueue('notifications');

            Log::info('Booking cancellation queued', [
                'registration_id' => $registration->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue booking cancellation', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send waitlist promotion notification.
     */
    public function sendWaitlistPromotion(ClassRegistration $registration): void
    {
        try {
            SendWaitlistPromotion::dispatch($registration)
                ->onQueue('notifications');

            Log::info('Waitlist promotion queued', [
                'registration_id' => $registration->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue waitlist promotion', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send class reminder notification.
     */
    public function sendClassReminder(ClassRegistration $registration, int $hoursBeforeClass = 24): void
    {
        try {
            SendClassReminder::dispatch($registration, $hoursBeforeClass)
                ->onQueue('notifications');

            Log::info('Class reminder queued', [
                'registration_id' => $registration->id,
                'hours_before' => $hoursBeforeClass,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue class reminder', [
                'registration_id' => $registration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send event confirmation notification.
     */
    public function sendEventConfirmation(Event $event): void
    {
        try {
            SendEventNotification::dispatch($event, 'event_confirmation')
                ->onQueue('notifications');

            Log::info('Event confirmation queued', [
                'event_id' => $event->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue event confirmation', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send event update notification.
     */
    public function sendEventUpdate(Event $event): void
    {
        try {
            SendEventNotification::dispatch($event, 'event_update')
                ->onQueue('notifications');

            Log::info('Event update queued', [
                'event_id' => $event->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue event update', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send event cancellation notification.
     */
    public function sendEventCancellation(Event $event): void
    {
        try {
            SendEventNotification::dispatch($event, 'event_cancellation')
                ->onQueue('notifications');

            Log::info('Event cancellation queued', [
                'event_id' => $event->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue event cancellation', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send event reminder notification.
     */
    public function sendEventReminder(Event $event): void
    {
        try {
            SendEventNotification::dispatch($event, 'event_reminder')
                ->onQueue('notifications');

            Log::info('Event reminder queued', [
                'event_id' => $event->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue event reminder', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send class rescheduled notification.
     *
     * @deprecated Use sendClassModified() for more detailed notifications
     */
    public function sendClassRescheduled(ClassRegistration $registration): void
    {
        // Reuse booking confirmation for now with updated data
        $this->sendBookingConfirmation($registration);
    }

    /**
     * Send class cancelled notification.
     *
     * @deprecated Use sendClassDeleted() for bulk notifications
     */
    public function sendClassCancelled(ClassRegistration $registration): void
    {
        $this->sendBookingCancellation($registration);
    }
}
