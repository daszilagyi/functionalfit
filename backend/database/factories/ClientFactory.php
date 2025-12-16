<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'date_of_joining' => fake()->dateTimeBetween('-2 years', 'now'),
            'notes' => fake()->optional()->paragraph(),
            'gdpr_consent_at' => now(),
        ];
    }

    public function withoutUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    public function withNotes(): static
    {
        return $this->state(fn (array $attributes) => [
            'notes' => fake()->paragraph(),
        ]);
    }
}
