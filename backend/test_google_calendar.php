<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\GoogleCalendarSyncConfig;
use App\Services\GoogleCalendarService;

echo "=== Google Calendar Sync Test ===\n\n";

try {
    $config = GoogleCalendarSyncConfig::first();

    if (!$config) {
        echo "ERROR: No sync configuration found\n";
        exit(1);
    }

    echo "Config: {$config->name}\n";
    echo "Calendar ID: {$config->google_calendar_id}\n";
    echo "Sync enabled: " . ($config->sync_enabled ? 'YES' : 'NO') . "\n";
    echo "Sync direction: {$config->sync_direction}\n";
    echo "\nService Account Email: functionalfit-calendar@functionalfitcalendarproject.iam.gserviceaccount.com\n";
    echo "\n--- IMPORTANT ---\n";
    echo "Make sure you've shared the calendar '{$config->google_calendar_id}' with the Service Account email above!\n";
    echo "Go to Google Calendar → Settings → Share with specific people → Add the email above with 'Make changes to events' permission\n";
    echo "\n";

    $service = app(GoogleCalendarService::class);

    echo "Testing Google Calendar API connection...\n";

    if (!$service->isSyncEnabled()) {
        echo "WARNING: Google Calendar sync is disabled in config\n";
    }

    $calendarService = $service->getCalendarService();

    // Try to list events
    $optParams = [
        'maxResults' => 5,
        'timeMin' => date('c'),
        'singleEvents' => true,
        'orderBy' => 'startTime',
    ];

    echo "Fetching events from Google Calendar...\n";
    $events = $calendarService->events->listEvents($config->google_calendar_id, $optParams);

    echo "\n✅ SUCCESS! Connection established.\n";
    echo "Found " . count($events->getItems()) . " upcoming events.\n\n";

    if (count($events->getItems()) > 0) {
        echo "Recent events:\n";
        foreach ($events->getItems() as $event) {
            $start = $event->getStart()->getDateTime() ?? $event->getStart()->getDate();
            echo "  - {$event->getSummary()} (Start: {$start})\n";
        }
    }

} catch (Google\Service\Exception $e) {
    echo "\n❌ Google Calendar API Error:\n";
    echo "Code: {$e->getCode()}\n";
    echo "Message: {$e->getMessage()}\n\n";

    if ($e->getCode() === 403) {
        echo "This is likely a permissions issue. Make sure:\n";
        echo "1. The Service Account email is added to the calendar with proper permissions\n";
        echo "2. The Google Calendar API is enabled in your Google Cloud project\n";
    } elseif ($e->getCode() === 404) {
        echo "Calendar not found. Check that the Calendar ID is correct.\n";
    }

    exit(1);

} catch (Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}\n";
    echo "Line: {$e->getLine()}\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";
