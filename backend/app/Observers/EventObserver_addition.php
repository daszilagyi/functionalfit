    /**
     * Handle the Event "deleted" event.
     */
    public function deleted(Event $event): void
    {
        // Log deletion
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
