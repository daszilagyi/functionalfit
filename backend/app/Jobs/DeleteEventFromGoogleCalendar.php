<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Event;
use App\Services\GoogleCalendarService;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteEventFromGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120, 240, 480, 960]; // Exponential backoff: 1min, 2min, 4min, 8min, 16min
    }

    /**
     * The maximum number of exceptions to allow before failing.
     */
    public int $maxExceptions = 5;

    /**
     * Store event data separately since the model might be deleted.
     */
    private int $eventId;
    private ?string $googleEventId;
    private int $staffId;
    private string $eventType;

    public function __construct(Event $event)
    {
        $this->eventId = $event->id;
        $this->googleEventId = $event->google_event_id;
        $this->staffId = $event->staff_id;
        $this->eventType = $event->type;
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleCalendarService $googleCalendarService): void
    {
        $startTime = microtime(true);

        try {
            // Check if sync is enabled
            if (!$googleCalendarService->isSyncEnabled()) {
                Log::info('Google Calendar sync is disabled, skipping deletion', [
                    'event_id' => $this->eventId,
                ]);
                return;
            }

            // Check if we have a Google event ID
            if (!$this->googleEventId) {
                Log::info('Event has no Google Calendar ID, nothing to delete', [
                    'event_id' => $this->eventId,
                ]);
                return;
            }

            Log::info('Starting Google Calendar deletion', [
                'event_id' => $this->eventId,
                'google_event_id' => $this->googleEventId,
                'attempt' => $this->attempts(),
                'staff_id' => $this->staffId,
            ]);

            // Try to load the event (it might be soft-deleted or hard-deleted)
            $event = Event::withTrashed()->find($this->eventId);

            if (!$event) {
                // Event is hard deleted, we need to reconstruct minimal data
                Log::warning('Event hard deleted, using stored data for Google Calendar deletion', [
                    'event_id' => $this->eventId,
                ]);

                // We can't delete without the staff profile to get calendar ID
                // In this case, we should have the calendar ID stored or use a fallback
                Log::error('Cannot delete from Google Calendar - event hard deleted and staff profile unavailable', [
                    'event_id' => $this->eventId,
                    'google_event_id' => $this->googleEventId,
                ]);
                return;
            }

            // Delete from Google Calendar
            $success = $googleCalendarService->deleteEventFromGoogleCalendar($event);

            $latency = (microtime(true) - $startTime) * 1000;

            if ($success) {
                Log::info('Google Calendar deletion successful', [
                    'event_id' => $this->eventId,
                    'google_event_id' => $this->googleEventId,
                    'operation' => 'delete',
                    'attempt' => $this->attempts(),
                    'latency_ms' => round($latency, 2),
                    'status' => 'success',
                ]);
            } else {
                Log::warning('Google Calendar deletion returned false', [
                    'event_id' => $this->eventId,
                    'google_event_id' => $this->googleEventId,
                    'attempt' => $this->attempts(),
                ]);
            }
        } catch (GoogleServiceException $e) {
            $latency = (microtime(true) - $startTime) * 1000;

            // 404 means already deleted, consider it success
            if ($e->getCode() === 404) {
                Log::info('Google Calendar event already deleted (404)', [
                    'event_id' => $this->eventId,
                    'google_event_id' => $this->googleEventId,
                    'latency_ms' => round($latency, 2),
                ]);
                return;
            }

            Log::error('Google Calendar API error during deletion', [
                'event_id' => $this->eventId,
                'google_event_id' => $this->googleEventId,
                'operation' => 'delete',
                'status' => 'failure',
                'attempt_number' => $this->attempts(),
                'max_attempts' => $this->tries,
                'latency_ms' => round($latency, 2),
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'staff_id' => $this->staffId,
            ]);

            // Check if we should retry based on error code
            if (!$this->shouldRetryError($e)) {
                $this->fail($e);
                return;
            }

            throw $e;
        } catch (\Exception $e) {
            $latency = (microtime(true) - $startTime) * 1000;

            Log::error('Unexpected error during Google Calendar deletion', [
                'event_id' => $this->eventId,
                'google_event_id' => $this->googleEventId,
                'operation' => 'delete',
                'status' => 'failure',
                'attempt_number' => $this->attempts(),
                'latency_ms' => round($latency, 2),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Google Calendar deletion job failed permanently', [
            'event_id' => $this->eventId,
            'google_event_id' => $this->googleEventId,
            'operation' => 'delete',
            'status' => 'failed_permanently',
            'attempts' => $this->attempts(),
            'error_message' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);

        // Optionally: Send notification to admin about failed deletion
        // This is important as it means the event is deleted in DB but still in Google Calendar
    }

    /**
     * Determine if error should be retried based on error code.
     */
    private function shouldRetryError(GoogleServiceException $e): bool
    {
        $code = $e->getCode();

        // Do NOT retry these errors (client errors, permanent failures)
        $nonRetryableCodes = [400, 401, 403];

        if (in_array($code, $nonRetryableCodes)) {
            Log::warning('Non-retryable error code, marking job as failed', [
                'event_id' => $this->eventId,
                'error_code' => $code,
                'error_message' => $e->getMessage(),
            ]);
            return false;
        }

        // Retry rate limit and server errors
        return true;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'gcal-delete',
            'event:' . $this->eventId,
            'staff:' . $this->staffId,
            'type:' . $this->eventType,
        ];
    }
}
