<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClassTemplate;
use App\Models\ClassOccurrence;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateRecurringClasses extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'classes:generate-recurring {--days=14 : Number of days to generate ahead}';

    /**
     * The console command description.
     */
    protected $description = 'Generate recurring class occurrences based on RRULE patterns';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysAhead = (int) $this->option('days');
        $startDate = now();
        $endDate = now()->addDays($daysAhead);

        $this->info("Generating class occurrences from {$startDate->toDateString()} to {$endDate->toDateString()}");

        // Get all active class templates with recurrence rules
        $templates = ClassTemplate::where('status', 'active')
            ->whereNotNull('weekly_rrule')
            ->get();

        if ($templates->isEmpty()) {
            $this->warn('No active recurring class templates found');
            return Command::SUCCESS;
        }

        $generatedCount = 0;

        foreach ($templates as $template) {
            $this->line("Processing template: {$template->name}");

            $occurrences = $this->generateOccurrencesForTemplate($template, $startDate, $endDate);

            foreach ($occurrences as $occurrence) {
                // Check if occurrence already exists
                $exists = ClassOccurrence::where('class_template_id', $template->id)
                    ->where('starts_at', $occurrence['starts_at'])
                    ->exists();

                if (!$exists) {
                    ClassOccurrence::create($occurrence);
                    $generatedCount++;
                }
            }
        }

        $this->info("Generated {$generatedCount} new class occurrences");

        return Command::SUCCESS;
    }

    /**
     * Generate occurrences for a template based on RRULE
     */
    private function generateOccurrencesForTemplate(ClassTemplate $template, Carbon $startDate, Carbon $endDate): array
    {
        $occurrences = [];

        // Parse RRULE (simplified implementation)
        // Example RRULE: "FREQ=WEEKLY;BYDAY=MO,WE,FR;BYHOUR=18;BYMINUTE=0"
        $rrule = $template->recurrence_rule;

        if (!$rrule) {
            return $occurrences;
        }

        // Simple parsing (for production, use library like simshaun/recurr)
        preg_match('/FREQ=(\w+)/', $rrule, $freqMatch);
        preg_match('/BYDAY=([A-Z,]+)/', $rrule, $dayMatch);
        preg_match('/BYHOUR=(\d+)/', $rrule, $hourMatch);
        preg_match('/BYMINUTE=(\d+)/', $rrule, $minuteMatch);

        $freq = $freqMatch[1] ?? 'WEEKLY';
        $days = isset($dayMatch[1]) ? explode(',', $dayMatch[1]) : [];
        $hour = (int)($hourMatch[1] ?? 18);
        $minute = (int)($minuteMatch[1] ?? 0);

        $dayMap = [
            'MO' => Carbon::MONDAY,
            'TU' => Carbon::TUESDAY,
            'WE' => Carbon::WEDNESDAY,
            'TH' => Carbon::THURSDAY,
            'FR' => Carbon::FRIDAY,
            'SA' => Carbon::SATURDAY,
            'SU' => Carbon::SUNDAY,
        ];

        // Generate occurrences
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dayOfWeek = $current->dayOfWeekIso;

            foreach ($days as $day) {
                if (isset($dayMap[$day]) && $dayMap[$day] === $dayOfWeek) {
                    $startsAt = $current->copy()->setTime($hour, $minute);
                    $endsAt = $startsAt->copy()->addMinutes($template->duration_minutes);

                    $occurrences[] = [
                        'class_template_id' => $template->id,
                        'room_id' => $template->default_room_id ?? 1, // TODO: handle room assignment
                        'trainer_id' => $template->default_trainer_id ?? 1, // TODO: handle trainer assignment
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'max_capacity' => $template->default_capacity,
                        'current_participants' => 0,
                        'status' => 'scheduled',
                    ];
                }
            }

            $current->addDay();
        }

        return $occurrences;
    }
}
