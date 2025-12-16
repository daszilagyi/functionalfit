<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Client;
use App\Models\Pass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle WooCommerce webhook (order paid)
     *
     * POST /api/webhooks/woocommerce
     */
    public function woocommerce(Request $request): JsonResponse
    {
        // Verify webhook signature (WooCommerce sends a signature in headers)
        if (!$this->verifyWooCommerceSignature($request)) {
            Log::warning('WooCommerce webhook signature verification failed', [
                'ip' => $request->ip(),
                'payload' => $request->all(),
            ]);
            return ApiResponse::error('Invalid signature', null, 401);
        }

        $payload = $request->all();

        // Idempotency check - prevent duplicate processing
        $orderId = $payload['id'] ?? null;
        if (!$orderId) {
            return ApiResponse::error('Missing order ID', null, 422);
        }

        $idempotencyKey = "woocommerce_order_{$orderId}";

        // Check if already processed
        $existingPass = Pass::where('external_reference', $idempotencyKey)->first();
        if ($existingPass) {
            Log::info('WooCommerce order already processed', ['order_id' => $orderId]);
            return ApiResponse::success(['status' => 'already_processed'], 'Order already processed');
        }

        try {
            return DB::transaction(function () use ($payload, $idempotencyKey) {
                // Extract data from WooCommerce payload
                $customerEmail = $payload['billing']['email'] ?? null;
                $totalAmount = $payload['total'] ?? 0;
                $lineItems = $payload['line_items'] ?? [];

                // Find client by email
                $client = Client::whereHas('user', function ($query) use ($customerEmail) {
                    $query->where('email', $customerEmail);
                })->first();

                if (!$client) {
                    Log::warning('Client not found for WooCommerce order', [
                        'order_id' => $payload['id'],
                        'email' => $customerEmail,
                    ]);
                    return ApiResponse::error('Client not found', null, 404);
                }

                // Process each line item (pass purchase)
                foreach ($lineItems as $item) {
                    $productName = $item['name'] ?? 'Pass';
                    $quantity = $item['quantity'] ?? 1;
                    $price = $item['total'] ?? 0;

                    // Determine credits from product name or metadata
                    // Example: "10 Credit Pass" -> 10 credits
                    $credits = $this->extractCreditsFromProductName($productName);

                    if ($credits > 0) {
                        Pass::create([
                            'client_id' => $client->id,
                            'type' => $productName,
                            'total_credits' => $credits,
                            'credits_left' => $credits,
                            'valid_from' => now()->toDateString(),
                            'valid_until' => now()->addMonths(6)->toDateString(), // Default 6 months
                            'source' => 'woocommerce',
                            'status' => 'active',
                            'external_order_id' => (string)$payload['id'],
                            'external_reference' => $idempotencyKey,
                        ]);
                    }
                }

                Log::info('WooCommerce order processed successfully', [
                    'order_id' => $payload['id'],
                    'client_id' => $client->id,
                ]);

                return ApiResponse::success(['status' => 'processed'], 'Order processed successfully');
            });
        } catch (\Exception $e) {
            Log::error('WooCommerce webhook processing failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Processing failed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Stripe webhook
     *
     * POST /api/webhooks/stripe
     */
    public function stripe(Request $request): JsonResponse
    {
        // Verify Stripe webhook signature
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (!$this->verifyStripeSignature($request->getContent(), $signature, $webhookSecret)) {
            Log::warning('Stripe webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);
            return ApiResponse::error('Invalid signature', null, 401);
        }

        $payload = $request->all();
        $eventType = $payload['type'] ?? null;

        switch ($eventType) {
            case 'checkout.session.completed':
                return $this->handleStripeCheckoutCompleted($payload);

            case 'payment_intent.succeeded':
                return $this->handleStripePaymentSucceeded($payload);

            case 'charge.refunded':
                return $this->handleStripeRefund($payload);

            default:
                Log::info('Unhandled Stripe event type', ['type' => $eventType]);
                return ApiResponse::success(['status' => 'ignored'], 'Event type not handled');
        }
    }

    /**
     * Handle Stripe checkout completed
     */
    private function handleStripeCheckoutCompleted(array $payload): JsonResponse
    {
        $session = $payload['data']['object'] ?? [];
        $sessionId = $session['id'] ?? null;

        if (!$sessionId) {
            return ApiResponse::error('Missing session ID', null, 422);
        }

        // Idempotency check
        $idempotencyKey = "stripe_session_{$sessionId}";
        $existingPass = Pass::where('external_reference', $idempotencyKey)->first();
        if ($existingPass) {
            return ApiResponse::success(['status' => 'already_processed'], 'Session already processed');
        }

        try {
            return DB::transaction(function () use ($session, $idempotencyKey) {
                $customerEmail = $session['customer_details']['email'] ?? null;
                $amountTotal = $session['amount_total'] ?? 0;

                // Find client
                $client = Client::whereHas('user', function ($query) use ($customerEmail) {
                    $query->where('email', $customerEmail);
                })->first();

                if (!$client) {
                    Log::warning('Client not found for Stripe checkout', [
                        'session_id' => $session['id'],
                        'email' => $customerEmail,
                    ]);
                    return ApiResponse::error('Client not found', null, 404);
                }

                // Extract metadata for pass details
                $metadata = $session['metadata'] ?? [];
                $passType = $metadata['pass_type'] ?? 'Pass';
                $credits = (int)($metadata['credits'] ?? 10);

                Pass::create([
                    'client_id' => $client->id,
                    'type' => $passType,
                    'total_credits' => $credits,
                    'credits_left' => $credits,
                    'valid_from' => now()->toDateString(),
                    'valid_until' => now()->addMonths(6)->toDateString(),
                    'source' => 'stripe',
                    'status' => 'active',
                    'external_order_id' => $sessionId,
                    'external_reference' => $idempotencyKey,
                ]);

                Log::info('Stripe checkout processed successfully', [
                    'session_id' => $session['id'],
                    'client_id' => $client->id,
                ]);

                return ApiResponse::success(['status' => 'processed'], 'Checkout processed successfully');
            });
        } catch (\Exception $e) {
            Log::error('Stripe checkout processing failed', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            return ApiResponse::error('Processing failed', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Stripe payment succeeded
     */
    private function handleStripePaymentSucceeded(array $payload): JsonResponse
    {
        // Similar to checkout completed, but for direct PaymentIntent
        Log::info('Stripe payment succeeded', ['payload' => $payload]);
        return ApiResponse::success(['status' => 'acknowledged'], 'Payment acknowledged');
    }

    /**
     * Handle Stripe refund
     */
    private function handleStripeRefund(array $payload): JsonResponse
    {
        // TODO: Implement refund logic (deactivate pass, adjust credits)
        Log::info('Stripe refund received', ['payload' => $payload]);
        return ApiResponse::success(['status' => 'acknowledged'], 'Refund acknowledged');
    }

    /**
     * Verify WooCommerce webhook signature
     */
    private function verifyWooCommerceSignature(Request $request): bool
    {
        // WooCommerce sends a signature in X-WC-Webhook-Signature header
        // Implement HMAC verification with secret key
        $signature = $request->header('X-WC-Webhook-Signature');
        $secret = config('services.woocommerce.webhook_secret');

        if (!$signature || !$secret) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify Stripe webhook signature
     */
    private function verifyStripeSignature(string $payload, ?string $signature, string $secret): bool
    {
        if (!$signature) {
            return false;
        }

        try {
            // Stripe signature format: t=timestamp,v1=signature
            \Stripe\WebhookSignature::verifyHeader($payload, $signature, $secret);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extract credits from product name (e.g., "10 Credit Pass" -> 10)
     */
    private function extractCreditsFromProductName(string $productName): int
    {
        preg_match('/(\d+)\s*(credit|credits|kredit)/i', $productName, $matches);
        return (int)($matches[1] ?? 0);
    }
}
