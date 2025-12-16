<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\StaffProfile;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleCalendarService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const MAX_RETRIES = 5;
    private const BASE_BACKOFF = 2; // seconds
    private const MAX_BACKOFF = 60; // seconds
    private const JITTER_PERCENT = 20;

    private GoogleClient $client;
    private Calendar $calendarService;

    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * Initialize Google API client with authentication.
     */
    private function initializeClient(): void
    {
        $this->client = new GoogleClient();
        $this->client->setApplicationName(config('services.google_calendar.application_name'));
        $this->client->setScopes(config('services.google_calendar.scopes'));

        // Use service account authentication
        $serviceAccountPath = config('services.google_calendar.service_account_path');

        if (file_exists($serviceAccountPath)) {
            $this->client->setAuthConfig($serviceAccountPath);
        } else {
            Log::warning('Google service account file not found', [
                'path' => $serviceAccountPath,
            ]);
        }

        $this->calendarService = new Calendar($this->client);
    }

    /**
     * Check if Google Calendar sync is enabled.
     */
    public function isSyncEnabled(): bool
    {
        return (bool) config('services.google_calendar.sync_enabled', false);
    }

    /**
     * Push event to Google Calendar (create or update).
     *
     * @return string|null Google Calendar event ID
     */
    public function pushEventToGoogleCalendar(Event $event): ?string
    {
        if (!$this->isSyncEnabled()) {
            Log::info('Google Calendar sync is disabled', ['event_id' => $event->id]);
            return null;
        }

        try {
            $staff = $event->staff;
            $calendarId = $this->getCalendarIdForStaff($staff);

            if (!$calendarId) {
                Log::warning('No calendar ID found for staff', [
                    'event_id' => $event->id,
                    'staff_id' => $staff->id,
                ]);
                return null;
            }

            // Check if event already exists in Google Calendar (idempotency)
            $existingGoogleEventId = $this->findExistingGoogleEvent($event, $calendarId);

            if ($existingGoogleEventId) {
                // Update existing event
                return $this->updateGoogleEvent($event, $calendarId, $existingGoogleEventId);
            } else {
                // Create new event
                return $this->createGoogleEvent($event, $calendarId);
            }
        } catch (GoogleServiceException $e) {
            Log::error('Google Calendar API error', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error pushing event to Google Calendar', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete event from Google Calendar.
     */
    public function deleteEventFromGoogleCalendar(Event $event): bool
    {
        if (!$this->isSyncEnabled()) {
            Log::info('Google Calendar sync is disabled', ['event_id' => $event->id]);
            return false;
        }

        if (!$event->google_event_id) {
            Log::info('Event has no Google Calendar ID', ['event_id' => $event->id]);
            return false;
        }

        try {
            $staff = $event->staff;
            $calendarId = $this->getCalendarIdForStaff($staff);

            if (!$calendarId) {
                Log::warning('No calendar ID found for staff', [
                    'event_id' => $event->id,
                    'staff_id' => $staff->id,
                ]);
                return false;
            }

            $this->executeWithRetry(function () use ($calendarId, $event) {
                $this->calendarService->events->delete($calendarId, $event->google_event_id);
            });

            Log::info('Event deleted from Google Calendar', [
                'event_id' => $event->id,
                'google_event_id' => $event->google_event_id,
            ]);

            return true;
        } catch (GoogleServiceException $e) {
            // 404 means already deleted, consider it success
            if ($e->getCode() === 404) {
                Log::info('Event already deleted from Google Calendar', [
                    'event_id' => $event->id,
                    'google_event_id' => $event->google_event_id,
                ]);
                return true;
            }

            Log::error('Failed to delete event from Google Calendar', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected error deleting event from Google Calendar', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get or create Google Calendar ID for staff member.
     */
    public function getCalendarIdForStaff(StaffProfile $staff): ?string
    {
        // For now, use the staff user's email as calendar ID
        // In production, you might want to store this in the database
        // or create separate calendars for each staff member

        $cacheKey = "gcal_calendar_id_staff_{$staff->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($staff) {
            // Default to primary calendar for the staff user
            // You can extend this to create separate calendars
            return 'primary';
        });
    }

    /**
     * Find existing Google Calendar event by internal event ID.
     */
    private function findExistingGoogleEvent(Event $event, string $calendarId): ?string
    {
        // First check if we have the google_event_id stored
        if ($event->google_event_id) {
            try {
                $googleEvent = $this->calendarService->events->get($calendarId, $event->google_event_id);
                if ($googleEvent) {
                    return $event->google_event_id;
                }
            } catch (GoogleServiceException $e) {
                if ($e->getCode() === 404) {
                    Log::info('Stored Google event ID not found, will search by extended properties', [
                        'event_id' => $event->id,
                        'google_event_id' => $event->google_event_id,
                    ]);
                }
            }
        }

        // Search by extended properties
        try {
            $optParams = [
                'privateExtendedProperty' => "internal_event_id={$event->id}",
                'singleEvents' => true,
                'maxResults' => 1,
            ];

            $events = $this->calendarService->events->listEvents($calendarId, $optParams);

            if (count($events->getItems()) > 0) {
                $googleEvent = $events->getItems()[0];
                return $googleEvent->getId();
            }
        } catch (GoogleServiceException $e) {
            Log::warning('Error searching for existing Google event', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Create new Google Calendar event.
     */
    private function createGoogleEvent(Event $event, string $calendarId): string
    {
        $googleEvent = $this->buildGoogleEvent($event);

        $createdEvent = $this->executeWithRetry(function () use ($calendarId, $googleEvent) {
            return $this->calendarService->events->insert($calendarId, $googleEvent);
        });

        $googleEventId = $createdEvent->getId();

        Log::info('Event created in Google Calendar', [
            'event_id' => $event->id,
            'google_event_id' => $googleEventId,
        ]);

        return $googleEventId;
    }

    /**
     * Update existing Google Calendar event.
     */
    private function updateGoogleEvent(Event $event, string $calendarId, string $googleEventId): string
    {
        $googleEvent = $this->buildGoogleEvent($event);

        $updatedEvent = $this->executeWithRetry(function () use ($calendarId, $googleEventId, $googleEvent) {
            return $this->calendarService->events->update($calendarId, $googleEventId, $googleEvent);
        });

        Log::info('Event updated in Google Calendar', [
            'event_id' => $event->id,
            'google_event_id' => $googleEventId,
        ]);

        return $updatedEvent->getId();
    }

    /**
     * Build Google Calendar event from internal Event model.
     */
    private function buildGoogleEvent(Event $event): GoogleEvent
    {
        $googleEvent = new GoogleEvent();

        // Summary (title)
        $summary = $this->buildEventSummary($event);
        $googleEvent->setSummary($summary);

        // Description
        $description = $this->buildEventDescription($event);
        $googleEvent->setDescription($description);

        // Start and end times (with timezone)
        $timezone = config('app.timezone', 'Europe/Budapest');

        $start = new EventDateTime();
        $start->setDateTime($event->starts_at->format('c'));
        $start->setTimeZone($timezone);
        $googleEvent->setStart($start);

        $end = new EventDateTime();
        $end->setDateTime($event->ends_at->format('c'));
        $end->setTimeZone($timezone);
        $googleEvent->setEnd($end);

        // Location
        if ($event->room) {
            $location = $event->room->name;
            if ($event->room->location) {
                $location .= ', ' . $event->room->location;
            }
            $googleEvent->setLocation($location);
        }

        // Extended properties (for idempotency and tracking)
        $extendedProperties = new \Google\Service\Calendar\EventExtendedProperties();
        $extendedProperties->setPrivate([
            'internal_event_id' => (string) $event->id,
            'system' => 'functionalfit',
            'sync_version' => now()->timestamp,
            'event_type' => $event->type,
        ]);
        $googleEvent->setExtendedProperties($extendedProperties);

        // Status
        if ($event->status === 'cancelled') {
            $googleEvent->setStatus('cancelled');
        } else {
            $googleEvent->setStatus('confirmed');
        }

        // Color (optional - differentiate event types)
        // 1=lavender, 2=sage, 3=grape, 4=flamingo, 5=banana, 6=tangerine, 7=peacock, 8=graphite, 9=blueberry, 10=basil, 11=tomato
        if ($event->type === 'BLOCK') {
            $googleEvent->setColorId('8'); // graphite for maintenance blocks
        } else {
            $googleEvent->setColorId('7'); // peacock for individual sessions
        }

        return $googleEvent;
    }

    /**
     * Build event summary (title) for Google Calendar.
     */
    private function buildEventSummary(Event $event): string
    {
        if ($event->type === 'BLOCK') {
            return 'BLOCK: ' . ($event->notes ?: 'Maintenance/Unavailable');
        }

        $summary = 'Session';

        if ($event->client) {
            $clientName = $event->client->user->name ?? 'Client';
            $summary .= " - {$clientName}";
        }

        return $summary;
    }

    /**
     * Build event description for Google Calendar.
     */
    private function buildEventDescription(Event $event): string
    {
        $lines = [];

        $lines[] = "**Event Type:** " . ucfirst(strtolower($event->type));
        $lines[] = "**Status:** " . ucfirst($event->status);

        if ($event->client) {
            $lines[] = "**Client:** " . ($event->client->user->name ?? 'N/A');
            $lines[] = "**Client Email:** " . ($event->client->user->email ?? 'N/A');
        }

        if ($event->staff) {
            $lines[] = "**Staff:** " . ($event->staff->user->name ?? 'N/A');
        }

        if ($event->room) {
            $lines[] = "**Room:** " . $event->room->name;
        }

        if ($event->notes) {
            $lines[] = "";
            $lines[] = "**Notes:**";
            $lines[] = $event->notes;
        }

        $lines[] = "";
        $lines[] = "---";
        $lines[] = "_Synced from FunctionalFit Calendar_";
        $lines[] = "_Internal Event ID: {$event->id}_";

        return implode("\n", $lines);
    }

    /**
     * Execute API call with exponential backoff retry logic.
     */
    private function executeWithRetry(callable $callback, int $attempt = 1): mixed
    {
        try {
            return $callback();
        } catch (GoogleServiceException $e) {
            // Check if we should retry
            if (!$this->shouldRetry($e, $attempt)) {
                throw $e;
            }

            // Calculate backoff with jitter
            $backoff = $this->calculateBackoff($attempt);

            Log::warning('Google Calendar API call failed, retrying', [
                'attempt' => $attempt,
                'max_retries' => self::MAX_RETRIES,
                'backoff_seconds' => $backoff,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            sleep($backoff);

            return $this->executeWithRetry($callback, $attempt + 1);
        }
    }

    /**
     * Determine if error is retryable.
     */
    private function shouldRetry(GoogleServiceException $e, int $attempt): bool
    {
        // Don't retry if max attempts reached
        if ($attempt >= self::MAX_RETRIES) {
            return false;
        }

        $code = $e->getCode();

        // Retry on rate limit and server errors
        $retryableCodes = [429, 500, 502, 503, 504];

        return in_array($code, $retryableCodes);
    }

    /**
     * Calculate exponential backoff with jitter.
     */
    private function calculateBackoff(int $attempt): int
    {
        $exponentialBackoff = min(
            self::BASE_BACKOFF * pow(2, $attempt - 1),
            self::MAX_BACKOFF
        );

        // Add jitter (±20%)
        $jitter = $exponentialBackoff * (self::JITTER_PERCENT / 100);
        $jitterAmount = rand(-$jitter, $jitter);

        return (int) max(1, $exponentialBackoff + $jitterAmount);
    }

    /**
     * Get calendar service instance (for testing/advanced usage).
     */
    public function getCalendarService(): Calendar
    {
        return $this->calendarService;
    }

    /**
     * Get client instance (for testing/advanced usage).
     */
    public function getClient(): GoogleClient
    {
        return $this->client;
    }

    /**
     * Import events from Google Calendar within a date range.
     *
     * @param string $calendarId Google Calendar ID to import from
     * @param \DateTime $startDate Start of date range
     * @param \DateTime $endDate End of date range
     * @param array $filters Additional filters (room_id, etc.)
     * @return array List of Google Calendar events
     */
    public function importEventsFromGoogleCalendar(
        string $calendarId,
        \DateTime $startDate,
        \DateTime $endDate,
        array $filters = []
    ): array {
        if (!$this->isSyncEnabled()) {
            Log::info('Google Calendar sync is disabled');
            return [];
        }

        try {
            $optParams = [
                'timeMin' => $startDate->format('c'),
                'timeMax' => $endDate->format('c'),
                'singleEvents' => true,
                'orderBy' => 'startTime',
                'maxResults' => 2500, // Google Calendar API limit
            ];

            $events = [];
            $pageToken = null;

            do {
                if ($pageToken) {
                    $optParams['pageToken'] = $pageToken;
                }

                $response = $this->executeWithRetry(function () use ($calendarId, $optParams) {
                    return $this->calendarService->events->listEvents($calendarId, $optParams);
                });

                foreach ($response->getItems() as $googleEvent) {
                    $events[] = $this->convertGoogleEventToArray($googleEvent);
                }

                $pageToken = $response->getNextPageToken();
            } while ($pageToken);

            Log::info('Successfully imported events from Google Calendar', [
                'calendar_id' => $calendarId,
                'count' => count($events),
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
            ]);

            return $events;
        } catch (GoogleServiceException $e) {
            Log::error('Failed to import events from Google Calendar', [
                'calendar_id' => $calendarId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Convert Google Calendar event to array format.
     */
    private function convertGoogleEventToArray(GoogleEvent $googleEvent): array
    {
        $start = $googleEvent->getStart();
        $end = $googleEvent->getEnd();

        // Get extended properties if available
        $extendedProps = $googleEvent->getExtendedProperties();
        $privateProps = $extendedProps ? $extendedProps->getPrivate() : [];

        // Check if this event was originally synced from our system
        $isFromOurSystem = isset($privateProps['system']) && $privateProps['system'] === 'functionalfit';
        $internalEventId = $privateProps['internal_event_id'] ?? null;

        return [
            'google_event_id' => $googleEvent->getId(),
            'summary' => $googleEvent->getSummary(),
            'description' => $googleEvent->getDescription(),
            'location' => $googleEvent->getLocation(),
            'start_time' => $start->getDateTime() ?? $start->getDate(),
            'end_time' => $end->getDateTime() ?? $end->getDate(),
            'status' => $googleEvent->getStatus(),
            'is_from_our_system' => $isFromOurSystem,
            'internal_event_id' => $internalEventId,
            'extended_properties' => $privateProps,
            'color_id' => $googleEvent->getColorId(),
            'created' => $googleEvent->getCreated(),
            'updated' => $googleEvent->getUpdated(),
        ];
    }

    /**
     * Export events to Google Calendar in bulk.
     *
     * @param string $calendarId Google Calendar ID to export to
     * @param array $events Collection of Event models
     * @param bool $overwriteExisting Whether to overwrite existing events
     * @return array Results with created/updated/failed counts
     */
    public function exportEventsToGoogleCalendar(
        string $calendarId,
        array $events,
        bool $overwriteExisting = false
    ): array {
        if (!$this->isSyncEnabled()) {
            Log::info('Google Calendar sync is disabled');
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => [],
            ];
        }

        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($events as $event) {
            try {
                $existingGoogleEventId = $this->findExistingGoogleEvent($event, $calendarId);

                if ($existingGoogleEventId && !$overwriteExisting) {
                    $results['skipped']++;
                    continue;
                }

                if ($existingGoogleEventId) {
                    $this->updateGoogleEvent($event, $calendarId, $existingGoogleEventId);
                    $results['updated']++;
                } else {
                    $this->createGoogleEvent($event, $calendarId);
                    $results['created']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to export event to Google Calendar', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Bulk export to Google Calendar completed', [
            'calendar_id' => $calendarId,
            'results' => $results,
        ]);

        return $results;
    }
}
