<?php

declare(strict_types=1);

use App\Jobs\SendBookingCancellation;
use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendClassReminder;
use App\Jobs\SendEventNotification;
use App\Jobs\SendWaitlistPromotion;
use App\Mail\BookingCancellation as BookingCancellationMail;
use App\Mail\BookingConfirmation as BookingConfirmationMail;
use App\Mail\ClassReminder as ClassReminderMail;
use App\Mail\EventNotification as EventNotificationMail;
use App\Mail\WaitlistPromotion as WaitlistPromotionMail;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\ClassTemplate;
use App\Models\Client;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

describe('SendBookingConfirmation Job', function () {
    beforeEach(function () {
        // Don't fake Mail for this test suite - we want to test actual dispatch
        Mail::fake();

        // Create test data
        $this->user = User::factory()->client()->create(['email' => 'client@example.com']);
        $this->client = Client::factory()->create(['user_id' => $this->user->id]);
        $this->trainer = StaffProfile::factory()->create();
        $this->room = Room::factory()->create(['name' => 'Studio A']);
        $this->template = ClassTemplate::factory()->create([
            'title' => 'Morning Yoga',
        ]);
        $this->occurrence = ClassOccurrence::factory()->create([
            'template_id' => $this->template->id,
            'trainer_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHour(),
        ]);
        $this->registration = ClassRegistration::factory()->create([
            'occurrence_id' => $this->occurrence->id,
            'client_id' => $this->client->id,
            'status' => 'confirmed',
        ]);
    });

    it('dispatches and handles SendBookingConfirmation job successfully', function () {
        // Act: Execute the job
        $job = new SendBookingConfirmation($this->registration);
        $job->handle();

        // Assert: Email was sent to the correct recipient
        Mail::assertSent(BookingConfirmationMail::class, function ($mail) {
            return $mail->hasTo('client@example.com');
        });

        // Assert: Notification record was created with 'sent' status
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'booking_confirmation',
            'channel' => 'email',
            'status' => 'sent',
        ]);

        // Assert: Notification has correct subject
        $notification = Notification::where('user_id', $this->user->id)->first();
        expect($notification->subject)->toContain('Morning Yoga');
    });

    it('retries SendBookingConfirmation job on failure', function () {
        // Arrange: Simulate SMTP failure
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \Exception('SMTP connection failed'));

        // Act & Assert: Job should throw exception to trigger retry
        $job = new SendBookingConfirmation($this->registration);

        expect(fn() => $job->handle())
            ->toThrow(\Exception::class, 'SMTP connection failed');

        // Assert: Notification record marked as failed
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'booking_confirmation',
            'status' => 'failed',
        ]);

        // Assert: Job configuration allows 3 retries
        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe(60);
    });
});

describe('SendBookingCancellation Job', function () {
    beforeEach(function () {
        Mail::fake();

        // Create test data
        $this->user = User::factory()->client()->create(['email' => 'client@example.com']);
        $this->client = Client::factory()->create(['user_id' => $this->user->id]);
        $this->occurrence = ClassOccurrence::factory()->create([
            'starts_at' => Carbon::now()->addDays(2),
        ]);
        $this->registration = ClassRegistration::factory()->create([
            'occurrence_id' => $this->occurrence->id,
            'client_id' => $this->client->id,
            'status' => 'cancelled',
        ]);
    });

    it('sends cancellation email with credit refund notice', function () {
        // Act: Execute the job
        $job = new SendBookingCancellation($this->registration, true); // credit_refunded = true
        $job->handle();

        // Assert: Cancellation email was sent
        Mail::assertSent(BookingCancellationMail::class, function ($mail) {
            return $mail->hasTo('client@example.com');
        });

        // Assert: Notification record created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'booking_cancellation',
            'channel' => 'email',
            'status' => 'sent',
        ]);
    });
});

describe('SendWaitlistPromotion Job', function () {
    beforeEach(function () {
        Mail::fake();

        // Create test data
        $this->user = User::factory()->client()->create(['email' => 'waitlist@example.com']);
        $this->client = Client::factory()->create(['user_id' => $this->user->id]);
        $this->occurrence = ClassOccurrence::factory()->create([
            'starts_at' => Carbon::now()->addDays(3),
        ]);
        $this->registration = ClassRegistration::factory()->create([
            'occurrence_id' => $this->occurrence->id,
            'client_id' => $this->client->id,
            'status' => 'waitlist',
        ]);
    });

    it('sends promotion email when waitlist client gets confirmed', function () {
        // Act: Execute the job
        $job = new SendWaitlistPromotion($this->registration);
        $job->handle();

        // Assert: Promotion email was sent
        Mail::assertSent(WaitlistPromotionMail::class, function ($mail) {
            return $mail->hasTo('waitlist@example.com');
        });

        // Assert: Notification record created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'waitlist_promotion',
            'channel' => 'email',
            'status' => 'sent',
        ]);

        // Assert: Registration status updated to confirmed
        $this->registration->refresh();
        expect($this->registration->status)->toBe('confirmed');
    });
});

describe('SendClassReminder Job', function () {
    beforeEach(function () {
        Mail::fake();

        // Create class occurrence 24 hours in the future
        $this->trainer = StaffProfile::factory()->create();
        $this->room = Room::factory()->create();
        $this->template = ClassTemplate::factory()->create(['title' => 'Evening Spin']);
        $this->occurrence = ClassOccurrence::factory()->create([
            'template_id' => $this->template->id,
            'trainer_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHour(),
        ]);

        // Create multiple confirmed registrations
        $this->confirmedClients = [];
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->client()->create([
                'email' => "confirmed{$i}@example.com",
            ]);
            $client = Client::factory()->create(['user_id' => $user->id]);
            ClassRegistration::factory()->create([
                'occurrence_id' => $this->occurrence->id,
                'client_id' => $client->id,
                'status' => 'confirmed',
            ]);
            $this->confirmedClients[] = ['user' => $user, 'client' => $client];
        }

        // Create cancelled and no_show registrations (should NOT receive reminders)
        $cancelledUser = User::factory()->client()->create(['email' => 'cancelled@example.com']);
        $cancelledClient = Client::factory()->create(['user_id' => $cancelledUser->id]);
        ClassRegistration::factory()->create([
            'occurrence_id' => $this->occurrence->id,
            'client_id' => $cancelledClient->id,
            'status' => 'cancelled',
        ]);

        $noShowUser = User::factory()->client()->create(['email' => 'noshow@example.com']);
        $noShowClient = Client::factory()->create(['user_id' => $noShowUser->id]);
        ClassRegistration::factory()->create([
            'occurrence_id' => $this->occurrence->id,
            'client_id' => $noShowClient->id,
            'status' => 'no_show',
        ]);
    });

    it('sends reminders only to confirmed registrations', function () {
        // Act: Execute the job
        $job = new SendClassReminder($this->occurrence);
        $job->handle();

        // Assert: Reminders sent to all confirmed clients (3)
        Mail::assertSent(ClassReminderMail::class, 3);

        // Assert: Confirmed clients received emails
        foreach ($this->confirmedClients as $clientData) {
            Mail::assertSent(ClassReminderMail::class, function ($mail) use ($clientData) {
                return $mail->hasTo($clientData['user']->email);
            });
        }

        // Assert: Cancelled and no_show clients did NOT receive emails
        Mail::assertNotSent(ClassReminderMail::class, function ($mail) {
            return $mail->hasTo('cancelled@example.com');
        });

        Mail::assertNotSent(ClassReminderMail::class, function ($mail) {
            return $mail->hasTo('noshow@example.com');
        });

        // Assert: Notification records created for confirmed clients only
        $notificationCount = Notification::where('type', 'class_reminder')
            ->where('status', 'sent')
            ->count();
        expect($notificationCount)->toBe(3);
    });
});

describe('SendEventNotification Job', function () {
    beforeEach(function () {
        Mail::fake();

        // Create test data for 1:1 event
        $this->staffUser = User::factory()->staff()->create(['email' => 'trainer@example.com']);
        $this->staff = StaffProfile::factory()->create(['user_id' => $this->staffUser->id]);

        $this->clientUser = User::factory()->client()->create(['email' => 'client@example.com']);
        $this->client = Client::factory()->create(['user_id' => $this->clientUser->id]);

        $this->room = Room::factory()->create();

        $this->event = Event::factory()->create([
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'type' => 'INDIVIDUAL',
            'status' => 'scheduled',
            'starts_at' => Carbon::now()->addDays(2),
            'ends_at' => Carbon::now()->addDays(2)->addHour(),
        ]);
    });

    it('sends event confirmation notification', function () {
        // Act: Execute the job with 'confirmation' type
        $job = new SendEventNotification($this->event, 'confirmation');
        $job->handle();

        // Assert: Confirmation email was sent to client
        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('client@example.com');
        });

        // Assert: Notification record created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->clientUser->id,
            'type' => 'event_confirmation',
            'channel' => 'email',
            'status' => 'sent',
        ]);
    });

    it('sends event update notification', function () {
        // Act: Execute the job with 'update' type
        $job = new SendEventNotification($this->event, 'update');
        $job->handle();

        // Assert: Update email was sent
        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('client@example.com');
        });

        // Assert: Notification type is correct
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->clientUser->id,
            'type' => 'event_update',
            'status' => 'sent',
        ]);
    });

    it('sends event cancellation notification', function () {
        // Arrange: Update event to cancelled
        $this->event->update(['status' => 'cancelled']);

        // Act: Execute the job with 'cancellation' type
        $job = new SendEventNotification($this->event, 'cancellation');
        $job->handle();

        // Assert: Cancellation email was sent
        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('client@example.com');
        });

        // Assert: Notification type is correct
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->clientUser->id,
            'type' => 'event_cancellation',
            'status' => 'sent',
        ]);
    });

    it('sends event reminder notification 24h before', function () {
        // Arrange: Event is 24h away
        $this->event->update([
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addDay()->addHour(),
        ]);

        // Act: Execute the job with 'reminder' type
        $job = new SendEventNotification($this->event, 'reminder');
        $job->handle();

        // Assert: Reminder email was sent
        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('client@example.com');
        });

        // Assert: Notification type is correct
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->clientUser->id,
            'type' => 'event_reminder',
            'status' => 'sent',
        ]);
    });
});

describe('Notification Job Failure Handling', function () {
    it('marks notification as failed after max retries', function () {
        // Arrange: Create registration
        $user = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $occurrence = ClassOccurrence::factory()->create();
        $registration = ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $client->id,
        ]);

        // Simulate persistent SMTP failure
        Mail::shouldReceive('to')
            ->times(3)
            ->andThrow(new \Exception('Persistent SMTP failure'));

        // Act: Try to send 3 times (simulating retry mechanism)
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $job = new SendBookingConfirmation($registration);
                $job->handle();
            } catch (\Exception $e) {
                // Expected failure
            }
        }

        // Assert: Notification exists and is marked as failed
        $notification = Notification::where('user_id', $user->id)
            ->where('type', 'booking_confirmation')
            ->first();

        expect($notification)->not->toBeNull();
        expect($notification->status)->toBe('failed');
        expect($notification->error_message)->toContain('Persistent SMTP failure');
    });

    it('verifies job retry configuration', function () {
        // Arrange
        $user = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $occurrence = ClassOccurrence::factory()->create();
        $registration = ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $client->id,
        ]);

        // Act: Create job instances
        $confirmationJob = new SendBookingConfirmation($registration);
        $cancellationJob = new SendBookingCancellation($registration, false);
        $waitlistJob = new SendWaitlistPromotion($registration);

        // Assert: All notification jobs have retry configuration
        expect($confirmationJob->tries)->toBe(3);
        expect($confirmationJob->backoff)->toBe(60);

        expect($cancellationJob->tries)->toBe(3);
        expect($waitlistJob->tries)->toBe(3);
    });
});
