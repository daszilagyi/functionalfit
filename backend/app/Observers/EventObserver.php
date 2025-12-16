<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\DeleteEventFromGoogleCalendar;
use App\Jobs\SyncEventToGoogleCalendar;
use App\Models\Event;
use App\Models\EventChange;
use App\Services\CalendarChangeLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EventObserver
{
    /**
     * CalendarChangeLogger service instance.
     */
    protected CalendarChangeLogger $calendarChangeLogger;

    /**
     * Constructor with dependency injection.
     */
    public function __construct(CalendarChangeLogger $calendarChangeLogger)
    {
        $this->calendarChangeLogger = $calendarChangeLogger;
    }
    /**
     * Handle the Event "created" event.
     */
    public function created(Event $event): void
    {
        // Log event creation to new audit trail
        $this->calendarChangeLogger->logCreated($event);

        // Keep legacy logging for backwards compatibility
        $this->logChange($event, 'created', [
            'type' => $event->type,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'staff_id' => $event->staff_id,
            'client_id' => $event->client_id,
            'room_id' => $event->room_id,
            'status' => $event->status,
        ]);

        // Only sync scheduled events
        if ($event->status !== 'scheduled') {
            Log::info('Event created with non-scheduled status, skipping Google Calendar sync', [
                'event_id' => $event->id,
                'status' => $event->status,
            ]);
            return;
        }

        Log::info('Event created, dispatching Google Calendar sync', [
            'event_id' => $event->id,
            'type' => $event->type,
            'staff_id' => $event->staff_id,
        ]);

        SyncEventToGoogleCalendar::dispatch($event)
            ->onQueue('gcal-sync');
    }

    /**
     * Handle the Event "updated" event.
     */
    public function updated(Event $event): void
    {
        // Get original attributes BEFORE any changes
        $originalAttributes = $event->getOriginal();

        // Log event update to new audit trail
        $this->calendarChangeLogger->logUpdated($event, $originalAttributes);

        // Determine action type based on changes for legacy logging
        $action = 'updated';
        $changedFields = array_keys($event->getDirty());

        if ($event->isDirty('status') && $event->status === 'cancelled') {
            $action = 'cancelled';
        } elseif ($event->isDirty('starts_at') || $event->isDirty('ends_at')) {
            $action = 'moved';
        }

        // Log the change with old and new values (legacy)
        $meta = [
            'changed_fields' => $changedFields,
            'old' => [],
            'new' => [],
        ];

        foreach ($changedFields as $field) {
            $oldValue = $event->getOriginal($field);
            $newValue = $event->$field;

            // Format datetime fields
            if (in_array($field, ['starts_at', 'ends_at']) && $oldValue) {
                $oldValue = \Carbon\Carbon::parse($oldValue)->toIso8601String();
            }
            if (in_array($field, ['starts_at', 'ends_at']) && $newValue) {
                $newValue = $newValue->toIso8601String();
            }

            $meta['old'][$field] = $oldValue;
            $meta['new'][$field] = $newValue;
        }

        $this->logChange($event, $action, $meta);

        // Check if status changed to cancelled
        if ($event->isDirty('status') && $event->status === 'cancelled') {
            Log::info('Event status changed to cancelled, dispatching Google Calendar deletion', [
                'event_id' => $event->id,
                'old_status' => $event->getOriginal('status'),
                'new_status' => $event->status,
            ]);

            DeleteEventFromGoogleCalendar::dispatch($event)
                ->onQueue('gcal-sync');

            return;
        }

        // Only sync scheduled events
        if ($event->status !== 'scheduled') {
            Log::info('Event updated with non-scheduled status, skipping Google Calendar sync', [
                'event_id' => $event->id,
                'status' => $event->status,
            ]);
            return;
        }

        // Check if relevant fields changed
        $relevantFields = [
            'starts_at',
            'ends_at',
            'staff_id',
            'client_id',
            'room_id',
            'notes',
            'type',
        ];

        $hasRelevantChanges = false;
        foreach ($relevantFields as $field) {
            if ($event->isDirty($field)) {
                $hasRelevantChanges = true;
                break;
            }
        }

        if (!$hasRelevantChanges) {
            Log::debug('Event updated but no relevant fields changed, skipping Google Calendar sync', [
                'event_id' => $event->id,
            ]);
            return;
        }

        Log::info('Event updated with relevant changes, dispatching Google Calendar sync', [
            'event_id' => $event->id,
            'changed_fields' => array_keys($event->getDirty()),
        ]);

        SyncEventToGoogleCalendar::dispatch($event)
            ->onQueue('gcal-sync');
    }

    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        // Log event deletion to new audit trail
        $this->calendarChangeLogger->logDeleted($event);

        // Keep legacy logging for backwards compatibility
        $this->logChange($event, 'deleted', [
            'soft_delete' => true,
            'google_event_id' => $event->google_event_id,
        ]);

        Log::info('Event deleted, dispatching Google Calendar deletion', [
            'event_id' => $event->id,
            'google_event_id' => $event->google_event_id,
        ]);

        DeleteEventFromGoogleCalendar::dispatch($event)
            ->onQueue('gcal-sync');
    }

    /**
     * Handle the Event "restored" event.
     */
    public function restored(Event $event): void
    {
        // Log restoration
        $this->logChange($event, 'updated', [
            'action_detail' => 'restored_from_soft_delete',
        ]);

        // When an event is restored, re-sync to Google Calendar
        if ($event->status === 'scheduled') {
            Log::info('Event restored with scheduled status, dispatching Google Calendar sync', [
                'event_id' => $event->id,
            ]);

            SyncEventToGoogleCalendar::dispatch($event)
                ->onQueue('gcal-sync');
        }
    }

    /**
     * Handle the Event "force deleted" event.
     */
    public function forceDeleted(Event $event): void
    {
        // Note: Cannot log to event_changes because the event_id will cascade delete
        Log::warning('Event force deleted', [
            'event_id' => $event->id,
            'google_event_id' => $event->google_event_id,
        ]);

        DeleteEventFromGoogleCalendar::dispatch($event)
            ->onQueue('gcal-sync');
    }

    /**
     * Log change to event_changes audit table
     */
    private function logChange(Event $event, string $action, array $meta): void
    {
        try {
            EventChange::create([
                'event_id' => $event->id,
                'action' => $action,
                'by_user_id' => Auth::id() ?? $event->staff_id, // Fallback to staff_id if no auth user
                'meta' => $meta,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            Log::error('Failed to log event change', [
                'event_id' => $event->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
