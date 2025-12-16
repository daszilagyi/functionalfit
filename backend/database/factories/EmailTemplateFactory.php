<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'subject' => fake()->sentence(),
            'html_body' => '<html><body><p>Hello {{user.name}},</p><p>' . fake()->paragraph() . '</p></body></html>',
            'fallback_body' => 'Hello {{user.name}}, ' . fake()->paragraph(),
            'version' => 1,
            'is_active' => true,
            'updated_by' => null,
        ];
    }

    /**
     * Set a specific slug.
     */
    public function slug(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => $slug,
        ]);
    }

    /**
     * Mark template as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific version.
     */
    public function version(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => $version,
        ]);
    }

    /**
     * Create a registration confirmation template.
     */
    public function registrationConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'registration_confirmation',
            'subject' => 'Registration Confirmed - {{company_name}}',
            'html_body' => '<html><body><p>Welcome {{user.name}}!</p><p>Please confirm your email: {{confirm_url}}</p></body></html>',
            'fallback_body' => 'Welcome {{user.name}}! Please confirm your email: {{confirm_url}}',
        ]);
    }

    /**
     * Create a password reset template.
     */
    public function passwordReset(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'password_reset',
            'subject' => 'Password Reset - {{company_name}}',
            'html_body' => '<html><body><p>Hello {{user.name}},</p><p>Reset your password: {{password_reset_url}}</p></body></html>',
            'fallback_body' => 'Hello {{user.name}}, Reset your password: {{password_reset_url}}',
        ]);
    }

    /**
     * Create a booking confirmation template.
     */
    public function bookingConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'slug' => 'booking_confirmation',
            'subject' => 'Booking Confirmed: {{class.title}} - {{company_name}}',
            'html_body' => '<html><body><p>Hello {{user.name}},</p><p>Your booking for {{class.title}} on {{class.starts_at}} is confirmed.</p></body></html>',
            'fallback_body' => 'Hello {{user.name}}, Your booking for {{class.title}} on {{class.starts_at}} is confirmed.',
        ]);
    }
}
