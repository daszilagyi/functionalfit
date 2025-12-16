<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoomSeeder extends Seeder
{
    /**
     * Seed the rooms table with actual FunctionalFit locations.
     *
     * Sites:
     * - SASAD: Gym, Masszázs (Massage), Rehab
     * - TB (Tatabánya): Gym, Nagyterem (Main Hall), Terem 1, Terem 2, Terem 3
     * - ÚJBUDA: Gym, Masszázs, Terem I, Terem II, Terem III, Terem IV
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Get site IDs
        $sites = DB::table('sites')->pluck('id', 'name');

        $rooms = [
            // SASAD Location
            [
                'site' => 'SASAD',  // Legacy field for SQLite compatibility
                'site_id' => $sites['SASAD'],
                'name' => 'Gym',
                'google_calendar_id' => null, // To be configured
                'color' => '#3788D8',
                'capacity' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'SASAD',
                'site_id' => $sites['SASAD'],
                'name' => 'Masszázs',
                'google_calendar_id' => null,
                'color' => '#9C27B0',
                'capacity' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'SASAD',
                'site_id' => $sites['SASAD'],
                'name' => 'Rehab',
                'google_calendar_id' => null,
                'color' => '#4CAF50',
                'capacity' => 8,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // TB (Tatabánya) Location
            [
                'site' => 'TB',
                'site_id' => $sites['TB'],
                'name' => 'Gym',
                'google_calendar_id' => null,
                'color' => '#FF9800',
                'capacity' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'TB',
                'site_id' => $sites['TB'],
                'name' => 'Nagyterem',
                'google_calendar_id' => null,
                'color' => '#F44336',
                'capacity' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'TB',
                'site_id' => $sites['TB'],
                'name' => 'Terem 1',
                'google_calendar_id' => null,
                'color' => '#2196F3',
                'capacity' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'TB',
                'site_id' => $sites['TB'],
                'name' => 'Terem 2',
                'google_calendar_id' => null,
                'color' => '#00BCD4',
                'capacity' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'TB',
                'site_id' => $sites['TB'],
                'name' => 'Terem 3',
                'google_calendar_id' => null,
                'color' => '#009688',
                'capacity' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // ÚJBUDA Location
            [
                'site' => 'ÚJBUDA',
                'site_id' => $sites['ÚJBUDA'],
                'name' => 'Gym',
                'google_calendar_id' => null,
                'color' => '#673AB7',
                'capacity' => 18,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'ÚJBUDA',
                'site_id' => $sites['ÚJBUDA'],
                'name' => 'Masszázs',
                'google_calendar_id' => null,
                'color' => '#E91E63',
                'capacity' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'ÚJBUDA',
                'site_id' => $sites['ÚJBUDA'],
                'name' => 'Terem I',
                'google_calendar_id' => null,
                'color' => '#795548',
                'capacity' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'ÚJBUDA',
                'site_id' => $sites['ÚJBUDA'],
                'name' => 'Terem II',
                'google_calendar_id' => null,
                'color' => '#607D8B',
                'capacity' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'ÚJBUDA',
                'site_id' => $sites['ÚJBUDA'],
                'name' => 'Terem III',
                'google_calendar_id' => null,
                'color' => '#FFC107',
                'capacity' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'site' => 'ÚJBUDA',
                'site_id' => $sites['ÚJBUDA'],
                'name' => 'Terem IV',
                'google_calendar_id' => null,
                'color' => '#CDDC39',
                'capacity' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('rooms')->insert($rooms);

        $this->command->info('Seeded ' . count($rooms) . ' rooms across 3 sites (SASAD, TB, ÚJBUDA)');
    }
}
