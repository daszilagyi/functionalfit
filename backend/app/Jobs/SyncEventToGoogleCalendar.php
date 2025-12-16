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

class SyncEventToGoogleCalendar implements ShouldQueue
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
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * The maximum number of exceptions to allow before failing.
     */
    public int $maxExceptions = 5;

    public function __construct(
        public Event $event
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GoogleCalendarService $googleCalendarService): void
    {
        $startTime = microtime(true);

        try {
            // Check if sync is enabled
            if (!$googleCalendarService->isSyncEnabled()) {
                Log::info('Google Calendar sync is disabled, skipping', [
                    'event_id' => $this->event->id,
                ]);
                return;
            }

            // Only sync scheduled events
            if ($this->event->status !== 'scheduled') {
                Log::info('Event status is not scheduled, skipping sync', [
                    'event_id' => $this->event->id,
                    'status' => $this->event->status,
                ]);
                return;
            }

            Log::info('Starting Google Calendar sync', [
                'event_id' => $this->event->id,
                'attempt' => $this->attempts(),
                'event_type' => $this->event->type,
                'staff_id' => $this->event->staff_id,
            ]);

            // Push event to Google Calendar
            $googleEventId = $googleCalendarService->pushEventToGoogleCalendar($this->event);

            if ($googleEventId) {
                // Update internal event with Google Calendar event ID
                $this->event->update(['google_event_id' => $googleEventId]);

                $latency = (microtime(true) - $startTime) * 1000;

                Log::info('Google Calendar sync successful', [
                    'event_id' => $this->event->id,
                    'google_event_id' => $googleEventId,
                    'operation' => $this->event->wasRecentlyCreated ? 'create' : 'update',
                    'attempt' => $this->attempts(),
                    'latency_ms' => round($latency, 2),
                    'status' => 'success',
                ]);
            } else {
                Log::warning('Google Calendar sync returned null event ID', [
                    'event_id' => $this->event->id,
                    'attempt' => $this->attempts(),
                ]);
            }
        } catch (GoogleServiceException $e) {
            $latency = (microtime(true) - $startTime) * 1000;

            Log::error('Google Calendar API error during sync', [
                'event_id' => $this->event->id,
                'google_event_id' => $this->event->google_event_id,
                'operation' => 'sync',
                'status' => 'failure',
                'attempt_number' => $this->attempts(),
                'max_attempts' => $this->tries,
                'latency_ms' => round($latency, 2),
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'staff_id' => $this->event->staff_id,
            ]);

            // Check if we should retry based on error code
            if (!$this->shouldRetryError($e)) {
                $this->fail($e);
                return;
            }

            throw $e;
        } catch (\Exception $e) {
            $latency = (microtime(true) - $startTime) * 1000;

            Log::error('Unexpected error during Google Calendar sync', [
                'event_id' => $this->event->id,
                'operation' => 'sync',
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
        Log::error('Google Calendar sync job failed permanently', [
            'event_id' => $this->event->id,
            'google_event_id' => $this->event->google_event_id,
            'operation' => 'sync',
            'status' => 'failed_permanently',
            'attempts' => $this->attempts(),
            'error_message' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);

        // Optionally: Send notification to admin about failed sync
        // Or: Create a manual review queue entry
    }

    /**
     * Determine if error should be retried based on error code.
     */
    private function shouldRetryError(GoogleServiceException $e): bool
    {
        $code = $e->getCode();

        // Do NOT retry these errors (client errors, permanent failures)
        $nonRetryableCodes = [400, 401, 403, 404];

        if (in_array($code, $nonRetryableCodes)) {
            Log::warning('Non-retryable error code, marking job as failed', [
                'event_id' => $this->event->id,
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
            'gcal-sync',
            'event:' . $this->event->id,
            'staff:' . $this->event->staff_id,
            'type:' . $this->event->type,
        ];
    }
}
