<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPriceCode;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPriceCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);

        $clientUser = User::factory()->create(['role' => 'client']);
        $this->client = Client::factory()->create(['user_id' => $clientUser->id]);

        $this->serviceType = ServiceType::factory()->pt()->create();
    }

    public function test_admin_can_list_client_price_codes(): void
    {
        ClientPriceCode::factory()->count(2)->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/clients/{$this->client->id}/price-codes");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'client_id',
                        'service_type_id',
                        'price_code',
                        'entry_fee_brutto',
                        'trainer_fee_brutto',
                        'is_active',
                    ],
                ],
            ]);
    }

    public function test_admin_can_create_client_price_code(): void
    {
        $data = [
            'service_type_id' => $this->serviceType->id,
            'price_code' => 'VIP',
            'entry_fee_brutto' => 8000,
            'trainer_fee_brutto' => 6000,
            'valid_from' => now()->format('Y-m-d'),
            'is_active' => true,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/admin/clients/{$this->client->id}/price-codes", $data);

        $response->assertCreated()
            ->assertJsonPath('data.price_code', 'VIP')
            ->assertJsonPath('data.entry_fee_brutto', 8000);

        $this->assertDatabaseHas('client_price_codes', [
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
            'price_code' => 'VIP',
        ]);
    }

    public function test_admin_can_update_client_price_code(): void
    {
        $priceCode = ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
            'entry_fee_brutto' => 10000,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/client-price-codes/{$priceCode->id}", [
                'entry_fee_brutto' => 9000,
                'price_code' => 'DISCOUNT',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.entry_fee_brutto', 9000)
            ->assertJsonPath('data.price_code', 'DISCOUNT');
    }

    public function test_admin_can_toggle_price_code_active_status(): void
    {
        $priceCode = ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/api/v1/admin/client-price-codes/{$priceCode->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_delete_price_code(): void
    {
        $priceCode = ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/admin/client-price-codes/{$priceCode->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('client_price_codes', ['id' => $priceCode->id]);
    }

    public function test_non_admin_cannot_access_price_codes(): void
    {
        $clientUser = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($clientUser)
            ->getJson("/api/v1/admin/clients/{$this->client->id}/price-codes");

        $response->assertForbidden();
    }
}
