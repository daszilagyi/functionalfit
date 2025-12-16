<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\StaffProfile;
use App\Models\User;
use App\Policies\EventPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('EventPolicy - View Permissions', function () {
    beforeEach(function () {
        $this->policy = new EventPolicy();
    });

    it('allows active users to view any events', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $client = User::factory()->client()->create();

        // Act & Assert
        expect($this->policy->viewAny($admin))->toBeTrue();
        expect($this->policy->viewAny($staff))->toBeTrue();
        expect($this->policy->viewAny($client))->toBeTrue();
    });

    it('denies inactive users from viewing events', function () {
        // Arrange
        $inactiveUser = User::factory()->inactive()->create();

        // Act & Assert
        expect($this->policy->viewAny($inactiveUser))->toBeFalse();
    });

    it('allows admin to view any specific event', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->view($admin, $event))->toBeTrue();
    });

    it('allows staff to view any specific event', function () {
        // Arrange
        $staff = User::factory()->staff()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->view($staff, $event))->toBeTrue();
    });

    it('allows client to view their own event', function () {
        // Arrange
        $user = User::factory()->client()->create();
        $client = $user->client()->create([
            'user_id' => $user->id,
            'full_name' => $user->name,
        ]);
        $event = Event::factory()->create(['client_id' => $client->id]);

        // Act & Assert
        expect($this->policy->view($user, $event))->toBeTrue();
    });

    it('denies client from viewing other clients events', function () {
        // Arrange
        $user = User::factory()->client()->create();
        $client = $user->client()->create([
            'user_id' => $user->id,
            'full_name' => $user->name,
        ]);
        $otherEvent = Event::factory()->create(); // Different client

        // Act & Assert
        expect($this->policy->view($user, $otherEvent))->toBeFalse();
    });
});

describe('EventPolicy - Create Permissions', function () {
    beforeEach(function () {
        $this->policy = new EventPolicy();
    });

    it('allows admin to create events', function () {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act & Assert
        expect($this->policy->create($admin))->toBeTrue();
    });

    it('allows active staff to create events', function () {
        // Arrange
        $staff = User::factory()->staff()->create();

        // Act & Assert
        expect($this->policy->create($staff))->toBeTrue();
    });

    it('denies inactive staff from creating events', function () {
        // Arrange
        $inactiveStaff = User::factory()->staff()->inactive()->create();

        // Act & Assert
        expect($this->policy->create($inactiveStaff))->toBeFalse();
    });

    it('denies client from creating events', function () {
        // Arrange
        $client = User::factory()->client()->create();

        // Act & Assert
        expect($this->policy->create($client))->toBeFalse();
    });
});

describe('EventPolicy - Update Permissions', function () {
    beforeEach(function () {
        $this->policy = new EventPolicy();
    });

    it('allows admin to update any event', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->update($admin, $event))->toBeTrue();
    });

    it('allows staff to update their own future events', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $futureEvent = Event::factory()
            ->startingAt(Carbon::now()->addDays(1))
            ->create(['staff_id' => $staffProfile->id]);

        // Act & Assert
        expect($this->policy->update($user, $futureEvent))->toBeTrue();
    });

    it('denies staff from updating past events', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $pastEvent = Event::factory()
            ->inPast()
            ->create(['staff_id' => $staffProfile->id]);

        // Act & Assert
        expect($this->policy->update($user, $pastEvent))->toBeFalse();
    });

    it('denies staff from updating other staff events', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        StaffProfile::factory()->create(['user_id' => $user->id]);
        $otherStaffEvent = Event::factory()->create(); // Different staff

        // Act & Assert
        expect($this->policy->update($user, $otherStaffEvent))->toBeFalse();
    });

    it('denies client from updating events', function () {
        // Arrange
        $client = User::factory()->client()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->update($client, $event))->toBeFalse();
    });
});

describe('EventPolicy - Same-Day Move Rule', function () {
    beforeEach(function () {
        $this->policy = new EventPolicy();
    });

    it('allows admin to move events across days', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'))
            ->create();
        $newStartsAt = '2025-11-20 14:00:00'; // Different day

        // Act & Assert
        expect($this->policy->sameDayMove($admin, $event, $newStartsAt))->toBeTrue();
    });

    it('allows staff to move events within same day', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'))
            ->create(['staff_id' => $staffProfile->id]);
        $newStartsAt = '2025-11-15 14:00:00'; // Same day, different time

        // Act & Assert
        expect($this->policy->sameDayMove($user, $event, $newStartsAt))->toBeTrue();
    });

    it('denies staff from moving events to different day', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'))
            ->create(['staff_id' => $staffProfile->id]);
        $newStartsAt = '2025-11-16 10:00:00'; // Next day

        // Act & Assert
        expect($this->policy->sameDayMove($user, $event, $newStartsAt))->toBeFalse();
    });

    it('handles timezone correctly for same-day check', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 23:30:00', 'Europe/Budapest'))
            ->create(['staff_id' => $staffProfile->id]);
        $newStartsAt = '2025-11-15 23:59:00'; // Still same day, just before midnight

        // Act & Assert
        expect($this->policy->sameDayMove($user, $event, $newStartsAt))->toBeTrue();
    });

    it('denies moving from late night to next day', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 23:30:00'))
            ->create(['staff_id' => $staffProfile->id]);
        $newStartsAt = '2025-11-16 00:30:00'; // Next day, after midnight

        // Act & Assert
        expect($this->policy->sameDayMove($user, $event, $newStartsAt))->toBeFalse();
    });

    it('denies staff from moving other staff events', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        StaffProfile::factory()->create(['user_id' => $user->id]);
        $otherStaffEvent = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'))
            ->create(); // Different staff
        $newStartsAt = '2025-11-15 14:00:00';

        // Act & Assert
        expect($this->policy->sameDayMove($user, $otherStaffEvent, $newStartsAt))->toBeFalse();
    });
});

describe('EventPolicy - Force Update (Admin Override)', function () {
    beforeEach(function () {
        $this->policy = new EventPolicy();
    });

    it('allows admin to force update events', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->forceUpdate($admin, $event))->toBeTrue();
    });

    it('denies staff from force updating events', function () {
        // Arrange
        $staff = User::factory()->staff()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->forceUpdate($staff, $event))->toBeFalse();
    });

    it('denies client from force updating events', function () {
        // Arrange
        $client = User::factory()->client()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->forceUpdate($client, $event))->toBeFalse();
    });
});

describe('EventPolicy - Delete Permissions', function () {
    beforeEach(function () {
        $this->policy = new EventPolicy();
    });

    it('allows admin to delete any event', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->delete($admin, $event))->toBeTrue();
    });

    it('allows staff to delete their own future events', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $futureEvent = Event::factory()
            ->startingAt(Carbon::now()->addDays(1))
            ->create(['staff_id' => $staffProfile->id]);

        // Act & Assert
        expect($this->policy->delete($user, $futureEvent))->toBeTrue();
    });

    it('denies staff from deleting past events', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $pastEvent = Event::factory()
            ->inPast()
            ->create(['staff_id' => $staffProfile->id]);

        // Act & Assert
        expect($this->policy->delete($user, $pastEvent))->toBeFalse();
    });

    it('denies staff from deleting other staff events', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        StaffProfile::factory()->create(['user_id' => $user->id]);
        $otherStaffEvent = Event::factory()->create(); // Different staff

        // Act & Assert
        expect($this->policy->delete($user, $otherStaffEvent))->toBeFalse();
    });

    it('denies client from deleting events', function () {
        // Arrange
        $client = User::factory()->client()->create();
        $event = Event::factory()->create();

        // Act & Assert
        expect($this->policy->delete($client, $event))->toBeFalse();
    });
});

describe('EventPolicy - Edge Cases', function () {
    beforeEach(function () {
        $this->policy = new EventPolicy();
    });

    it('handles events starting exactly at midnight', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 00:00:00'))
            ->create(['staff_id' => $staffProfile->id]);
        $newStartsAt = '2025-11-15 23:59:59'; // End of same day

        // Act & Assert
        expect($this->policy->sameDayMove($user, $event, $newStartsAt))->toBeTrue();
    });

    it('admin can override same-day restriction for cross-day moves', function () {
        // Arrange
        $admin = User::factory()->admin()->create();
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'))
            ->create();
        $newStartsAt = '2025-11-20 10:00:00'; // 5 days later

        // Act & Assert
        expect($this->policy->sameDayMove($admin, $event, $newStartsAt))->toBeTrue();
        expect($this->policy->forceUpdate($admin, $event))->toBeTrue();
    });

    it('staff cannot update events starting in the past even if owns them', function () {
        // Arrange
        $user = User::factory()->staff()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $user->id]);
        $pastEvent = Event::factory()
            ->startingAt(Carbon::now()->subHours(1))
            ->create(['staff_id' => $staffProfile->id]);

        // Act & Assert
        expect($this->policy->update($user, $pastEvent))->toBeFalse();
    });
});
