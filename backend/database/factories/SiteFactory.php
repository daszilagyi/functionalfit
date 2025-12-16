<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $siteNames = ['SASAD', 'TB', 'ÚJBUDA', 'Test Site'];
        $name = fake()->randomElement($siteNames) . ' ' . fake()->uuid();

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'address' => fake()->streetAddress(),
            'city' => 'Budapest',
            'postal_code' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'description' => fake()->optional()->sentence(),
            'opening_hours' => [
                'monday' => '06:00-22:00',
                'tuesday' => '06:00-22:00',
                'wednesday' => '06:00-22:00',
                'thursday' => '06:00-22:00',
                'friday' => '06:00-22:00',
                'saturday' => '08:00-20:00',
                'sunday' => '08:00-18:00',
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
