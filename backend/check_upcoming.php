<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ClassOccurrence;
use Carbon\Carbon;

echo "Current time: " . Carbon::now()->toDateTimeString() . "\n";
echo "This week start: " . Carbon::now()->startOfWeek()->toDateTimeString() . "\n\n";

$all = ClassOccurrence::all();
echo "Total occurrences: " . $all->count() . "\n\n";

foreach ($all as $occ) {
    $isPast = $occ->starts_at < Carbon::now();
    $status = $isPast ? "PAST" : "FUTURE";
    echo "[{$status}] {$occ->id}: {$occ->starts_at->toDateTimeString()}\n";
}

$upcoming = ClassOccurrence::where('starts_at', '>', Carbon::now())->get();
echo "\nUpcoming occurrences (starts_at > now): " . $upcoming->count() . "\n";
