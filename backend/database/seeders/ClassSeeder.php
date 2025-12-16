<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClassSeeder extends Seeder
{
    /**
     * Seed class templates and occurrences.
     *
     * Creates sample group fitness classes with recurring schedules
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Get staff and rooms for assignments
        $staff = DB::table('staff_profiles')->get();
        $rooms = DB::table('rooms')->get();

        if ($staff->isEmpty() || $rooms->isEmpty()) {
            $this->command->error('Cannot seed classes: staff or rooms not found. Run UserSeeder and RoomSeeder first.');
            return;
        }

        // Sample class templates
        $templates = [
            [
                'title' => 'Reggeli Yoga',
                'description' => 'Kezdd a napod energikusan! Nyugodt, flow alapú yoga gyakorlatok minden szinten.',
                'trainer_id' => $staff->where('user_id', 3)->first()->id ?? $staff->first()->id, // Éva Nagy
                'room_id' => $rooms->where('name', 'Gym')->where('site_id', 2)->first()->id ?? $rooms->first()->id, // TB Gym
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR', // Hétfő, Szerda, Péntek
                'duration_min' => 60,
                'capacity' => 15,
                'credits_required' => 1,
                'base_price_huf' => 2500,
                'tags' => json_encode(['yoga', 'mindfulness', 'stretching']),
                'status' => 'active',
                'is_public_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'CrossFit Intenzív',
                'description' => 'Erőnléti és funkcionális edzés magas intenzitással. Haladó szint.',
                'trainer_id' => $staff->where('user_id', 4)->first()->id ?? $staff->first()->id, // Péter Tóth
                'room_id' => $rooms->where('name', 'Gym')->where('site_id', 3)->first()->id ?? $rooms->first()->id, // ÚJBUDA Gym
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=TU,TH', // Kedd, Csütörtök
                'duration_min' => 90,
                'capacity' => 12,
                'credits_required' => 2,
                'base_price_huf' => 3500,
                'tags' => json_encode(['crossfit', 'strength', 'cardio']),
                'status' => 'active',
                'is_public_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Pilates Core',
                'description' => 'Törzserősítő gyakorlatok, testtartás javítás. Minden szint.',
                'trainer_id' => $staff->where('user_id', 3)->first()->id ?? $staff->first()->id, // Éva Nagy
                'room_id' => $rooms->where('name', 'Nagyterem')->first()->id ?? $rooms->skip(1)->first()->id, // TB Nagyterem
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR', // Hétfő, Szerda, Péntek
                'duration_min' => 55,
                'capacity' => 20,
                'credits_required' => 1,
                'base_price_huf' => 2800,
                'tags' => json_encode(['pilates', 'core', 'posture']),
                'status' => 'active',
                'is_public_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Erőnléti Kör',
                'description' => 'Vegyes funkcionális edzés körbejárással. Kezdő-középhaladó.',
                'trainer_id' => $staff->where('user_id', 2)->first()->id ?? $staff->first()->id, // János Kovács
                'room_id' => $rooms->where('name', 'Gym')->where('site_id', 1)->first()->id ?? $rooms->first()->id, // SASAD Gym
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=TU,TH,SA', // Kedd, Csütörtök, Szombat
                'duration_min' => 60,
                'capacity' => 15,
                'credits_required' => 1,
                'base_price_huf' => 2500,
                'tags' => json_encode(['functional', 'circuit', 'strength']),
                'status' => 'active',
                'is_public_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Spinning',
                'description' => 'Intenzív kerékpáros kardió edzés zenére. Haladó szint.',
                'trainer_id' => $staff->where('user_id', 2)->first()->id ?? $staff->first()->id, // János Kovács
                'room_id' => $rooms->where('name', 'Terem 1')->first()->id ?? $rooms->skip(2)->first()->id, // TB Terem 1
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR', // Hétfő, Szerda, Péntek
                'duration_min' => 45,
                'capacity' => 12,
                'credits_required' => 1,
                'base_price_huf' => 3000,
                'tags' => json_encode(['spinning', 'cardio', 'endurance']),
                'status' => 'active',
                'is_public_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Gerinctorna',
                'description' => 'Hátfájás megelőzés és gerincbarát gyakorlatok. Minden korosztály.',
                'trainer_id' => $staff->where('user_id', 2)->first()->id ?? $staff->first()->id, // János Kovács
                'room_id' => $rooms->where('name', 'Rehab')->first()->id ?? $rooms->skip(3)->first()->id, // SASAD Rehab
                'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=TU,TH', // Kedd, Csütörtök
                'duration_min' => 50,
                'capacity' => 8,
                'credits_required' => 1,
                'base_price_huf' => 2200,
                'tags' => json_encode(['rehabilitation', 'back', 'therapy']),
                'status' => 'active',
                'is_public_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($templates as $template) {
            $templateId = DB::table('class_templates')->insertGetId($template);
            $this->command->info("Created class template: {$template['title']}");

            // Generate occurrences for the next 2 weeks
            $this->generateOccurrences($templateId, $template);
        }

        $this->command->info("Seeded " . count($templates) . " class templates with occurrences");
    }

    /**
     * Generate class occurrences from template RRULE
     */
    private function generateOccurrences(int $templateId, array $template): void
    {
        $now = Carbon::now();
        $endDate = $now->copy()->addWeeks(2);

        // Parse RRULE to get days of week
        if (!$template['weekly_rrule']) {
            return;
        }

        preg_match('/BYDAY=([^;]+)/', $template['weekly_rrule'], $matches);
        if (!isset($matches[1])) {
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

        // Set start times based on class type
        $startTimeMap = [
            'Reggeli Yoga' => '07:00',
            'CrossFit Intenzív' => '18:00',
            'Pilates Core' => '17:30',
            'Erőnléti Kör' => '19:00',
            'Spinning' => '18:30',
            'Gerinctorna' => '16:00',
        ];

        $startTime = $startTimeMap[$template['title']] ?? '18:00';

        $occurrences = [];
        $currentDate = $now->copy()->startOfWeek();

        while ($currentDate->lte($endDate)) {
            foreach ($days as $day) {
                if (!isset($dayMap[$day])) {
                    continue;
                }

                $occurrenceDate = $currentDate->copy()->next($dayMap[$day]);

                if ($occurrenceDate->gte($now) && $occurrenceDate->lte($endDate)) {
                    $startsAt = $occurrenceDate->copy()->setTimeFromTimeString($startTime);
                    $endsAt = $startsAt->copy()->addMinutes($template['duration_min']);

                    $occurrences[] = [
                        'template_id' => $templateId,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'room_id' => $template['room_id'],
                        'trainer_id' => $template['trainer_id'],
                        'capacity' => $template['capacity'],
                        'status' => 'scheduled',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }

            $currentDate->addWeek();
        }

        if (!empty($occurrences)) {
            DB::table('class_occurrences')->insert($occurrences);
            $this->command->info("  → Generated " . count($occurrences) . " occurrences");
        }
    }
}
