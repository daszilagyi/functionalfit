<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Event;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('now', '+30 days');
        $duration = fake()->numberBetween(30, 120); // 30 min to 2 hours

        return [
            'type' => 'INDIVIDUAL',
            'status' => 'scheduled',
            'staff_id' => StaffProfile::factory(),
            'client_id' => Client::factory(),
            'room_id' => Room::factory(),
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addMinutes($duration),
            'google_event_id' => fake()->optional()->uuid(),
            'notes' => fake()->optional()->paragraph(),
            'created_by' => null,  // Can be null according to migration
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'INDIVIDUAL',
        ]);
    }

    public function block(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'BLOCK',
            'client_id' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function forDate(Carbon $date): static
    {
        return $this->state(function (array $attributes) use ($date) {
            $startsAt = $date->copy()->setTime(10, 0);
            $endsAt = $startsAt->copy()->addHour();

            return [
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ];
        });
    }

    public function startingAt(Carbon $startsAt, int $durationMinutes = 60): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($durationMinutes),
        ]);
    }

    public function inPast(): static
    {
        return $this->state(function (array $attributes) {
            $startsAt = fake()->dateTimeBetween('-30 days', '-1 day');

            return [
                'starts_at' => $startsAt,
                'ends_at' => Carbon::parse($startsAt)->addHour(),
            ];
        });
    }
}
