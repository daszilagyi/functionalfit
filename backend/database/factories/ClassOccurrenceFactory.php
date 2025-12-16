<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassOccurrence;
use App\Models\ClassTemplate;
use App\Models\Room;
use App\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassOccurrenceFactory extends Factory
{
    protected $model = ClassOccurrence::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+30 days');
        $duration = 60;

        return [
            'template_id' => ClassTemplate::factory(),
            'room_id' => Room::factory(),
            'trainer_id' => StaffProfile::factory(),
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addMinutes($duration),
            'capacity' => fake()->numberBetween(10, 30),
            'status' => 'scheduled',
            'google_event_id' => fake()->optional()->uuid(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function startingAt(Carbon $startsAt, int $durationMinutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($durationMinutes),
        ]);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }
}
