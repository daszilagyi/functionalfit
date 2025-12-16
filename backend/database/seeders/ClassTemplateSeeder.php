<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ClassTemplate;
use App\Models\ClassOccurrence;
use App\Models\Room;
use App\Models\StaffProfile;
use Carbon\Carbon;

class ClassTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get rooms and staff for occurrences
        $rooms = Room::all();
        $staffProfiles = StaffProfile::all();

        if ($rooms->isEmpty() || $staffProfiles->isEmpty()) {
            $this->command->warn('No rooms or staff found. Please run RoomSeeder and UserSeeder first.');
            return;
        }

        // Define class templates
        $templates = [
            [
                'title' => 'Functional Training',
                'description' => 'Funkcionális edzés gyakorlatokkal az egész testet átmozgató gyakorlatokkal.',
                'trainer_id' => $staffProfiles->first()->id,
                'room_id' => $rooms->where('name', 'LIKE', '%Gym%')->first()->id ?? $rooms->first()->id,
                'duration_min' => 60,
                'capacity' => 12,
                'credits_required' => 1,
                'tags' => ['functional', 'strength', 'cardio'],
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
                'status' => 'active',
            ],
            [
                'title' => 'Yoga',
                'description' => 'Relaxáló jóga óra minden szintnek.',
                'trainer_id' => $staffProfiles->skip(1)->first()->id ?? $staffProfiles->first()->id,
                'room_id' => $rooms->where('name', 'LIKE', '%Gym%')->first()->id ?? $rooms->first()->id,
                'duration_min' => 75,
                'capacity' => 15,
                'credits_required' => 1,
                'tags' => ['yoga', 'flexibility', 'mindfulness'],
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=TU,TH',
                'status' => 'active',
            ],
            [
                'title' => 'HIIT',
                'description' => 'High Intensity Interval Training - intenzív intervallumos edzés.',
                'trainer_id' => $staffProfiles->first()->id,
                'room_id' => $rooms->where('name', 'LIKE', '%Gym%')->first()->id ?? $rooms->first()->id,
                'duration_min' => 45,
                'capacity' => 10,
                'credits_required' => 1,
                'tags' => ['hiit', 'cardio', 'advanced'],
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=WE,FR',
                'status' => 'active',
            ],
            [
                'title' => 'Pilates',
                'description' => 'Pilates gyakorlatok a core izmok erősítésére.',
                'trainer_id' => $staffProfiles->skip(1)->first()->id ?? $staffProfiles->first()->id,
                'room_id' => $rooms->where('name', 'LIKE', '%Gym%')->first()->id ?? $rooms->first()->id,
                'duration_min' => 60,
                'capacity' => 12,
                'credits_required' => 1,
                'tags' => ['pilates', 'core', 'flexibility'],
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE',
                'status' => 'active',
            ],
            [
                'title' => 'Spinning',
                'description' => 'Intenzív spinning óra zenére.',
                'trainer_id' => $staffProfiles->first()->id,
                'room_id' => $rooms->where('name', 'LIKE', '%Gym%')->first()->id ?? $rooms->first()->id,
                'duration_min' => 50,
                'capacity' => 20,
                'credits_required' => 1,
                'tags' => ['spinning', 'cardio', 'endurance'],
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=TU,TH,SA',
                'status' => 'active',
            ],
        ];

        foreach ($templates as $templateData) {
            $template = ClassTemplate::create($templateData);

            // Create upcoming occurrences for the next 14 days
            $this->createOccurrencesForTemplate($template);
        }

        $this->command->info('Class templates and occurrences seeded successfully!');
    }

    /**
     * Create occurrences for a template
     */
    private function createOccurrencesForTemplate(ClassTemplate $template): void
    {
        // Parse RRULE to get days
        preg_match('/BYDAY=([A-Z,]+)/', $template->weekly_rrule ?? '', $matches);
        if (empty($matches[1])) {
            return;
        }

        $days = explode(',', $matches[1]);
        $dayMap = [
            'MO' => Carbon::MONDAY,
            'TU' => Carbon::TUESDAY,
            'WE' => Carbon::WEDNESDAY,
            'TH' => Carbon::THURSDAY,
            'FR' => Carbon::FRIDAY,
            'SA' => Carbon::SATURDAY,
            'SU' => Carbon::SUNDAY,
        ];

        // Define time slots for each template
        $timeSlots = [
            'Functional Training' => ['09:00', '18:00'],
            'Yoga' => ['07:00', '19:00'],
            'HIIT' => ['06:30', '17:30'],
            'Pilates' => ['10:00', '18:30'],
            'Spinning' => ['07:00', '18:00', '12:00'],
        ];

        $slots = $timeSlots[$template->title] ?? ['18:00'];

        // Create occurrences for the next 14 days
        $startDate = Carbon::now()->startOfDay();
        $endDate = $startDate->copy()->addDays(14);

        while ($startDate->lte($endDate)) {
            foreach ($days as $day) {
                if (!isset($dayMap[$day])) {
                    continue;
                }

                $dayOfWeek = $dayMap[$day];
                $occurrenceDate = $startDate->copy()->next($dayOfWeek);

                if ($occurrenceDate->lte($endDate)) {
                    foreach ($slots as $time) {
                        $startsAt = $occurrenceDate->copy()->setTimeFromTimeString($time);
                        $endsAt = $startsAt->copy()->addMinutes($template->duration_min);

                        // Only create future occurrences
                        if ($startsAt->isFuture()) {
                            ClassOccurrence::create([
                                'template_id' => $template->id,
                                'trainer_id' => $template->trainer_id,
                                'room_id' => $template->room_id,
                                'starts_at' => $startsAt,
                                'ends_at' => $endsAt,
                                'capacity' => $template->capacity,
                                'status' => 'scheduled',
                            ]);
                        }
                    }
                }
            }
            $startDate->addDay();
        }
    }
}
