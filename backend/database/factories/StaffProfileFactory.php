<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bio' => fake()->paragraph(),
            'skills' => ['massage', 'personal_training', 'yoga'],
            'default_site' => fake()->randomElement(['SASAD', 'TB', 'ÚJBUDA']),
            'visibility' => true,
        ];
    }

    public function invisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => false,
        ]);
    }

    public function withSkills(array $skills): static
    {
        return $this->state(fn (array $attributes) => [
            'skills' => $skills,
        ]);
    }
}
