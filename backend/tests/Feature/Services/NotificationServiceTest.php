<?php

declare(strict_types=1);

use App\Jobs\SendClassDeleted;
use App\Jobs\SendClassModified;
use App\Jobs\SendPasswordReset;
use App\Jobs\SendRegistrationConfirmation;
use App\Jobs\SendUserDeleted;
use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\ClassTemplate;
use App\Models\Client;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->notificationService = app(NotificationService::class);
});

describe('sendRegistrationConfirmation', function () {
    test('dispatches SendRegistrationConfirmation job to notifications queue', function () {
        $user = User::factory()->create();

        $this->notificationService->sendRegistrationConfirmation($user);

        Queue::assertPushedOn('notifications', SendRegistrationConfirmation::class, function ($job) use ($user) {
            return $job->user->id === $user->id;
        });
    });

    test('does not throw exception on dispatch failure', function () {
        Queue::shouldReceive('push')
            ->andThrow(new \Exception('Queue connection failed'));

        $user = User::factory()->create();

        // Should not throw - method catches exceptions internally
        expect(fn () => $this->notificationService->sendRegistrationConfirmation($user))
            ->not->toThrow(\Exception::class);
    });
});

describe('sendPasswordReset', function () {
    test('dispatches SendPasswordReset job with user and token', function () {
        $user = User::factory()->create();
        $token = 'reset-token-123';

        $this->notificationService->sendPasswordReset($user, $token);

        Queue::assertPushedOn('notifications', SendPasswordReset::class, function ($job) use ($user, $token) {
            return $job->user->id === $user->id && $job->token === $token;
        });
    });
});

describe('sendUserDeleted', function () {
    test('dispatches SendUserDeleted job with captured user data', function () {
        $user = User::factory()->create([
            'name' => 'Deleted User',
            'email' => 'deleted@example.com',
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin User',
        ]);

        $this->notificationService->sendUserDeleted($user, $admin);

        Queue::assertPushedOn('notifications', SendUserDeleted::class, function ($job) use ($user, $admin) {
            return $job->userData['id'] === $user->id
                && $job->userData['name'] === 'Deleted User'
                && $job->userData['email'] === 'deleted@example.com'
                && $job->deletedByName === 'Admin User';
        });
    });
});

describe('sendClassModified', function () {
    test('dispatches SendClassModified job for each booked participant', function () {
        $occurrence = createOccurrenceWithRegistrations(3, 'booked');
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
        $changes = ['old_starts_at' => '10:00', 'new_starts_at' => '14:00'];

        $this->notificationService->sendClassModified($occurrence, $admin, $changes);

        Queue::assertPushedOn('notifications', SendClassModified::class);
        Queue::assertPushed(SendClassModified::class, 3);
    });

    test('dispatches SendClassModified job for each waitlisted participant', function () {
        $occurrence = createOccurrenceWithRegistrations(2, 'waitlist');
        $admin = User::factory()->create(['role' => 'admin']);
        $changes = [];

        $this->notificationService->sendClassModified($occurrence, $admin, $changes);

        Queue::assertPushed(SendClassModified::class, 2);
    });

    test('dispatches for both booked and waitlisted participants', function () {
        $occurrence = createOccurrence();

        // Create 2 booked and 1 waitlist registration
        createRegistrationForOccurrence($occurrence, 'booked');
        createRegistrationForOccurrence($occurrence, 'booked');
        createRegistrationForOccurrence($occurrence, 'waitlist');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->notificationService->sendClassModified($occurrence, $admin, []);

        Queue::assertPushed(SendClassModified::class, 3);
    });

    test('does not dispatch if no participants', function () {
        $occurrence = createOccurrence(); // No registrations
        $admin = User::factory()->create(['role' => 'admin']);

        $this->notificationService->sendClassModified($occurrence, $admin, []);

        Queue::assertNotPushed(SendClassModified::class);
    });

    test('ignores cancelled registrations', function () {
        $occurrence = createOccurrenceWithRegistrations(2, 'booked');

        // Add cancelled registration
        createRegistrationForOccurrence($occurrence, 'cancelled');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->notificationService->sendClassModified($occurrence, $admin, []);

        Queue::assertPushed(SendClassModified::class, 2);
    });
});

describe('sendClassDeleted', function () {
    test('dispatches SendClassDeleted job for each participant', function () {
        $occurrence = createOccurrenceWithRegistrations(3, 'booked');
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);

        $this->notificationService->sendClassDeleted($occurrence, $admin);

        Queue::assertPushedOn('notifications', SendClassDeleted::class);
        Queue::assertPushed(SendClassDeleted::class, 3);
    });

    test('captures class data correctly before deletion', function () {
        $room = Room::factory()->create(['name' => 'Test Room']);
        $trainerUser = User::factory()->create(['name' => 'Test Trainer', 'role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $trainerUser->id]);
        $classTemplate = ClassTemplate::factory()->create(['title' => 'Test Class']);

        $occurrence = ClassOccurrence::factory()->create([
            'template_id' => $classTemplate->id,
            'trainer_id' => $staffProfile->id,
            'room_id' => $room->id,
            'starts_at' => '2025-01-15 10:00:00',
            'ends_at' => '2025-01-15 11:00:00',
        ]);

        createRegistrationForOccurrence($occurrence, 'booked');

        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);

        $this->notificationService->sendClassDeleted($occurrence, $admin);

        Queue::assertPushed(SendClassDeleted::class, function ($job) {
            return $job->classData['title'] === 'Test Class'
                && $job->classData['room'] === 'Test Room'
                && $job->classData['trainer'] === 'Test Trainer'
                && $job->deletedByName === 'Admin User';
        });
    });

    test('does not dispatch when notifyParticipants is false', function () {
        $occurrence = createOccurrenceWithRegistrations(3, 'booked');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->notificationService->sendClassDeleted($occurrence, $admin, false);

        Queue::assertNotPushed(SendClassDeleted::class);
    });

    test('dispatches for both booked and waitlisted participants', function () {
        $occurrence = createOccurrence();

        createRegistrationForOccurrence($occurrence, 'booked');
        createRegistrationForOccurrence($occurrence, 'booked');
        createRegistrationForOccurrence($occurrence, 'waitlist');

        $admin = User::factory()->create(['role' => 'admin']);

        $this->notificationService->sendClassDeleted($occurrence, $admin);

        Queue::assertPushed(SendClassDeleted::class, 3);
    });

    test('does not dispatch if no participants', function () {
        $occurrence = createOccurrence();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->notificationService->sendClassDeleted($occurrence, $admin);

        Queue::assertNotPushed(SendClassDeleted::class);
    });
});

// Helper functions

function createOccurrence(): ClassOccurrence
{
    $trainerUser = User::factory()->create(['role' => 'staff']);
    $staffProfile = StaffProfile::factory()->create(['user_id' => $trainerUser->id]);
    $room = Room::factory()->create();
    $classTemplate = ClassTemplate::factory()->create();

    return ClassOccurrence::factory()->create([
        'template_id' => $classTemplate->id,
        'trainer_id' => $staffProfile->id,
        'room_id' => $room->id,
    ]);
}

function createOccurrenceWithRegistrations(int $count, string $status): ClassOccurrence
{
    $occurrence = createOccurrence();

    for ($i = 0; $i < $count; $i++) {
        createRegistrationForOccurrence($occurrence, $status);
    }

    return $occurrence;
}

function createRegistrationForOccurrence(ClassOccurrence $occurrence, string $status): ClassRegistration
{
    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);

    return ClassRegistration::factory()->create([
        'occurrence_id' => $occurrence->id,
        'client_id' => $client->id,
        'status' => $status,
    ]);
}
