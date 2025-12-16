<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Client;
use App\Models\Room;
use App\Models\Event;
use Carbon\Carbon;

// Find admin user (staff member)
$admin = User::where('email', 'admin@functionalfit.hu')->first();
if (!$admin) {
    echo "Admin user not found!\n";
    exit(1);
}

// Create or find a room with valid site
$room = Room::firstOrCreate(
    ['name' => 'Főterem', 'site' => 'SASAD'],
    [
        'capacity' => 20,
    ]
);

// Create or find a test client user
$clientUser = User::firstOrCreate(
    ['email' => 'test@client.hu'],
    [
        'name' => 'Teszt Kliens',
        'password' => bcrypt('password123'),
        'role' => 'client',
    ]
);

// Create client record
$client = Client::firstOrCreate(
    ['user_id' => $clientUser->id],
    [
        'full_name' => 'Teszt Kliens',
        'date_of_joining' => now()->subMonths(3),
    ]
);

// Delete old test events
Event::where('staff_id', $admin->id)->delete();

// Create test events for the current week
$now = Carbon::now('Europe/Budapest');
$events = [];

// Monday 10:00-11:00
$events[] = Event::create([
    'staff_id' => $admin->id,
    'client_id' => $client->id,
    'room_id' => $room->id,
    'start_time' => $now->copy()->startOfWeek()->setTime(10, 0),
    'end_time' => $now->copy()->startOfWeek()->setTime(11, 0),
    'title' => 'Személyi edzés - Teszt Kliens',
    'type' => 'personal_training',
    'status' => 'confirmed',
]);

// Tuesday 14:00-15:00
$events[] = Event::create([
    'staff_id' => $admin->id,
    'client_id' => $client->id,
    'room_id' => $room->id,
    'start_time' => $now->copy()->startOfWeek()->addDays(1)->setTime(14, 0),
    'end_time' => $now->copy()->startOfWeek()->addDays(1)->setTime(15, 0),
    'title' => 'Konzultáció - Teszt Kliens',
    'type' => 'consultation',
    'status' => 'confirmed',
]);

// Wednesday 9:00-10:30
$events[] = Event::create([
    'staff_id' => $admin->id,
    'client_id' => $client->id,
    'room_id' => $room->id,
    'start_time' => $now->copy()->startOfWeek()->addDays(2)->setTime(9, 0),
    'end_time' => $now->copy()->startOfWeek()->addDays(2)->setTime(10, 30),
    'title' => 'Funkcionális edzés - Teszt Kliens',
    'type' => 'personal_training',
    'status' => 'confirmed',
]);

// Thursday 16:00-17:00
$events[] = Event::create([
    'staff_id' => $admin->id,
    'client_id' => $client->id,
    'room_id' => $room->id,
    'start_time' => $now->copy()->startOfWeek()->addDays(3)->setTime(16, 0),
    'end_time' => $now->copy()->startOfWeek()->addDays(3)->setTime(17, 0),
    'title' => 'Személyi edzés - Teszt Kliens',
    'type' => 'personal_training',
    'status' => 'confirmed',
]);

// Friday 11:00-12:00
$events[] = Event::create([
    'staff_id' => $admin->id,
    'client_id' => $client->id,
    'room_id' => $room->id,
    'start_time' => $now->copy()->startOfWeek()->addDays(4)->setTime(11, 0),
    'end_time' => $now->copy()->startOfWeek()->addDays(4)->setTime(12, 0),
    'title' => 'Rehabilitáció - Teszt Kliens',
    'type' => 'consultation',
    'status' => 'confirmed',
]);

echo "Created " . count($events) . " test events successfully!\n";
echo "Room: {$room->name} ({$room->site})\n";
echo "Client: {$client->full_name}\n";
echo "Staff: {$admin->name}\n\n";

foreach ($events as $event) {
    echo "- {$event->start_time->format('Y-m-d H:i')} - {$event->end_time->format('H:i')}: {$event->title}\n";
}
