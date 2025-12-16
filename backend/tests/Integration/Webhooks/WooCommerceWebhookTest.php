<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Pass;
use App\Models\User;
use Carbon\Carbon;
use Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

describe('WooCommerce Webhook Integration', function () {
    beforeEach(function () {
        // Set up WooCommerce webhook secret
        config(['services.woocommerce.webhook_secret' => 'test_woocommerce_secret']);

        // Create a test client
        $this->user = User::factory()->create([
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
        ]);
        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
        ]);
    });

    it('successfully processes valid order paid webhook', function () {
        // Arrange: Load WooCommerce order paid fixture
        $payload = $this->loadFixture('Webhooks/woocommerce_order_paid.json');
        $secret = config('services.woocommerce.webhook_secret');
        $signature = $this->signWooCommercePayload($payload, $secret);

        // Act: Send webhook request
        $response = $this->postJson('/api/v1/webhooks/woocommerce', $payload, [
            'X-WC-Webhook-Signature' => $signature,
        ]);

        // Assert: Response is successful
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order processed successfully',
            ]);

        // Assert: Pass is created with correct data
        $this->assertDatabaseHas('passes', [
            'client_id' => $this->client->id,
            'pass_type' => '10 Credit Pass',
            'credits_total' => 10,
            'credits_remaining' => 10,
            'price' => 79.00,
            'status' => 'active',
            'external_reference' => 'woocommerce_order_12345',
        ]);

        // Assert: Pass expires in 6 months
        $pass = Pass::where('client_id', $this->client->id)->first();
        expect($pass->expires_at->diffInDays(now()))->toBeGreaterThanOrEqual(180);
    });

    it('handles idempotency by preventing duplicate pass creation', function () {
        // Arrange: Create existing pass with same order ID
        $payload = $this->loadFixture('Webhooks/woocommerce_order_paid.json');
        $secret = config('services.woocommerce.webhook_secret');
        $signature = $this->signWooCommercePayload($payload, $secret);

        Pass::factory()->create([
            'client_id' => $this->client->id,
            'external_reference' => 'woocommerce_order_12345',
            'pass_type' => '10 Credit Pass',
            'credits_total' => 10,
            'credits_remaining' => 10,
        ]);

        // Act: Send same webhook again
        $response = $this->postJson('/api/v1/webhooks/woocommerce', $payload, [
            'X-WC-Webhook-Signature' => $signature,
        ]);

        // Assert: Response acknowledges duplicate
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'already_processed',
                ],
            ]);

        // Assert: Still only one pass exists
        $passCount = Pass::where('client_id', $this->client->id)
            ->where('external_reference', 'woocommerce_order_12345')
            ->count();
        expect($passCount)->toBe(1);
    });

    it('rejects webhook with invalid signature', function () {
        // Arrange: Load payload but use wrong signature
        $payload = $this->loadFixture('Webhooks/woocommerce_order_paid.json');
        $invalidSignature = base64_encode('invalid_signature_hash');

        // Act: Send webhook with invalid signature
        $response = $this->postJson('/api/v1/webhooks/woocommerce', $payload, [
            'X-WC-Webhook-Signature' => $invalidSignature,
        ]);

        // Assert: Unauthorized response
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid signature',
            ]);

        // Assert: No pass was created
        $this->assertDatabaseCount('passes', 0);
    });

    it('handles unknown client email gracefully', function () {
        // Arrange: Create payload with unknown email
        $payload = $this->loadFixture('Webhooks/woocommerce_order_paid.json');
        $payload['billing']['email'] = 'unknown.user@example.com';
        $secret = config('services.woocommerce.webhook_secret');
        $signature = $this->signWooCommercePayload($payload, $secret);

        // Act: Send webhook
        $response = $this->postJson('/api/v1/webhooks/woocommerce', $payload, [
            'X-WC-Webhook-Signature' => $signature,
        ]);

        // Assert: Returns 404 but doesn't crash
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Client not found',
            ]);

        // Assert: No pass created
        $this->assertDatabaseCount('passes', 0);
    });

    it('validates required fields and returns 422 for missing data', function () {
        // Arrange: Create payload missing order ID
        $payload = $this->loadFixture('Webhooks/woocommerce_order_paid.json');
        unset($payload['id']); // Remove required field
        $secret = config('services.woocommerce.webhook_secret');
        $signature = $this->signWooCommercePayload($payload, $secret);

        // Act: Send webhook
        $response = $this->postJson('/api/v1/webhooks/woocommerce', $payload, [
            'X-WC-Webhook-Signature' => $signature,
        ]);

        // Assert: Validation error
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Missing order ID',
            ]);

        // Assert: No pass created
        $this->assertDatabaseCount('passes', 0);
    });

    it('gracefully ignores unsupported event types', function () {
        // Arrange: Create payload for order.cancelled event
        $payload = [
            'id' => 99999,
            'order_key' => 'wc_order_cancelled',
            'status' => 'cancelled',
            'billing' => [
                'email' => 'john.doe@example.com',
            ],
            'line_items' => [],
        ];
        $secret = config('services.woocommerce.webhook_secret');
        $signature = $this->signWooCommercePayload($payload, $secret);

        // Act: Send webhook
        $response = $this->postJson('/api/v1/webhooks/woocommerce', $payload, [
            'X-WC-Webhook-Signature' => $signature,
        ]);

        // Assert: Returns 200 but no processing
        $response->assertStatus(200);

        // Assert: No pass created
        $this->assertDatabaseCount('passes', 0);
    });

    it('extracts credits correctly from product name variations', function () {
        // Arrange: Test different product name formats
        $testCases = [
            ['name' => '10 Credit Pass', 'expected_credits' => 10],
            ['name' => '20 Credits Training Package', 'expected_credits' => 20],
            ['name' => '5 Kredit Bérlet', 'expected_credits' => 5],
            ['name' => 'Single Session Pass', 'expected_credits' => 0], // No credit number
        ];

        foreach ($testCases as $testCase) {
            // Arrange: Create payload with specific product name
            $payload = $this->loadFixture('Webhooks/woocommerce_order_paid.json');
            $payload['id'] = rand(10000, 99999); // Unique order ID
            $payload['order_key'] = 'wc_order_' . uniqid();
            $payload['line_items'][0]['name'] = $testCase['name'];

            $secret = config('services.woocommerce.webhook_secret');
            $signature = $this->signWooCommercePayload($payload, $secret);

            // Act: Send webhook
            $response = $this->postJson('/api/v1/webhooks/woocommerce', $payload, [
                'X-WC-Webhook-Signature' => $signature,
            ]);

            // Assert: Response is successful
            $response->assertStatus(200);

            // Skip verification if no credits expected (product without credits)
            if ($testCase['expected_credits'] === 0) {
                continue;
            }

            // Assert: Pass created with correct credits
            $this->assertDatabaseHas('passes', [
                'client_id' => $this->client->id,
                'pass_type' => $testCase['name'],
                'credits_total' => $testCase['expected_credits'],
                'credits_remaining' => $testCase['expected_credits'],
            ]);
        }
    });
});
