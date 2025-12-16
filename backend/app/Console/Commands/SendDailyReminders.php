<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClassRegistration;
use App\Models\Event;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendDailyReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reminders:send-daily {--hours=24 : Hours before class/event to send reminder}';

    /**
     * The console command description.
     */
    protected $description = 'Send reminder notifications for upcoming classes and events';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hoursAhead = (int) $this->option('hours');
        $targetTime = now()->addHours($hoursAhead);

        // Define time window (e.g., 24 hours ± 1 hour)
        $startWindow = $targetTime->copy()->subHour();
        $endWindow = $targetTime->copy()->addHour();

        $this->info("Sending reminders for classes/events starting between {$startWindow->toDateTimeString()} and {$endWindow->toDateTimeString()}");

        $classReminders = $this->sendClassReminders($startWindow, $endWindow, $hoursAhead);
        $eventReminders = $this->sendEventReminders($startWindow, $endWindow);

        $totalSent = $classReminders + $eventReminders;

        $this->info("Sent {$totalSent} reminders ({$classReminders} classes, {$eventReminders} events)");

        return Command::SUCCESS;
    }

    /**
     * Send reminders for class registrations
     */
    private function sendClassReminders(Carbon $startWindow, Carbon $endWindow, int $hoursAhead): int
    {
        $registrations = ClassRegistration::with(['classOccurrence', 'client'])
            ->where('status', 'confirmed')
            ->whereHas('classOccurrence', function ($query) use ($startWindow, $endWindow) {
                $query->whereBetween('starts_at', [$startWindow, $endWindow])
                      ->where('status', 'scheduled');
            })
            ->get();

        $sentCount = 0;

        foreach ($registrations as $registration) {
            // Check if client has opted in for this reminder type
            $client = $registration->client;
            $shouldSendReminder = $hoursAhead === 24
                ? $client->email_reminder_24h
                : ($hoursAhead === 2 ? $client->email_reminder_2h : false);

            if (!$shouldSendReminder) {
                $this->line("Skipped reminder for {$client->user->email} (preference disabled)");
                continue;
            }

            try {
                $this->notificationService->sendClassReminder($registration, $hoursAhead);
                $this->line("Sent class reminder to {$registration->client->user->email}");
                $sentCount++;
            } catch (\Exception $e) {
                $this->error("Failed to send class reminder: {$e->getMessage()}");
            }
        }

        return $sentCount;
    }

    /**
     * Send reminders for 1:1 events
     */
    private function sendEventReminders(Carbon $startWindow, Carbon $endWindow): int
    {
        // Calculate hours ahead based on the command option
        $hoursAhead = (int) $this->option('hours');

        $events = Event::with(['client', 'staff'])
            ->where('type', 'individual')
            ->where('status', 'scheduled')
            ->whereBetween('starts_at', [$startWindow, $endWindow])
            ->whereNotNull('client_id')
            ->get();

        $sentCount = 0;

        foreach ($events as $event) {
            // Check if client has opted in for this reminder type
            $client = $event->client;
            $shouldSendReminder = $hoursAhead === 24
                ? $client->email_reminder_24h
                : ($hoursAhead === 2 ? $client->email_reminder_2h : false);

            if (!$shouldSendReminder) {
                $this->line("Skipped event reminder for {$client->user->email} (preference disabled)");
                continue;
            }

            try {
                $this->notificationService->sendEventReminder($event);
                $this->line("Sent event reminder to {$event->client->user->email}");
                $sentCount++;
            } catch (\Exception $e) {
                $this->error("Failed to send event reminder: {$e->getMessage()}");
            }
        }

        return $sentCount;
    }
}
