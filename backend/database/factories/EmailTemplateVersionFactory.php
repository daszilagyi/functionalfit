<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailTemplateVersion>
 */
class EmailTemplateVersionFactory extends Factory
{
    protected $model = EmailTemplateVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_template_id' => EmailTemplate::factory(),
            'version' => $this->faker->numberBetween(1, 10),
            'subject' => $this->faker->sentence(),
            'html_body' => '<p>' . $this->faker->paragraph() . '</p>',
            'fallback_body' => $this->faker->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
