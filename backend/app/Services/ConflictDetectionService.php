<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ConflictException;
use App\Models\ClassOccurrence;
use App\Models\Event;
use App\Models\Room;
use App\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConflictDetectionService
{
    /**
     * Detect room booking conflicts for events and class occurrences.
     *
     * NOTE: Conflict detection is DISABLED to allow overlapping events
     * (e.g., clients joining at different times like 08:00, 08:30, 08:45)
     *
     * @throws ConflictException
     */
    public function detectRoomConflict(
        Room $room,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeEventId = null,
        ?int $excludeOccurrenceId = null
    ): bool {
        // Conflict detection disabled - allow overlapping events
        return false;

        // Check events table
        // Overlap logic: new_start < existing_end AND new_end > existing_start
        $eventConflict = Event::where('room_id', $room->id)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeEventId, fn($q) => $q->where('id', '!=', $excludeEventId))
            ->first();

        if ($eventConflict) {
            throw new ConflictException(
                "Room '{$room->name}' is already booked for this time slot",
                [
                    'conflict_type' => 'room',
                    'conflicting_event_id' => $eventConflict->id,
                    'conflicting_starts_at' => $eventConflict->starts_at->toIso8601String(),
                    'conflicting_ends_at' => $eventConflict->ends_at->toIso8601String(),
                ]
            );
        }

        // Check class_occurrences table
        // Overlap logic: new_start < existing_end AND new_end > existing_start
        $occurrenceConflict = ClassOccurrence::where('room_id', $room->id)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeOccurrenceId, fn($q) => $q->where('id', '!=', $excludeOccurrenceId))
            ->first();

        if ($occurrenceConflict) {
            throw new ConflictException(
                "Room '{$room->name}' is already booked for this time slot",
                [
                    'conflict_type' => 'room',
                    'conflicting_occurrence_id' => $occurrenceConflict->id,
                    'conflicting_starts_at' => $occurrenceConflict->starts_at->toIso8601String(),
                    'conflicting_ends_at' => $occurrenceConflict->ends_at->toIso8601String(),
                ]
            );
        }

        return false; // No conflict
    }

    /**
     * Detect staff scheduling conflicts.
     *
     * NOTE: Conflict detection is DISABLED to allow overlapping events
     * (e.g., clients joining at different times like 08:00, 08:30, 08:45)
     *
     * @throws ConflictException
     */
    public function detectStaffConflict(
        StaffProfile $staff,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeEventId = null,
        ?int $excludeOccurrenceId = null
    ): bool {
        // Conflict detection disabled - allow overlapping events
        return false;

        // Check events table
        // Overlap logic: new_start < existing_end AND new_end > existing_start
        $eventConflict = Event::where('staff_id', $staff->id)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeEventId, fn($q) => $q->where('id', '!=', $excludeEventId))
            ->first();

        if ($eventConflict) {
            throw new ConflictException(
                "Staff member is already scheduled for this time slot",
                [
                    'conflict_type' => 'staff',
                    'conflicting_event_id' => $eventConflict->id,
                    'conflicting_starts_at' => $eventConflict->starts_at->toIso8601String(),
                    'conflicting_ends_at' => $eventConflict->ends_at->toIso8601String(),
                ]
            );
        }

        // Check class_occurrences table
        // Overlap logic: new_start < existing_end AND new_end > existing_start
        $occurrenceConflict = ClassOccurrence::where('trainer_id', $staff->id)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeOccurrenceId, fn($q) => $q->where('id', '!=', $excludeOccurrenceId))
            ->first();

        if ($occurrenceConflict) {
            throw new ConflictException(
                "Staff member is already scheduled for this time slot",
                [
                    'conflict_type' => 'staff',
                    'conflicting_occurrence_id' => $occurrenceConflict->id,
                    'conflicting_starts_at' => $occurrenceConflict->starts_at->toIso8601String(),
                    'conflicting_ends_at' => $occurrenceConflict->ends_at->toIso8601String(),
                ]
            );
        }

        return false; // No conflict
    }

    /**
     * Check for conflicts without locking (for use within existing transactions).
     *
     * NOTE: Conflict detection is DISABLED to allow overlapping events
     * (e.g., clients joining at different times like 08:00, 08:30, 08:45)
     *
     * @param int $roomId
     * @param \DateTime|Carbon $startsAt
     * @param \DateTime|Carbon $endsAt
     * @param int $staffId
     * @param int|null $excludeEventId
     * @param int|null $excludeClassOccurrenceId
     * @throws ConflictException
     */
    public function checkConflicts(
        int $roomId,
        \DateTime|Carbon $startsAt,
        \DateTime|Carbon $endsAt,
        int $staffId,
        ?int $excludeEventId = null,
        ?int $excludeClassOccurrenceId = null
    ): void {
        // Conflict detection disabled - allow overlapping events
        return;

        // Resolve models
        $room = Room::findOrFail($roomId);
        $staff = StaffProfile::findOrFail($staffId);

        // Convert DateTime to Carbon if needed
        $startsAt = $startsAt instanceof Carbon ? $startsAt : Carbon::instance($startsAt);
        $endsAt = $endsAt instanceof Carbon ? $endsAt : Carbon::instance($endsAt);

        // Check both room and staff conflicts
        $this->detectRoomConflict($room, $startsAt, $endsAt, $excludeEventId, $excludeClassOccurrenceId);
        $this->detectStaffConflict($staff, $startsAt, $endsAt, $excludeEventId, $excludeClassOccurrenceId);
    }

    /**
     * Check for conflicts with pessimistic locking for critical sections.
     *
     * NOTE: Conflict detection is DISABLED to allow overlapping events
     * (e.g., clients joining at different times like 08:00, 08:30, 08:45)
     */
    public function checkConflictsWithLock(
        Room $room,
        StaffProfile $staff,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeEventId = null
    ): void {
        // Conflict detection disabled - allow overlapping events
        return;

        DB::transaction(function () use ($room, $staff, $startsAt, $endsAt, $excludeEventId) {
            // Lock the room and staff records for this transaction
            $room->lockForUpdate()->find($room->id);
            $staff->lockForUpdate()->find($staff->id);

            $this->detectRoomConflict($room, $startsAt, $endsAt, $excludeEventId);
            $this->detectStaffConflict($staff, $startsAt, $endsAt, $excludeEventId);
        });
    }

    /**
     * Detect conflicts and return them as an array (for Google Calendar import).
     *
     * NOTE: Conflict detection is DISABLED to allow overlapping events
     * (e.g., clients joining at different times like 08:00, 08:30, 08:45)
     *
     * @param int $roomId
     * @param \DateTime|Carbon $startsAt
     * @param \DateTime|Carbon $endsAt
     * @param int|null $excludeEventId
     * @return array List of conflicts
     */
    public function detectConflicts(
        int $roomId,
        \DateTime|Carbon $startsAt,
        \DateTime|Carbon $endsAt,
        ?int $excludeEventId = null
    ): array {
        // Conflict detection disabled - allow overlapping events
        return [];

        // Convert DateTime to Carbon if needed
        $startsAt = $startsAt instanceof Carbon ? $startsAt : Carbon::instance($startsAt);
        $endsAt = $endsAt instanceof Carbon ? $endsAt : Carbon::instance($endsAt);

        $conflicts = [];

        // Check events table for room conflicts
        $eventConflicts = Event::where('room_id', $roomId)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeEventId, fn($q) => $q->where('id', '!=', $excludeEventId))
            ->get();

        foreach ($eventConflicts as $event) {
            $overlapStart = max($startsAt, $event->starts_at);
            $overlapEnd = min($endsAt, $event->ends_at);
            $overlapMinutes = $overlapStart->diffInMinutes($overlapEnd);

            // Only add to conflicts if there is actual overlap (> 0 minutes)
            if ($overlapMinutes > 0) {
                $conflicts[] = [
                    'event_id' => $event->id,
                    'event_type' => 'event',
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'ends_at' => $event->ends_at->toIso8601String(),
                    'overlap_minutes' => $overlapMinutes,
                ];
            }
        }

        // Check class_occurrences table for room conflicts
        $occurrenceConflicts = ClassOccurrence::where('room_id', $roomId)
            ->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->get();

        foreach ($occurrenceConflicts as $occurrence) {
            $overlapStart = max($startsAt, $occurrence->starts_at);
            $overlapEnd = min($endsAt, $occurrence->ends_at);
            $overlapMinutes = $overlapStart->diffInMinutes($overlapEnd);

            // Only add to conflicts if there is actual overlap (> 0 minutes)
            if ($overlapMinutes > 0) {
                $conflicts[] = [
                    'event_id' => $occurrence->id,
                    'event_type' => 'class_occurrence',
                    'starts_at' => $occurrence->starts_at->toIso8601String(),
                    'ends_at' => $occurrence->ends_at->toIso8601String(),
                    'overlap_minutes' => $overlapMinutes,
                ];
            }
        }

        return $conflicts;
    }
}
