<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $roomNames = ['Gym', 'Main Hall', 'Massage Room', 'Rehab Room', 'Room I', 'Room II'];
        $sites = ['SASAD', 'TB', 'ÚJBUDA'];

        return [
            'site_id' => \App\Models\Site::factory(),
            'site' => fake()->randomElement($sites), // Keep for backward compatibility (SQLite doesn't drop columns)
            'name' => fake()->randomElement($roomNames),
            'google_calendar_id' => fake()->optional()->uuid(),
            'color' => fake()->hexColor(),
            'capacity' => fake()->optional()->numberBetween(5, 30),
        ];
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }

    public function forSite(int $siteId): static
    {
        return $this->state(fn (array $attributes) => [
            'site_id' => $siteId,
        ]);
    }
}
