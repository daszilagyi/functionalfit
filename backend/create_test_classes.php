<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\ClassTemplate;
use App\Models\ClassOccurrence;
use Carbon\Carbon;

// Get staff user
$staffUser = User::where('email', 'staff@example.com')->first();
if (!$staffUser || !$staffUser->staffProfile) {
    die("Staff user not found or has no staff profile\n");
}
$staffId = $staffUser->staffProfile->id;
echo "Using staff profile ID: {$staffId}\n";

// Create Rooms if they don't exist
$rooms = [
    ['name' => 'Gym', 'capacity' => 20, 'color' => '#3b82f6'],
    ['name' => 'Masszázs', 'capacity' => 2, 'color' => '#10b981'],
    ['name' => 'Rehab', 'capacity' => 10, 'color' => '#f59e0b'],
];

echo "\nCreating rooms...\n";
foreach ($rooms as $roomData) {
    $room = Room::firstOrCreate(
        ['name' => $roomData['name']],
        $roomData
    );
    echo "  Room: {$room->name} (ID: {$room->id})\n";
}

// Get room IDs
$gymRoom = Room::where('name', 'Gym')->first();
$massageRoom = Room::where('name', 'Masszázs')->first();
$rehabRoom = Room::where('name', 'Rehab')->first();

// Create ClassTemplates
$templates = [
    [
        'title' => 'Yoga',
        'description' => 'Relaxing yoga session for all levels',
        'trainer_id' => $staffId,
        'room_id' => $gymRoom->id,
        'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE',
        'duration_min' => 60,
        'capacity' => 15,
        'tags' => ['relaxation', 'flexibility'],
    ],
    [
        'title' => 'Spinning',
        'description' => 'High-intensity cycling workout',
        'trainer_id' => $staffId,
        'room_id' => $gymRoom->id,
        'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,TH',
        'duration_min' => 45,
        'capacity' => 20,
        'tags' => ['cardio', 'intensity'],
    ],
    [
        'title' => 'Pilates',
        'description' => 'Core strengthening and flexibility',
        'trainer_id' => $staffId,
        'room_id' => $rehabRoom->id,
        'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=TU',
        'duration_min' => 50,
        'capacity' => 10,
        'tags' => ['core', 'flexibility'],
    ],
];

echo "\nCreating class templates...\n";
foreach ($templates as $templateData) {
    $template = ClassTemplate::firstOrCreate(
        ['title' => $templateData['title']],
        $templateData
    );
    echo "  Template: {$template->title} (ID: {$template->id})\n";
}

// Delete old occurrences
ClassOccurrence::truncate();
echo "\nDeleted old occurrences.\n";

// Create ClassOccurrences for NEXT week (future dates)
$nextWeek = Carbon::now()->addWeek()->startOfWeek();

$occurrences = [
    // Yoga Monday 9:00
    [
        'template_id' => ClassTemplate::where('title', 'Yoga')->first()->id,
        'trainer_id' => $staffId,
        'room_id' => $gymRoom->id,
        'starts_at' => $nextWeek->copy()->addDays(0)->setTime(9, 0),
        'ends_at' => $nextWeek->copy()->addDays(0)->setTime(10, 0),
        'capacity' => 15,
        'status' => 'scheduled',
    ],
    // Spinning Monday 18:00
    [
        'template_id' => ClassTemplate::where('title', 'Spinning')->first()->id,
        'trainer_id' => $staffId,
        'room_id' => $gymRoom->id,
        'starts_at' => $nextWeek->copy()->addDays(0)->setTime(18, 0),
        'ends_at' => $nextWeek->copy()->addDays(0)->setTime(18, 45),
        'capacity' => 20,
        'status' => 'scheduled',
    ],
    // Pilates Tuesday 10:00
    [
        'template_id' => ClassTemplate::where('title', 'Pilates')->first()->id,
        'trainer_id' => $staffId,
        'room_id' => $rehabRoom->id,
        'starts_at' => $nextWeek->copy()->addDays(1)->setTime(10, 0),
        'ends_at' => $nextWeek->copy()->addDays(1)->setTime(10, 50),
        'capacity' => 10,
        'status' => 'scheduled',
    ],
    // Yoga Wednesday 9:00
    [
        'template_id' => ClassTemplate::where('title', 'Yoga')->first()->id,
        'trainer_id' => $staffId,
        'room_id' => $gymRoom->id,
        'starts_at' => $nextWeek->copy()->addDays(2)->setTime(9, 0),
        'ends_at' => $nextWeek->copy()->addDays(2)->setTime(10, 0),
        'capacity' => 15,
        'status' => 'scheduled',
    ],
    // Spinning Thursday 18:00
    [
        'template_id' => ClassTemplate::where('title', 'Spinning')->first()->id,
        'trainer_id' => $staffId,
        'room_id' => $gymRoom->id,
        'starts_at' => $nextWeek->copy()->addDays(3)->setTime(18, 0),
        'ends_at' => $nextWeek->copy()->addDays(3)->setTime(18, 45),
        'capacity' => 20,
        'status' => 'scheduled',
    ],
    // Pilates Friday 10:00
    [
        'template_id' => ClassTemplate::where('title', 'Pilates')->first()->id,
        'trainer_id' => $staffId,
        'room_id' => $rehabRoom->id,
        'starts_at' => $nextWeek->copy()->addDays(4)->setTime(10, 0),
        'ends_at' => $nextWeek->copy()->addDays(4)->setTime(10, 50),
        'capacity' => 10,
        'status' => 'scheduled',
    ],
];

echo "\nCreating class occurrences...\n";
foreach ($occurrences as $occurrenceData) {
    $occurrence = ClassOccurrence::create($occurrenceData);
    $template = ClassTemplate::find($occurrence->template_id);
    echo "  {$template->title} - {$occurrence->starts_at->format('l H:i')} (ID: {$occurrence->id})\n";
}

echo "\n✅ All test classes created successfully!\n";
