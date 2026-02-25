<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClassOccurrence;
use App\Models\Event;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendDailyTrainerSchedule extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schedule:send-daily-trainer-notifications {--force : Force send regardless of configured hour}';

    /**
     * The console command description.
     */
    protected $description = 'Send daily schedule notifications to trainers who have opted in';

    public function __construct(
        private readonly MailService $mailService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $configuredHour = (int) Setting::get('daily_schedule_notification_hour', 7);
        $currentHour = (int) now('Europe/Budapest')->format('H');

        // Only send at the configured hour (unless --force is used)
        if (!$this->option('force') && $currentHour !== $configuredHour) {
            $this->info("Current hour ({$currentHour}) does not match configured hour ({$configuredHour}). Skipping.");
            return Command::SUCCESS;
        }

        $this->info("Sending daily trainer schedule notifications...");

        // Get all staff with daily notification enabled
        $staffProfiles = StaffProfile::where('daily_schedule_notification', true)
            ->with('user')
            ->get();

        if ($staffProfiles->isEmpty()) {
            $this->info("No trainers have daily schedule notification enabled.");
            return Command::SUCCESS;
        }

        $this->info("Found {$staffProfiles->count()} trainers with notifications enabled.");

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($staffProfiles as $staff) {
            $result = $this->processStaff($staff);
            if ($result) {
                $sentCount++;
            } else {
                $skippedCount++;
            }
        }

        $this->info("Sent {$sentCount} notifications, skipped {$skippedCount} (no events).");

        return Command::SUCCESS;
    }

    /**
     * Process a single staff member and send their daily schedule if they have events.
     */
    private function processStaff(StaffProfile $staff): bool
    {
        $today = Carbon::today('Europe/Budapest');
        $todayStart = $today->copy()->startOfDay();
        $todayEnd = $today->copy()->endOfDay();

        // Get individual events for today
        $events = Event::where('staff_id', $staff->id)
            ->where('status', 'scheduled')
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->with(['client', 'room'])
            ->orderBy('starts_at')
            ->get();

        // Get class occurrences for today
        $classes = ClassOccurrence::where('trainer_id', $staff->id)
            ->where('status', 'scheduled')
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->with(['template', 'room', 'registrations'])
            ->orderBy('starts_at')
            ->get();

        // Skip if no events
        if ($events->isEmpty() && $classes->isEmpty()) {
            $this->line("  Skipping {$staff->user->name}: no events today");
            return false;
        }

        // Prepare template variables
        $variables = $this->prepareVariables($staff, $events, $classes, $today);

        try {
            $this->mailService->send(
                'daily-schedule',
                $staff->user->email,
                $variables,
                $staff->user->name
            );

            $this->line("  Sent to {$staff->user->name} ({$staff->user->email})");
            return true;
        } catch (\Exception $e) {
            $this->error("  Failed for {$staff->user->name}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Prepare template variables for the email.
     */
    private function prepareVariables(
        StaffProfile $staff,
        Collection $events,
        Collection $classes,
        Carbon $today
    ): array {
        $individualCount = $events->count();
        $groupCount = $classes->count();
        $totalCount = $individualCount + $groupCount;

        return [
            'trainer' => [
                'name' => $staff->user->name,
            ],
            'date' => $today->translatedFormat('Y. F j.'), // Hungarian date format
            'events_count' => $totalCount,
            'individual_count' => $individualCount,
            'group_count' => $groupCount,
            'events_table' => $this->buildEventsTableHtml($events, $classes),
            'events_list' => $this->buildEventsListText($events, $classes),
            'motivational_quote' => \App\Models\MotivationalQuote::inRandomOrder()->first()?->text ?? '',
        ];
    }

    /**
     * Build HTML table of events for the email.
     */
    private function buildEventsTableHtml(Collection $events, Collection $classes): string
    {
        $html = '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
        $html .= '<thead><tr style="background: #3b82f6; color: white;">';
        $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Időpont</th>';
        $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Típus</th>';
        $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Vendég / Óra neve</th>';
        $html .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Terem</th>';
        $html .= '</tr></thead><tbody>';

        // Combine and sort by start time
        $allItems = collect();

        foreach ($events as $event) {
            $allItems->push([
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'type' => 'Személyi',
                'name' => $event->client?->user?->name ?? 'N/A',
                'room' => $event->room?->name ?? 'N/A',
            ]);
        }

        foreach ($classes as $class) {
            $registrationCount = $class->registrations
                ->whereIn('status', ['booked', 'attended'])
                ->count();

            $allItems->push([
                'starts_at' => $class->starts_at,
                'ends_at' => $class->ends_at,
                'type' => 'Csoportos',
                'name' => ($class->template?->name ?? 'N/A') . " ({$registrationCount} fő)",
                'room' => $class->room?->name ?? 'N/A',
            ]);
        }

        $sortedItems = $allItems->sortBy('starts_at');

        $rowIndex = 0;
        foreach ($sortedItems as $item) {
            $bgColor = $rowIndex % 2 === 0 ? '#f8fafc' : '#ffffff';
            $timeRange = $item['starts_at']->format('H:i') . ' - ' . $item['ends_at']->format('H:i');

            $html .= "<tr style=\"background: {$bgColor};\">";
            $html .= "<td style=\"padding: 8px; border: 1px solid #ddd;\">{$timeRange}</td>";
            $html .= "<td style=\"padding: 8px; border: 1px solid #ddd;\">{$item['type']}</td>";
            $html .= "<td style=\"padding: 8px; border: 1px solid #ddd;\">{$item['name']}</td>";
            $html .= "<td style=\"padding: 8px; border: 1px solid #ddd;\">{$item['room']}</td>";
            $html .= '</tr>';
            $rowIndex++;
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Build plain text list of events for the fallback email.
     */
    private function buildEventsListText(Collection $events, Collection $classes): string
    {
        $lines = [];

        // Combine and sort by start time
        $allItems = collect();

        foreach ($events as $event) {
            $allItems->push([
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'type' => 'Személyi',
                'name' => $event->client?->user?->name ?? 'N/A',
                'room' => $event->room?->name ?? 'N/A',
            ]);
        }

        foreach ($classes as $class) {
            $registrationCount = $class->registrations
                ->whereIn('status', ['booked', 'attended'])
                ->count();

            $allItems->push([
                'starts_at' => $class->starts_at,
                'ends_at' => $class->ends_at,
                'type' => 'Csoportos',
                'name' => ($class->template?->name ?? 'N/A') . " ({$registrationCount} fő)",
                'room' => $class->room?->name ?? 'N/A',
            ]);
        }

        $sortedItems = $allItems->sortBy('starts_at');

        foreach ($sortedItems as $item) {
            $timeRange = $item['starts_at']->format('H:i') . ' - ' . $item['ends_at']->format('H:i');
            $lines[] = "- {$timeRange} | {$item['type']} | {$item['name']} | {$item['room']}";
        }

        return implode("\n", $lines);
    }
}
