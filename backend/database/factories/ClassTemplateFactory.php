<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassTemplate;
use App\Models\Room;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassTemplateFactory extends Factory
{
    protected $model = ClassTemplate::class;

    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'description' => fake()->paragraph(),
            'room_id' => Room::factory(),
            'trainer_id' => StaffProfile::factory(),
            'duration_min' => fake()->randomElement([45, 60, 90]),
            'capacity' => fake()->numberBetween(10, 30),
            'credits_required' => fake()->numberBetween(1, 2),
            'base_price_huf' => fake()->numberBetween(1000, 5000),
            'tags' => fake()->optional()->randomElements(['yoga', 'cardio', 'strength', 'beginner', 'advanced'], 2),
            'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            'status' => 'active',
            'is_public_visible' => true,
        ];
    }

    public function notPublicVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public_visible' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn (array $attributes) => [
            'capacity' => $capacity,
        ]);
    }
}
