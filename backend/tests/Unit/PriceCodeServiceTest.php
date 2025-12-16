<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Client;
use App\Models\ClientPriceCode;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\PriceCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private PriceCodeService $service;
    private ServiceType $serviceType;
    private Client $client;
    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PriceCodeService::class);

        $this->serviceType = ServiceType::factory()->pt()->create();

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'test@example.com',
        ]);
        $this->client = Client::factory()->create([
            'user_id' => $this->clientUser->id,
        ]);
    }

    public function test_resolves_client_specific_price_code(): void
    {
        ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
            'client_email' => $this->clientUser->email,
            'service_type_id' => $this->serviceType->id,
            'entry_fee_brutto' => 8000,
            'trainer_fee_brutto' => 5000,
            'price_code' => 'VIP',
            'is_active' => true,
            'valid_from' => now()->subDay(),
        ]);

        $result = $this->service->resolveByClientAndServiceType(
            $this->client->id,
            $this->serviceType->id
        );

        $this->assertEquals(8000, $result['entry_fee_brutto']);
        $this->assertEquals(5000, $result['trainer_fee_brutto']);
        $this->assertEquals('client_price_code', $result['source']);
        $this->assertEquals('VIP', $result['price_code']);
    }

    public function test_falls_back_to_service_type_default_when_no_client_price_code(): void
    {
        $result = $this->service->resolveByClientAndServiceType(
            $this->client->id,
            $this->serviceType->id
        );

        $this->assertEquals($this->serviceType->default_entry_fee_brutto, $result['entry_fee_brutto']);
        $this->assertEquals($this->serviceType->default_trainer_fee_brutto, $result['trainer_fee_brutto']);
        $this->assertEquals('service_type_default', $result['source']);
    }

    public function test_resolves_by_email(): void
    {
        ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
            'client_email' => $this->clientUser->email,
            'service_type_id' => $this->serviceType->id,
            'entry_fee_brutto' => 9000,
            'trainer_fee_brutto' => 6000,
            'is_active' => true,
            'valid_from' => now()->subDay(),
        ]);

        $result = $this->service->resolveByEmailAndServiceType(
            $this->clientUser->email,
            $this->serviceType->code
        );

        $this->assertEquals(9000, $result['entry_fee_brutto']);
        $this->assertEquals(6000, $result['trainer_fee_brutto']);
        $this->assertEquals('client_price_code', $result['source']);
    }

    public function test_ignores_inactive_price_codes(): void
    {
        ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
            'client_email' => $this->clientUser->email,
            'service_type_id' => $this->serviceType->id,
            'entry_fee_brutto' => 8000,
            'trainer_fee_brutto' => 5000,
            'is_active' => false,
            'valid_from' => now()->subDay(),
        ]);

        $result = $this->service->resolveByClientAndServiceType(
            $this->client->id,
            $this->serviceType->id
        );

        $this->assertEquals('service_type_default', $result['source']);
    }

    public function test_ignores_future_price_codes(): void
    {
        ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
            'client_email' => $this->clientUser->email,
            'service_type_id' => $this->serviceType->id,
            'entry_fee_brutto' => 8000,
            'trainer_fee_brutto' => 5000,
            'is_active' => true,
            'valid_from' => now()->addWeek(), // Future date
        ]);

        $result = $this->service->resolveByClientAndServiceType(
            $this->client->id,
            $this->serviceType->id
        );

        $this->assertEquals('service_type_default', $result['source']);
    }

    public function test_ignores_expired_price_codes(): void
    {
        ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
            'client_email' => $this->clientUser->email,
            'service_type_id' => $this->serviceType->id,
            'entry_fee_brutto' => 8000,
            'trainer_fee_brutto' => 5000,
            'is_active' => true,
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->subDay(), // Expired
        ]);

        $result = $this->service->resolveByClientAndServiceType(
            $this->client->id,
            $this->serviceType->id
        );

        $this->assertEquals('service_type_default', $result['source']);
    }

    public function test_generates_default_price_codes_for_new_client(): void
    {
        // Create additional service types (one already exists from setUp)
        $gyogytorna = ServiceType::factory()->gyogytorna()->create();

        $this->service->generateDefaultPriceCodes($this->client);

        // Should create price codes for active service types
        $this->assertDatabaseHas('client_price_codes', [
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $this->assertDatabaseHas('client_price_codes', [
            'client_id' => $this->client->id,
            'service_type_id' => $gyogytorna->id,
        ]);
    }

    public function test_generates_price_codes_for_new_service_type(): void
    {
        // Create another client
        $clientUser2 = User::factory()->create(['role' => 'client', 'email' => 'test2@example.com']);
        $client2 = Client::factory()->create(['user_id' => $clientUser2->id]);

        // Create a new service type and generate price codes for all clients
        $newServiceType = ServiceType::factory()->masszazs()->create();

        $count = $this->service->generatePriceCodesForNewServiceType($newServiceType);

        // Should create price codes for all clients
        $this->assertGreaterThanOrEqual(2, $count);

        $this->assertDatabaseHas('client_price_codes', [
            'client_id' => $this->client->id,
            'service_type_id' => $newServiceType->id,
        ]);

        $this->assertDatabaseHas('client_price_codes', [
            'client_id' => $client2->id,
            'service_type_id' => $newServiceType->id,
        ]);
    }

    public function test_updates_client_email_in_price_codes(): void
    {
        ClientPriceCode::factory()->create([
            'client_id' => $this->client->id,
            'client_email' => 'old@example.com',
            'service_type_id' => $this->serviceType->id,
        ]);

        $this->service->updateClientEmail($this->client, 'new@example.com');

        $this->assertDatabaseHas('client_price_codes', [
            'client_id' => $this->client->id,
            'client_email' => 'new@example.com',
        ]);
    }
}
