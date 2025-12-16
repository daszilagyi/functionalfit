<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    protected $model = ServiceType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $codes = ['PT', 'GYOGYTORNA', 'MASSZAZS', 'REHAB', 'YOGA', 'PILATES'];

        return [
            'code' => $this->faker->unique()->randomElement($codes) . '_' . strtoupper($this->faker->lexify('???')),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'default_entry_fee_brutto' => $this->faker->numberBetween(5000, 20000),
            'default_trainer_fee_brutto' => $this->faker->numberBetween(3000, 15000),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the service type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a PT (Personal Training) service type.
     */
    public function pt(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'PT',
            'name' => 'Personal Training',
            'description' => 'One-on-one personal training sessions',
            'default_entry_fee_brutto' => 10000,
            'default_trainer_fee_brutto' => 7000,
        ]);
    }

    /**
     * Create a GYOGYTORNA (Physiotherapy) service type.
     */
    public function gyogytorna(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'GYOGYTORNA',
            'name' => 'Gyógytorna',
            'description' => 'Gyógytorna kezelések',
            'default_entry_fee_brutto' => 12000,
            'default_trainer_fee_brutto' => 8000,
        ]);
    }

    /**
     * Create a MASSZAZS (Massage) service type.
     */
    public function masszazs(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'MASSZAZS',
            'name' => 'Masszázs',
            'description' => 'Masszázs kezelések',
            'default_entry_fee_brutto' => 8000,
            'default_trainer_fee_brutto' => 5000,
        ]);
    }
}
