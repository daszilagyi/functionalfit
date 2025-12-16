<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientPriceCode;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPriceCode>
 */
class ClientPriceCodeFactory extends Factory
{
    protected $model = ClientPriceCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'client_email' => $this->faker->email(),
            'service_type_id' => ServiceType::factory(),
            'price_code' => $this->faker->optional()->randomElement(['VIP', 'STANDARD', 'DISCOUNT']),
            'entry_fee_brutto' => $this->faker->numberBetween(5000, 20000),
            'trainer_fee_brutto' => $this->faker->numberBetween(3000, 15000),
            'currency' => 'HUF',
            'valid_from' => now(),
            'valid_until' => null,
            'is_active' => true,
            'created_by' => User::factory()->create(['role' => 'admin'])->id,
        ];
    }

    /**
     * Indicate that the price code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific valid period.
     */
    public function validFor(string $from, ?string $until = null): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => $from,
            'valid_until' => $until,
        ]);
    }

    /**
     * Indicate that this is a VIP price code.
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_code' => 'VIP',
            'entry_fee_brutto' => 8000,
            'trainer_fee_brutto' => 5000,
        ]);
    }

    /**
     * Indicate that this is a discount price code.
     */
    public function discount(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_code' => 'DISCOUNT',
            'entry_fee_brutto' => 6000,
            'trainer_fee_brutto' => 4000,
        ]);
    }
}
