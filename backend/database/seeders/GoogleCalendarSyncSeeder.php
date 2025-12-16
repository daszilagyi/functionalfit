<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GoogleCalendarSyncConfig;
use App\Models\Room;
use Illuminate\Database\Seeder;

class GoogleCalendarSyncSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::all();

        if ($rooms->isEmpty()) {
            $this->command->warn('No rooms found. Please seed rooms first.');
            return;
        }

        // Example configurations for demonstration
        GoogleCalendarSyncConfig::create([
            'name' => 'Főcsarnok - Primary Calendar',
            'google_calendar_id' => 'primary',
            'room_id' => $rooms->first()->id,
            'sync_enabled' => true,
            'sync_direction' => 'both',
            'sync_options' => [
                'auto_resolve_conflicts' => false,
                'import_cancelled_events' => false,
            ],
        ]);

        if ($rooms->count() > 1) {
            GoogleCalendarSyncConfig::create([
                'name' => 'Kis Terem - Export Only',
                'google_calendar_id' => 'secondary@example.com',
                'room_id' => $rooms->skip(1)->first()->id,
                'sync_enabled' => true,
                'sync_direction' => 'export',
                'sync_options' => [
                    'overwrite_existing' => false,
                ],
            ]);
        }

        $this->command->info('Google Calendar sync configurations seeded successfully.');
    }
}
