<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Pass;
use Illuminate\Database\Eloquent\Factories\Factory;

class PassFactory extends Factory
{
    protected $model = Pass::class;

    public function definition(): array
    {
        $totalCredits = fake()->randomElement([5, 10, 20]);

        return [
            'client_id' => Client::factory(),
            'type' => fake()->randomElement(['5_session', '10_session', '20_session', 'monthly_unlimited']),
            'total_credits' => $totalCredits,
            'credits_left' => $totalCredits,
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->addDays(30),
            'status' => 'active',
            'source' => 'woocommerce',
            'external_order_id' => fake()->optional()->numerify('WC-####'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->addDays(30),
            'credits_left' => $attributes['total_credits'] ?? 10,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'valid_until' => now()->subDays(1),
        ]);
    }

    public function usedUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'depleted',
            'credits_left' => 0,
        ]);
    }

    public function depleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'depleted',
            'credits_left' => 0,
        ]);
    }

    public function withCredits(int $total, int $remaining): static
    {
        return $this->state(fn (array $attributes) => [
            'total_credits' => $total,
            'credits_left' => $remaining,
        ]);
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => now()->addDays($days),
        ]);
    }
}
