<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassRegistrationFactory extends Factory
{
    protected $model = ClassRegistration::class;

    public function definition(): array
    {
        return [
            'occurrence_id' => ClassOccurrence::factory(),
            'client_id' => Client::factory(),
            'status' => 'booked',
            'booked_at' => now(),
            'checked_in_at' => null,
            'credits_used' => 1,
            'payment_status' => 'paid',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'booked',
            'credits_used' => 1,
            'payment_status' => 'paid',
        ]);
    }

    public function waitlist(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'waitlist',
            'credits_used' => 0,
            'payment_status' => 'pending',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'no_show',
        ]);
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'attended',
            'checked_in_at' => now(),
        ]);
    }
}
