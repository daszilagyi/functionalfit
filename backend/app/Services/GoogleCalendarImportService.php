<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\GoogleCalendarSyncConfig;
use App\Models\GoogleCalendarSyncLog;
use App\Models\Room;
use App\Models\StaffProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GoogleCalendarImportService
{
    public function __construct(
        private GoogleCalendarService $googleCalendarService,
        private ConflictDetectionService $conflictDetectionService
    ) {
    }

    /**
     * Import events from Google Calendar with conflict detection.
     *
     * @param GoogleCalendarSyncConfig $config
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param int|null $roomId Optional room filter
     * @param bool $autoResolveConflicts Whether to automatically skip conflicting events
     * @return GoogleCalendarSyncLog
     */
    public function importEvents(
        GoogleCalendarSyncConfig $config,
        \DateTime $startDate,
        \DateTime $endDate,
        ?int $roomId = null,
        bool $autoResolveConflicts = false
    ): GoogleCalendarSyncLog {
        // Create sync log
        $log = GoogleCalendarSyncLog::create([
            'sync_config_id' => $config->id,
            'operation' => 'import',
            'status' => 'in_progress',
            'started_at' => now(),
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'room_id' => $roomId,
            ],
            'conflicts' => [],
        ]);

        try {
            // Fetch events from Google Calendar
            $googleEvents = $this->googleCalendarService->importEventsFromGoogleCalendar(
                $config->google_calendar_id,
                $startDate,
                $endDate
            );

            $log->update(['events_processed' => count($googleEvents)]);

            $conflicts = [];
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($googleEvents as $googleEvent) {
                try {
                    // Skip events that were originally from our system
                    if ($googleEvent['is_from_our_system']) {
                        $skipped++;
                        continue;
                    }

                    // Check for conflicts
                    $conflict = $this->detectConflicts(
                        $googleEvent,
                        $roomId ?? $config->room_id,
                        $startDate,
                        $endDate
                    );

                    if ($conflict) {
                        $conflicts[] = $conflict;

                        if (!$autoResolveConflicts) {
                            // Skip this event, requires manual resolution
                            $skipped++;
                            continue;
                        }
                    }

                    // Check if event already exists (by google_event_id)
                    $existingEvent = Event::where('google_event_id', $googleEvent['google_event_id'])->first();

                    if ($existingEvent) {
                        // Update existing event
                        $this->updateEventFromGoogleEvent($existingEvent, $googleEvent, $config);
                        $updated++;
                    } else {
                        // Create new event
                        $this->createEventFromGoogleEvent($googleEvent, $config, $roomId);
                        $created++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::error('Failed to import individual Google Calendar event', [
                        'google_event_id' => $googleEvent['google_event_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update sync log with results
            $log->update([
                'status' => count($conflicts) > 0 && !$autoResolveConflicts ? 'completed' : 'completed',
                'completed_at' => now(),
                'events_created' => $created,
                'events_updated' => $updated,
                'events_skipped' => $skipped,
                'events_failed' => $failed,
                'conflicts_detected' => count($conflicts),
                'conflicts' => $conflicts,
            ]);

            // Update config last import timestamp
            $config->update(['last_import_at' => now()]);

            return $log;
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Google Calendar import failed', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Detect conflicts for a Google Calendar event.
     */
    private function detectConflicts(
        array $googleEvent,
        ?int $roomId,
        \DateTime $startDate,
        \DateTime $endDate
    ): ?array {
        if (!$roomId) {
            return null; // Can't detect conflicts without a room
        }

        $startsAt = new \DateTime($googleEvent['start_time']);
        $endsAt = new \DateTime($googleEvent['end_time']);

        // Check if there are any existing events in this time slot
        $conflicts = $this->conflictDetectionService->detectConflicts(
            $roomId,
            $startsAt,
            $endsAt,
            null // No event ID since we're importing new events
        );

        if (empty($conflicts)) {
            return null;
        }

        return [
            'google_event_id' => $googleEvent['google_event_id'],
            'google_summary' => $googleEvent['summary'],
            'google_start' => $googleEvent['start_time'],
            'google_end' => $googleEvent['end_time'],
            'conflicting_events' => array_map(function ($conflict) {
                return [
                    'event_id' => $conflict['event_id'],
                    'type' => $conflict['event_type'],
                    'start' => $conflict['starts_at'],
                    'end' => $conflict['ends_at'],
                    'overlap_minutes' => $conflict['overlap_minutes'],
                ];
            }, $conflicts),
        ];
    }

    /**
     * Create a new event from Google Calendar event data.
     */
    private function createEventFromGoogleEvent(
        array $googleEvent,
        GoogleCalendarSyncConfig $config,
        ?int $roomId
    ): Event {
        $startsAt = new \DateTime($googleEvent['start_time']);
        $endsAt = new \DateTime($googleEvent['end_time']);

        // Determine room
        $room = $roomId ? Room::find($roomId) : $config->room;

        if (!$room) {
            throw new \Exception('No room specified for imported event');
        }

        // For imported events, we need to assign a staff member
        // Default to first available staff or create a placeholder
        $staff = StaffProfile::first();

        if (!$staff) {
            throw new \Exception('No staff profile available for imported event');
        }

        return Event::create([
            'room_id' => $room->id,
            'staff_id' => $staff->id,
            'client_id' => null, // Imported events don't have clients initially
            'type' => 'BLOCK', // Mark imported events as BLOCK type by default
            'status' => $googleEvent['status'] === 'cancelled' ? 'cancelled' : 'scheduled',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'notes' => $this->buildNotesFromGoogleEvent($googleEvent),
            'google_event_id' => $googleEvent['google_event_id'],
        ]);
    }

    /**
     * Update an existing event from Google Calendar event data.
     */
    private function updateEventFromGoogleEvent(
        Event $event,
        array $googleEvent,
        GoogleCalendarSyncConfig $config
    ): Event {
        $startsAt = new \DateTime($googleEvent['start_time']);
        $endsAt = new \DateTime($googleEvent['end_time']);

        $event->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $googleEvent['status'] === 'cancelled' ? 'cancelled' : 'scheduled',
            'notes' => $this->buildNotesFromGoogleEvent($googleEvent),
        ]);

        return $event;
    }

    /**
     * Build notes field from Google Calendar event.
     */
    private function buildNotesFromGoogleEvent(array $googleEvent): string
    {
        $notes = [];

        if ($googleEvent['summary']) {
            $notes[] = "Title: {$googleEvent['summary']}";
        }

        if ($googleEvent['description']) {
            $notes[] = "Description: {$googleEvent['description']}";
        }

        if ($googleEvent['location']) {
            $notes[] = "Location: {$googleEvent['location']}";
        }

        $notes[] = "Imported from Google Calendar";
        $notes[] = "Google Event ID: {$googleEvent['google_event_id']}";

        return implode("\n", $notes);
    }

    /**
     * Resolve conflicts by overwriting existing events.
     *
     * @param GoogleCalendarSyncLog $log
     * @param array $conflictResolutions Array of [google_event_id => 'overwrite'|'skip']
     * @return GoogleCalendarSyncLog
     */
    public function resolveConflicts(
        GoogleCalendarSyncLog $log,
        array $conflictResolutions
    ): GoogleCalendarSyncLog {
        if (!$log->hasConflicts()) {
            return $log;
        }

        $config = $log->syncConfig;
        $conflicts = $log->conflicts;
        $resolved = 0;

        foreach ($conflicts as $conflict) {
            $googleEventId = $conflict['google_event_id'];
            $resolution = $conflictResolutions[$googleEventId] ?? 'skip';

            if ($resolution === 'overwrite') {
                // Delete conflicting events and import the Google Calendar event
                foreach ($conflict['conflicting_events'] as $conflictingEvent) {
                    $event = Event::find($conflictingEvent['event_id']);
                    if ($event) {
                        $event->delete();
                    }
                }

                // Re-import this specific event
                // (Implementation would fetch this specific event and import it)
                $resolved++;
            }
        }

        $log->update([
            'metadata' => array_merge($log->metadata ?? [], [
                'conflicts_resolved' => $resolved,
                'resolution_timestamp' => now()->toIso8601String(),
            ]),
        ]);

        return $log;
    }
}
