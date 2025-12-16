<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Base test case for integration tests.
 *
 * Integration tests verify interactions between multiple components,
 * including webhooks, queue jobs, external services, and scheduled commands.
 */
abstract class IntegrationTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Fake queue for testing job dispatch without execution
        Queue::fake();

        // Fake mail for testing email sending without SMTP
        Mail::fake();

        // Fake bus for testing event dispatching
        Bus::fake();

        // Fake notifications
        Notification::fake();

        // Set testing environment variables
        config(['services.google_calendar.sync_enabled' => false]);

        // Set consistent timezone for tests
        config(['app.timezone' => 'Europe/Budapest']);

        // Disable external API calls by default
        $this->disableExternalServices();
    }

    /**
     * Disable external services for testing.
     */
    protected function disableExternalServices(): void
    {
        // Disable Google Calendar sync
        config(['services.google_calendar.sync_enabled' => false]);

        // Set webhook secrets for testing
        config([
            'services.woocommerce.webhook_secret' => 'test_woocommerce_secret',
            'services.stripe.webhook_secret' => 'whsec_test_stripe_secret',
        ]);
    }

    /**
     * Sign a WooCommerce webhook payload.
     *
     * WooCommerce uses HMAC-SHA256 signature with base64 encoding.
     *
     * @param array $payload The webhook payload
     * @param string $secret The webhook secret
     * @return string The signature
     */
    protected function signWooCommercePayload(array $payload, string $secret): string
    {
        $jsonPayload = json_encode($payload);
        $hash = hash_hmac('sha256', $jsonPayload, $secret, true);
        return base64_encode($hash);
    }

    /**
     * Sign a Stripe webhook payload.
     *
     * Stripe uses timestamp + payload signature scheme.
     * Format: t=timestamp,v1=signature
     *
     * @param array $payload The webhook payload
     * @param string $secret The webhook secret
     * @param int|null $timestamp The timestamp (defaults to now)
     * @return string The Stripe-Signature header value
     */
    protected function signStripePayload(array $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $jsonPayload = json_encode($payload);
        $signedPayload = "{$timestamp}.{$jsonPayload}";
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Load a JSON fixture file.
     *
     * @param string $filename The fixture filename (without path)
     * @return array The decoded JSON data
     */
    protected function loadFixture(string $filename): array
    {
        $path = base_path("tests/Fixtures/{$filename}");

        if (!file_exists($path)) {
            throw new \RuntimeException("Fixture file not found: {$path}");
        }

        $json = file_get_contents($path);
        return json_decode($json, true);
    }

    /**
     * Create a mock Google Calendar service.
     *
     * @return \Mockery\MockInterface
     */
    protected function mockGoogleCalendarService(): \Mockery\MockInterface
    {
        return $this->mock(\App\Services\GoogleCalendarService::class);
    }

    /**
     * Create a mock Google Calendar API client.
     *
     * @return \Mockery\MockInterface
     */
    protected function mockGoogleCalendarClient(): \Mockery\MockInterface
    {
        return $this->mock(\Google\Service\Calendar::class);
    }

    /**
     * Assert that a job was dispatched with specific attributes.
     *
     * @param string $jobClass The job class name
     * @param callable|null $callback Optional callback to verify job properties
     */
    protected function assertJobDispatched(string $jobClass, ?callable $callback = null): void
    {
        Queue::assertPushed($jobClass, $callback);
    }

    /**
     * Assert that an email was sent to a specific recipient.
     *
     * @param string $mailable The mailable class name
     * @param string $email The recipient email
     */
    protected function assertEmailSent(string $mailable, string $email): void
    {
        Mail::assertSent($mailable, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    /**
     * Teardown the test environment.
     */
    protected function tearDown(): void
    {
        // Clear any mocks
        \Mockery::close();

        parent::tearDown();
    }
}
