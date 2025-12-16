<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client as GoogleClient;
use Google\Service\Calendar;

class TestGoogleCalendar extends Command
{
    protected $signature = 'gcal:test {calendar_id}';
    protected $description = 'Test Google Calendar API connection';

    public function handle(): int
    {
        $calendarId = $this->argument('calendar_id');

        $this->info("Testing Google Calendar connection...");
        $this->info("Calendar ID: {$calendarId}");

        // Check service account file
        $serviceAccountPath = config('services.google_calendar.service_account_path');
        $this->info("Service Account Path: {$serviceAccountPath}");

        if (!file_exists($serviceAccountPath)) {
            $this->error("Service account file not found at: {$serviceAccountPath}");
            return 1;
        }

        $this->info("✓ Service account file exists");

        // Initialize client
        try {
            $client = new GoogleClient();
            $client->setApplicationName(config('services.google_calendar.application_name'));
            $client->setScopes(config('services.google_calendar.scopes'));
            $client->setAuthConfig($serviceAccountPath);

            $this->info("✓ Google Client initialized");

            // Test calendar service
            $calendarService = new Calendar($client);
            $this->info("✓ Calendar service created");

            // Try to get calendar info
            $this->info("Fetching calendar info (this may take a moment)...");
            $calendar = $calendarService->calendars->get($calendarId);

            $this->info("✓ Successfully connected to Google Calendar!");
            $this->info("  Calendar Summary: " . $calendar->getSummary());
            $this->info("  Calendar Timezone: " . $calendar->getTimeZone());

            // Try to list events
            $this->info("\nFetching recent events (max 5)...");
            $optParams = [
                'maxResults' => 5,
                'orderBy' => 'startTime',
                'singleEvents' => true,
                'timeMin' => date('c', strtotime('-30 days')),
            ];

            $events = $calendarService->events->listEvents($calendarId, $optParams);
            $this->info("✓ Found " . count($events->getItems()) . " events");

            foreach ($events->getItems() as $event) {
                $start = $event->start->dateTime ?? $event->start->date;
                $this->info("  - {$event->getSummary()} ({$start})");
            }

            return 0;
        } catch (\Google\Service\Exception $e) {
            $this->error("Google API Error: " . $e->getMessage());
            $this->error("Code: " . $e->getCode());

            if ($e->getCode() === 404) {
                $this->warn("\nPossible causes:");
                $this->warn("  - Calendar ID is incorrect");
                $this->warn("  - Calendar has not been shared with the service account");
                $this->warn("\nService account email:");
                $json = json_decode(file_get_contents($serviceAccountPath), true);
                $this->info("  " . $json['client_email']);
                $this->warn("\nPlease share the calendar with this email address!");
            } elseif ($e->getCode() === 403) {
                $this->warn("\nAccess denied. Possible causes:");
                $this->warn("  - Calendar not shared with service account");
                $this->warn("  - Service account does not have Calendar API enabled");
                $this->warn("  - Insufficient permissions");
            }

            return 1;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error("Type: " . get_class($e));
            return 1;
        }
    }
}
