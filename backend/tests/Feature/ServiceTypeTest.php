<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_list_service_types(): void
    {
        ServiceType::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/service-types');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'default_entry_fee_brutto',
                        'default_trainer_fee_brutto',
                        'is_active',
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_service_type(): void
    {
        $data = [
            'code' => 'PT',
            'name' => 'Personal Training',
            'description' => 'One-on-one personal training sessions',
            'default_entry_fee_brutto' => 10000,
            'default_trainer_fee_brutto' => 7000,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/service-types', $data);

        $response->assertCreated()
            ->assertJsonPath('data.service_type.code', 'PT')
            ->assertJsonPath('data.service_type.name', 'Personal Training');

        $this->assertDatabaseHas('service_types', [
            'code' => 'PT',
            'name' => 'Personal Training',
        ]);
    }

    public function test_admin_can_update_service_type(): void
    {
        $serviceType = ServiceType::factory()->create([
            'code' => 'TEST',
            'name' => 'Test Service',
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/service-types/{$serviceType->id}", [
                'name' => 'Updated Service',
                'default_entry_fee_brutto' => 15000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Service')
            ->assertJsonPath('data.default_entry_fee_brutto', 15000);
    }

    public function test_admin_can_toggle_service_type_active_status(): void
    {
        $serviceType = ServiceType::factory()->create([
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/service-types/{$serviceType->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        // Toggle back
        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/service-types/{$serviceType->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_admin_can_delete_service_type(): void
    {
        $serviceType = ServiceType::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/service-types/{$serviceType->id}");

        $response->assertOk();
        $this->assertSoftDeleted('service_types', ['id' => $serviceType->id]);
    }

    public function test_non_admin_cannot_access_service_types(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)
            ->getJson('/api/v1/admin/service-types');

        $response->assertForbidden();
    }

    public function test_service_type_code_must_be_unique(): void
    {
        ServiceType::factory()->create(['code' => 'PT']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/service-types', [
                'code' => 'PT',
                'name' => 'Another PT',
                'default_entry_fee_brutto' => 10000,
                'default_trainer_fee_brutto' => 7000,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_service_type_requires_valid_code_format(): void
    {
        // Code must be alpha_dash (letters, numbers, dashes, underscores)
        // Invalid characters like spaces or special chars should fail
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/admin/service-types', [
                'code' => 'invalid code!', // Contains space and special char
                'name' => 'Test Service',
                'default_entry_fee_brutto' => 10000,
                'default_trainer_fee_brutto' => 7000,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }
}
