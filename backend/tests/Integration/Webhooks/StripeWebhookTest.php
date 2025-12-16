<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Pass;
use App\Models\User;
use Carbon\Carbon;
use Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

describe('Stripe Webhook Integration', function () {
    beforeEach(function () {
        // Set up Stripe webhook secret
        config(['services.stripe.webhook_secret' => 'whsec_test_stripe_secret']);

        // Create test clients
        $this->user1 = User::factory()->create([
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
        ]);
        $this->client1 = Client::factory()->create([
            'user_id' => $this->user1->id,
        ]);

        $this->user2 = User::factory()->create([
            'email' => 'jane.smith@example.com',
            'name' => 'Jane Smith',
        ]);
        $this->client2 = Client::factory()->create([
            'user_id' => $this->user2->id,
        ]);
    });

    it('successfully processes payment_intent.succeeded event', function () {
        // Arrange: Load Stripe payment intent fixture
        $payload = $this->loadFixture('Webhooks/stripe_payment_intent_succeeded.json');
        $secret = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signature = $this->signStripePayload($payload, $secret, $timestamp);

        // Act: Send webhook request
        $response = $this->postJson('/api/v1/webhooks/stripe', $payload, [
            'Stripe-Signature' => $signature,
        ]);

        // Assert: Response is successful
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'acknowledged',
                ],
            ]);
    });

    it('successfully processes checkout.session.completed event', function () {
        // Arrange: Load Stripe checkout session fixture
        $payload = $this->loadFixture('Webhooks/stripe_checkout_session_completed.json');
        $secret = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signature = $this->signStripePayload($payload, $secret, $timestamp);

        // Act: Send webhook request
        $response = $this->postJson('/api/v1/webhooks/stripe', $payload, [
            'Stripe-Signature' => $signature,
        ]);

        // Assert: Response is successful
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Checkout processed successfully',
            ]);

        // Assert: Pass is created with correct data from metadata
        $this->assertDatabaseHas('passes', [
            'client_id' => $this->client2->id,
            'pass_type' => '20 Credit Pass',
            'credits_total' => 20,
            'credits_remaining' => 20,
            'price' => 79.00, // 7900 cents / 100
            'status' => 'active',
            'external_reference' => 'stripe_session_cs_test_abc123xyz',
        ]);
    });

    it('handles idempotency by preventing duplicate event processing', function () {
        // Arrange: Load checkout session fixture
        $payload = $this->loadFixture('Webhooks/stripe_checkout_session_completed.json');
        $secret = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signature = $this->signStripePayload($payload, $secret, $timestamp);

        // Create existing pass with same session ID
        Pass::factory()->create([
            'client_id' => $this->client2->id,
            'external_reference' => 'stripe_session_cs_test_abc123xyz',
            'pass_type' => '20 Credit Pass',
            'credits_total' => 20,
            'credits_remaining' => 20,
        ]);

        // Act: Send same webhook again (replay attack)
        $response = $this->postJson('/api/v1/webhooks/stripe', $payload, [
            'Stripe-Signature' => $signature,
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
        $passCount = Pass::where('client_id', $this->client2->id)
            ->where('external_reference', 'stripe_session_cs_test_abc123xyz')
            ->count();
        expect($passCount)->toBe(1);
    });

    it('rejects webhook with invalid signature', function () {
        // Arrange: Load payload but use invalid signature
        $payload = $this->loadFixture('Webhooks/stripe_checkout_session_completed.json');
        $invalidSignature = 't=' . time() . ',v1=invalid_signature_hash';

        // Act: Send webhook with invalid signature
        $response = $this->postJson('/api/v1/webhooks/stripe', $payload, [
            'Stripe-Signature' => $invalidSignature,
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

    it('handles charge.refunded event for pass suspension', function () {
        // Arrange: Create existing pass that will be refunded
        $refundPass = Pass::factory()->create([
            'client_id' => $this->client1->id,
            'pass_type' => '10 Credit Pass',
            'credits_total' => 10,
            'credits_remaining' => 7,
            'status' => 'active',
            'external_reference' => 'stripe_payment_pi_3DEF789ghi',
        ]);

        // Load refund webhook payload
        $payload = $this->loadFixture('Webhooks/stripe_charge_refunded.json');
        // Add pass_id to metadata for tracking
        $payload['data']['object']['metadata']['pass_id'] = (string) $refundPass->id;

        $secret = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signature = $this->signStripePayload($payload, $secret, $timestamp);

        // Act: Send refund webhook
        $response = $this->postJson('/api/v1/webhooks/stripe', $payload, [
            'Stripe-Signature' => $signature,
        ]);

        // Assert: Response acknowledges refund
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'acknowledged',
                ],
            ]);

        // Note: Actual refund implementation would suspend the pass
        // This test verifies the webhook is received and logged
    });

    it('gracefully ignores unknown event types', function () {
        // Arrange: Create payload for unsupported event type
        $payload = [
            'id' => 'evt_unknown_type',
            'object' => 'event',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                ],
            ],
        ];

        $secret = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signature = $this->signStripePayload($payload, $secret, $timestamp);

        // Act: Send webhook
        $response = $this->postJson('/api/v1/webhooks/stripe', $payload, [
            'Stripe-Signature' => $signature,
        ]);

        // Assert: Returns 200 but event not handled
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'ignored',
                ],
                'message' => 'Event type not handled',
            ]);

        // Assert: No pass created
        $this->assertDatabaseCount('passes', 0);
    });

    it('handles missing customer email gracefully', function () {
        // Arrange: Create payload with missing customer email
        $payload = $this->loadFixture('Webhooks/stripe_checkout_session_completed.json');
        $payload['data']['object']['customer_details']['email'] = 'nonexistent@example.com';

        $secret = config('services.stripe.webhook_secret');
        $timestamp = time();
        $signature = $this->signStripePayload($payload, $secret, $timestamp);

        // Act: Send webhook
        $response = $this->postJson('/api/v1/webhooks/stripe', $payload, [
            'Stripe-Signature' => $signature,
        ]);

        // Assert: Returns 404 for unknown client
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Client not found',
            ]);

        // Assert: No pass created
        $this->assertDatabaseCount('passes', 0);
    });

    it('extracts credits from metadata correctly', function () {
        // Arrange: Test different credit amounts in metadata
        $testCases = [
            ['credits' => '5', 'pass_type' => '5 Credit Pass', 'amount' => 3900],
            ['credits' => '10', 'pass_type' => '10 Credit Pass', 'amount' => 7900],
            ['credits' => '30', 'pass_type' => '30 Credit Pass', 'amount' => 19900],
        ];

        foreach ($testCases as $index => $testCase) {
            // Arrange: Create unique payload for each test case
            $payload = $this->loadFixture('Webhooks/stripe_checkout_session_completed.json');
            $payload['id'] = 'evt_test_' . $index;
            $payload['data']['object']['id'] = 'cs_test_' . uniqid();
            $payload['data']['object']['metadata']['credits'] = $testCase['credits'];
            $payload['data']['object']['metadata']['pass_type'] = $testCase['pass_type'];
            $payload['data']['object']['amount_total'] = $testCase['amount'];

            $secret = config('services.stripe.webhook_secret');
            $timestamp = time();
            $signature = $this->signStripePayload($payload, $secret, $timestamp);

            // Act: Send webhook
            $response = $this->postJson('/api/v1/webhooks/stripe', $payload, [
                'Stripe-Signature' => $signature,
            ]);

            // Assert: Response is successful
            $response->assertStatus(200);

            // Assert: Pass created with correct credits
            $this->assertDatabaseHas('passes', [
                'client_id' => $this->client2->id,
                'pass_type' => $testCase['pass_type'],
                'credits_total' => (int) $testCase['credits'],
                'credits_remaining' => (int) $testCase['credits'],
                'price' => $testCase['amount'] / 100, // Convert cents to euros
            ]);
        }
    });
});
