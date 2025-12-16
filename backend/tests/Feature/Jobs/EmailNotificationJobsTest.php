<?php

declare(strict_types=1);

use App\Jobs\SendBookingConfirmation;
use App\Jobs\SendBookingCancellation;
use App\Jobs\SendClassReminder;
use App\Jobs\SendEventNotification;
use App\Jobs\SendWaitlistPromotion;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\ClassTemplate;
use App\Models\Client;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
});

describe('SendBookingConfirmation Job', function () {
    it('queues booking confirmation email job', function () {
        $client = Client::factory()->create();
        $template = ClassTemplate::factory()->create();
        $occurrence = ClassOccurrence::factory()->create(['template_id' => $template->id]);
        $registration = ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $client->id,
            'status' => 'booked',
        ]);

        SendBookingConfirmation::dispatch($registration);

        Queue::assertPushed(SendBookingConfirmation::class, function ($job) use ($registration) {
            return $job->registration->id === $registration->id;
        });
    });

    it('sends booking confirmation email with correct data', function () {
        $user = User::factory()->client()->create(['email' => 'client@example.com']);
        $client = Client::factory()->create(['user_id' => $user->id]);
        $room = Room::factory()->create(['name' => 'Studio A']);
        $template = ClassTemplate::factory()->create([
            'title' => 'Morning Yoga',
            'room_id' => $room->id,
        ]);
        $occurrence = ClassOccurrence::factory()->create([
            'template_id' => $template->id,
            'starts_at' => now()->addDays(2)->setTime(9, 0),
        ]);
        $registration = ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $client->id,
            'status' => 'booked',
        ]);

        // Execute job synchronously
        (new SendBookingConfirmation($registration))->handle();

        // Verify email was sent (using Mail fake)
        Mail::assertSent(\App\Mail\BookingConfirmation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    });

    it('handles job retry on failure', function () {
        $registration = ClassRegistration::factory()->create();

        $job = new SendBookingConfirmation($registration);

        // Verify retry configuration
        expect($job->tries)->toBe(3);
        expect($job->backoff)->toBe(60);
    });
});

describe('SendBookingCancellation Job', function () {
    it('sends cancellation email when booking is cancelled', function () {
        $user = User::factory()->client()->create(['email' => 'client@example.com']);
        $client = Client::factory()->create(['user_id' => $user->id]);
        $registration = ClassRegistration::factory()->create([
            'client_id' => $client->id,
            'status' => 'cancelled',
        ]);

        (new SendBookingCancellation($registration))->handle();

        Mail::assertSent(\App\Mail\BookingCancellation::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    });

    it('uses notifications queue for cancellation emails', function () {
        $registration = ClassRegistration::factory()->create();

        SendBookingCancellation::dispatch($registration);

        Queue::assertPushedOn('notifications', SendBookingCancellation::class);
    });
});

describe('SendWaitlistPromotion Job', function () {
    it('sends waitlist promotion email', function () {
        $user = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $registration = ClassRegistration::factory()->create([
            'client_id' => $client->id,
            'status' => 'booked', // Promoted from waitlist
        ]);

        (new SendWaitlistPromotion($registration))->handle();

        Mail::assertSent(\App\Mail\WaitlistPromotion::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    });
});

describe('SendClassReminder Job', function () {
    it('sends reminder email 24 hours before class', function () {
        $user = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $occurrence = ClassOccurrence::factory()->create([
            'starts_at' => now()->addDay(),
        ]);
        $registration = ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $client->id,
            'status' => 'booked',
        ]);

        (new SendClassReminder($registration))->handle();

        Mail::assertSent(\App\Mail\ClassReminder::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    });

    it('does not send reminder for cancelled registrations', function () {
        $occurrence = ClassOccurrence::factory()->create([
            'starts_at' => now()->addDay(),
        ]);
        $registration = ClassRegistration::factory()->create([
            'occurrence_id' => $occurrence->id,
            'status' => 'cancelled',
        ]);

        (new SendClassReminder($registration))->handle();

        Mail::assertNothingSent();
    });
});

describe('SendEventNotification Job', function () {
    it('sends event confirmation for new 1:1 events', function () {
        $clientUser = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $clientUser->id]);
        $staffUser = User::factory()->staff()->create();
        $staffProfile = \App\Models\StaffProfile::factory()->create(['user_id' => $staffUser->id]);

        $event = Event::factory()->create([
            'type' => 'INDIVIDUAL',
            'staff_id' => $staffProfile->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDays(3),
        ]);

        (new SendEventNotification($event, 'event_confirmation'))->handle();

        Mail::assertSent(\App\Mail\EventNotification::class, function ($mail) use ($clientUser) {
            return $mail->hasTo($clientUser->email);
        });
    });

    it('sends event update notification when event is modified', function () {
        $clientUser = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $clientUser->id]);
        $staffUser = User::factory()->staff()->create();
        $staffProfile = \App\Models\StaffProfile::factory()->create(['user_id' => $staffUser->id]);

        $event = Event::factory()->create([
            'type' => 'INDIVIDUAL',
            'staff_id' => $staffProfile->id,
            'client_id' => $client->id,
        ]);

        (new SendEventNotification($event, 'event_update'))->handle();

        Mail::assertSent(\App\Mail\EventNotification::class);
    });

    it('sends event cancellation notification', function () {
        $clientUser = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $clientUser->id]);
        $staffUser = User::factory()->staff()->create();
        $staffProfile = \App\Models\StaffProfile::factory()->create(['user_id' => $staffUser->id]);

        $event = Event::factory()->create([
            'type' => 'INDIVIDUAL',
            'staff_id' => $staffProfile->id,
            'client_id' => $client->id,
            'status' => 'cancelled',
        ]);

        (new SendEventNotification($event, 'event_cancellation'))->handle();

        Mail::assertSent(\App\Mail\EventNotification::class);
    });

    it('sends event reminder 24h before appointment', function () {
        $clientUser = User::factory()->client()->create();
        $client = Client::factory()->create(['user_id' => $clientUser->id]);
        $staffUser = User::factory()->staff()->create();
        $staffProfile = \App\Models\StaffProfile::factory()->create(['user_id' => $staffUser->id]);

        $event = Event::factory()->create([
            'type' => 'INDIVIDUAL',
            'staff_id' => $staffProfile->id,
            'client_id' => $client->id,
            'starts_at' => now()->addDay(),
        ]);

        (new SendEventNotification($event, 'event_reminder'))->handle();

        Mail::assertSent(\App\Mail\EventNotification::class);
    });
});

describe('Job Queue Configuration', function () {
    it('uses notifications queue for all email jobs', function () {
        $registration = ClassRegistration::factory()->create();
        $event = Event::factory()->create();

        SendBookingConfirmation::dispatch($registration);
        SendBookingCancellation::dispatch($registration);
        SendWaitlistPromotion::dispatch($registration);
        SendClassReminder::dispatch($registration);
        SendEventNotification::dispatch($event, 'event_confirmation');

        Queue::assertPushedOn('notifications', SendBookingConfirmation::class);
        Queue::assertPushedOn('notifications', SendBookingCancellation::class);
        Queue::assertPushedOn('notifications', SendWaitlistPromotion::class);
        Queue::assertPushedOn('notifications', SendClassReminder::class);
        Queue::assertPushedOn('notifications', SendEventNotification::class);
    });

    it('limits retry attempts to 3', function () {
        $registration = ClassRegistration::factory()->create();
        $event = Event::factory()->create();

        $jobs = [
            new SendBookingConfirmation($registration),
            new SendBookingCancellation($registration),
            new SendWaitlistPromotion($registration),
            new SendClassReminder($registration),
            new SendEventNotification($event, 'event_confirmation'),
        ];

        foreach ($jobs as $job) {
            expect($job->tries)->toBe(3);
        }
    });
});

describe('Job Failure Handling', function () {
    it('creates notification record on job execution', function () {
        $registration = ClassRegistration::factory()->create();

        (new SendBookingConfirmation($registration))->handle();

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $registration->client->user_id,
            'template_key' => 'booking_confirmation',
            'channel' => 'email',
            'status' => 'sent',
        ]);
    });
});
