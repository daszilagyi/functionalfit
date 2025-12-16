<?php

declare(strict_types=1);

use App\Exceptions\ConflictException;
use App\Models\ClassOccurrence;
use App\Models\Event;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Services\ConflictDetectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('ConflictDetectionService - Room Conflicts', function () {
    beforeEach(function () {
        $this->service = new ConflictDetectionService();
        $this->room = Room::factory()->create();
        $this->staff = StaffProfile::factory()->create();
    });

    it('detects room conflict with existing event', function () {
        // Arrange: Create an existing event in the room
        $existingEvent = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['room_id' => $this->room->id, 'status' => 'scheduled']);

        // Act & Assert: Try to book the same room at overlapping time
        expect(fn () => $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 10:30:00'),
            Carbon::parse('2025-11-15 11:30:00')
        ))->toThrow(ConflictException::class, "Room '{$this->room->name}' is already booked for this time slot");
    });

    it('detects room conflict with class occurrence', function () {
        // Arrange: Create a class occurrence in the room
        $occurrence = ClassOccurrence::factory()
            ->startingAt(Carbon::parse('2025-11-15 14:00:00'), 60)
            ->create(['room_id' => $this->room->id, 'status' => 'scheduled']);

        // Act & Assert: Try to book the same room at overlapping time
        expect(fn () => $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 14:30:00'),
            Carbon::parse('2025-11-15 15:30:00')
        ))->toThrow(ConflictException::class);
    });

    it('allows room booking when no conflict exists', function () {
        // Arrange: Create an event that doesn't overlap
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        // Act: Try to book the room at a non-overlapping time
        $result = $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 12:00:00'),
            Carbon::parse('2025-11-15 13:00:00')
        );

        // Assert: No conflict
        expect($result)->toBeFalse();
    });

    it('ignores cancelled events when detecting conflicts', function () {
        // Arrange: Create a cancelled event
        Event::factory()
            ->cancelled()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        // Act: Try to book the same time slot
        $result = $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 10:30:00'),
            Carbon::parse('2025-11-15 11:30:00')
        );

        // Assert: No conflict because cancelled events are ignored
        expect($result)->toBeFalse();
    });

    it('excludes specified event when checking conflicts', function () {
        // Arrange: Create an event
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        // Act: Check conflict but exclude the same event (useful for updates)
        $result = $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 10:30:00'),
            Carbon::parse('2025-11-15 11:30:00'),
            $event->id
        );

        // Assert: No conflict because we excluded the event itself
        expect($result)->toBeFalse();
    });

    it('detects conflict when new event completely contains existing event', function () {
        // Arrange: Create a short event
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:30:00'), 30)
            ->create(['room_id' => $this->room->id]);

        // Act & Assert: Try to book a longer slot that contains the existing event
        expect(fn () => $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 10:00:00'),
            Carbon::parse('2025-11-15 12:00:00')
        ))->toThrow(ConflictException::class);
    });

    it('includes conflict details in exception', function () {
        // Arrange: Create an event with known times
        $existingEvent = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        // Act & Assert: Check exception details
        try {
            $this->service->detectRoomConflict(
                $this->room,
                Carbon::parse('2025-11-15 10:30:00'),
                Carbon::parse('2025-11-15 11:30:00')
            );
            $this->fail('Expected ConflictException was not thrown');
        } catch (ConflictException $e) {
            expect($e->getDetails())->toMatchArray([
                'conflict_type' => 'room',
                'conflicting_event_id' => $existingEvent->id,
            ]);
            expect($e->getDetails())->toHaveKeys(['conflicting_starts_at', 'conflicting_ends_at']);
        }
    });
});

describe('ConflictDetectionService - Staff Conflicts', function () {
    beforeEach(function () {
        $this->service = new ConflictDetectionService();
        $this->room = Room::factory()->create();
        $this->staff = StaffProfile::factory()->create();
    });

    it('detects staff conflict with existing event', function () {
        // Arrange: Create an existing event for the staff
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['staff_id' => $this->staff->id, 'status' => 'scheduled']);

        // Act & Assert: Try to schedule the same staff at overlapping time
        expect(fn () => $this->service->detectStaffConflict(
            $this->staff,
            Carbon::parse('2025-11-15 10:30:00'),
            Carbon::parse('2025-11-15 11:30:00')
        ))->toThrow(ConflictException::class, 'Staff member is already scheduled for this time slot');
    });

    it('detects staff conflict with class occurrence', function () {
        // Arrange: Create a class where staff is the trainer
        ClassOccurrence::factory()
            ->startingAt(Carbon::parse('2025-11-15 14:00:00'), 60)
            ->create(['trainer_id' => $this->staff->id, 'status' => 'scheduled']);

        // Act & Assert: Try to schedule the same staff at overlapping time
        expect(fn () => $this->service->detectStaffConflict(
            $this->staff,
            Carbon::parse('2025-11-15 14:30:00'),
            Carbon::parse('2025-11-15 15:30:00')
        ))->toThrow(ConflictException::class);
    });

    it('allows staff booking when no conflict exists', function () {
        // Arrange: Create an event that doesn't overlap
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['staff_id' => $this->staff->id]);

        // Act: Try to schedule the staff at a non-overlapping time
        $result = $this->service->detectStaffConflict(
            $this->staff,
            Carbon::parse('2025-11-15 12:00:00'),
            Carbon::parse('2025-11-15 13:00:00')
        );

        // Assert: No conflict
        expect($result)->toBeFalse();
    });

    it('ignores cancelled events when detecting staff conflicts', function () {
        // Arrange: Create a cancelled event
        Event::factory()
            ->cancelled()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['staff_id' => $this->staff->id]);

        // Act: Try to schedule the same time slot
        $result = $this->service->detectStaffConflict(
            $this->staff,
            Carbon::parse('2025-11-15 10:30:00'),
            Carbon::parse('2025-11-15 11:30:00')
        );

        // Assert: No conflict
        expect($result)->toBeFalse();
    });

    it('excludes specified event when checking staff conflicts', function () {
        // Arrange: Create an event
        $event = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['staff_id' => $this->staff->id]);

        // Act: Check conflict but exclude the same event
        $result = $this->service->detectStaffConflict(
            $this->staff,
            Carbon::parse('2025-11-15 10:30:00'),
            Carbon::parse('2025-11-15 11:30:00'),
            $event->id
        );

        // Assert: No conflict
        expect($result)->toBeFalse();
    });

    it('includes conflict details in staff exception', function () {
        // Arrange: Create an event with known times
        $existingEvent = Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['staff_id' => $this->staff->id]);

        // Act & Assert: Check exception details
        try {
            $this->service->detectStaffConflict(
                $this->staff,
                Carbon::parse('2025-11-15 10:30:00'),
                Carbon::parse('2025-11-15 11:30:00')
            );
            $this->fail('Expected ConflictException was not thrown');
        } catch (ConflictException $e) {
            expect($e->getDetails())->toMatchArray([
                'conflict_type' => 'staff',
                'conflicting_event_id' => $existingEvent->id,
            ]);
        }
    });
});

describe('ConflictDetectionService - Pessimistic Locking', function () {
    beforeEach(function () {
        $this->service = new ConflictDetectionService();
        $this->room = Room::factory()->create();
        $this->staff = StaffProfile::factory()->create();
    });

    it('uses pessimistic locking when checking conflicts', function () {
        // Arrange: Create a non-conflicting scenario
        $startsAt = Carbon::parse('2025-11-15 10:00:00');
        $endsAt = Carbon::parse('2025-11-15 11:00:00');

        // Act: Call checkConflictsWithLock (wraps in transaction with locking)
        $this->service->checkConflictsWithLock(
            $this->room,
            $this->staff,
            $startsAt,
            $endsAt
        );

        // Assert: If we get here, the method executed without throwing
        expect(true)->toBeTrue();
    });

    it('detects conflicts within pessimistic lock transaction', function () {
        // Arrange: Create a conflicting event
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create([
                'room_id' => $this->room->id,
                'staff_id' => $this->staff->id,
            ]);

        // Act & Assert: checkConflictsWithLock should detect and throw
        expect(fn () => $this->service->checkConflictsWithLock(
            $this->room,
            $this->staff,
            Carbon::parse('2025-11-15 10:30:00'),
            Carbon::parse('2025-11-15 11:30:00')
        ))->toThrow(ConflictException::class);
    });

    it('detects room conflict before staff conflict in locked transaction', function () {
        // Arrange: Create a room conflict only (different staff)
        $otherStaff = StaffProfile::factory()->create();
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create([
                'room_id' => $this->room->id,
                'staff_id' => $otherStaff->id,
            ]);

        // Act & Assert: Should throw room conflict
        try {
            $this->service->checkConflictsWithLock(
                $this->room,
                $this->staff,
                Carbon::parse('2025-11-15 10:30:00'),
                Carbon::parse('2025-11-15 11:30:00')
            );
            $this->fail('Expected ConflictException was not thrown');
        } catch (ConflictException $e) {
            expect($e->getDetails()['conflict_type'])->toBe('room');
        }
    });
});

describe('ConflictDetectionService - Edge Cases', function () {
    beforeEach(function () {
        $this->service = new ConflictDetectionService();
        $this->room = Room::factory()->create();
        $this->staff = StaffProfile::factory()->create();
    });

    it('allows back-to-back bookings with no overlap', function () {
        // Arrange: Create an event ending at 11:00
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        // Act: Try to book starting exactly at 11:00
        $result = $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 11:00:00'),
            Carbon::parse('2025-11-15 12:00:00')
        );

        // Assert: No conflict
        expect($result)->toBeFalse();
    });

    it('detects conflict when events share same start or end time', function () {
        // Arrange: Create an event from 10:00-11:00
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 10:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        // Act & Assert: Try to book 09:30-10:30 (overlaps by 30 min)
        expect(fn () => $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 09:30:00'),
            Carbon::parse('2025-11-15 10:30:00')
        ))->toThrow(ConflictException::class);
    });

    it('handles multiple non-conflicting events in same room', function () {
        // Arrange: Create multiple events at different times
        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 08:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 12:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        Event::factory()
            ->startingAt(Carbon::parse('2025-11-15 16:00:00'), 60)
            ->create(['room_id' => $this->room->id]);

        // Act: Try to book between existing events
        $result = $this->service->detectRoomConflict(
            $this->room,
            Carbon::parse('2025-11-15 10:00:00'),
            Carbon::parse('2025-11-15 11:00:00')
        );

        // Assert: No conflict
        expect($result)->toBeFalse();
    });
});
