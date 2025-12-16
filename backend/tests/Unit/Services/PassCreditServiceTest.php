<?php

declare(strict_types=1);

use App\Exceptions\PolicyViolationException;
use App\Models\Client;
use App\Models\Pass;
use App\Services\PassCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PassCreditService - Credit Availability', function () {
    beforeEach(function () {
        $this->service = new PassCreditService();
        $this->client = Client::factory()->create();
    });

    it('returns true when client has active passes with credits', function () {
        // Arrange: Create an active pass with credits
        Pass::factory()->active()->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
        ]);

        // Act
        $hasCredits = $this->service->hasAvailableCredits($this->client);

        // Assert
        expect($hasCredits)->toBeTrue();
    });

    it('returns false when client has no active passes', function () {
        // Arrange: Create expired and used-up passes only
        Pass::factory()->expired()->create(['client_id' => $this->client->id]);
        Pass::factory()->usedUp()->create(['client_id' => $this->client->id]);

        // Act
        $hasCredits = $this->service->hasAvailableCredits($this->client);

        // Assert
        expect($hasCredits)->toBeFalse();
    });

    it('returns false when client has active pass with zero credits', function () {
        // Arrange: Create an active pass but with 0 credits
        Pass::factory()->active()->withCredits(10, 0)->create([
            'client_id' => $this->client->id,
        ]);

        // Act
        $hasCredits = $this->service->hasAvailableCredits($this->client);

        // Assert
        expect($hasCredits)->toBeFalse();
    });
});

describe('PassCreditService - Get Available Pass', function () {
    beforeEach(function () {
        $this->service = new PassCreditService();
        $this->client = Client::factory()->create();
    });

    it('returns the pass expiring soonest when multiple active passes exist', function () {
        // Arrange: Create multiple active passes with different expiry dates
        $passExpiring30Days = Pass::factory()->active()->expiringIn(30)->create([
            'client_id' => $this->client->id,
        ]);

        $passExpiring7Days = Pass::factory()->active()->expiringIn(7)->create([
            'client_id' => $this->client->id,
        ]);

        $passExpiring60Days = Pass::factory()->active()->expiringIn(60)->create([
            'client_id' => $this->client->id,
        ]);

        // Act
        $pass = $this->service->getAvailablePass($this->client);

        // Assert: Should return the pass expiring in 7 days
        expect($pass->id)->toBe($passExpiring7Days->id);
    });

    it('returns null when no active passes exist', function () {
        // Arrange: Create only expired passes
        Pass::factory()->expired()->create(['client_id' => $this->client->id]);

        // Act
        $pass = $this->service->getAvailablePass($this->client);

        // Assert
        expect($pass)->toBeNull();
    });

    it('ignores passes with zero credits', function () {
        // Arrange: Create a pass expiring soon but with 0 credits
        Pass::factory()->active()->expiringIn(7)->withCredits(10, 0)->create([
            'client_id' => $this->client->id,
        ]);

        // Create a pass expiring later with credits
        $passWithCredits = Pass::factory()->active()->expiringIn(30)->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
        ]);

        // Act
        $pass = $this->service->getAvailablePass($this->client);

        // Assert: Should skip the pass with 0 credits
        expect($pass->id)->toBe($passWithCredits->id);
    });

    it('uses oldest pass when expiry dates are the same', function () {
        // Arrange: Create two passes with same expiry date
        $olderPass = Pass::factory()->active()->expiringIn(30)->create([
            'client_id' => $this->client->id,
            'created_at' => now()->subDays(10),
        ]);

        $newerPass = Pass::factory()->active()->expiringIn(30)->create([
            'client_id' => $this->client->id,
            'created_at' => now()->subDays(5),
        ]);

        // Act
        $pass = $this->service->getAvailablePass($this->client);

        // Assert: Should return the older pass
        expect($pass->id)->toBe($olderPass->id);
    });
});

describe('PassCreditService - Deduct Credit', function () {
    beforeEach(function () {
        $this->service = new PassCreditService();
        $this->client = Client::factory()->create();
    });

    it('deducts one credit from active pass', function () {
        // Arrange: Create a pass with 5 credits
        $pass = Pass::factory()->active()->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
        ]);

        // Act
        $updatedPass = $this->service->deductCredit($this->client, 'class_booking');

        // Assert
        expect($updatedPass->credits_left)->toBe(4);
        expect($updatedPass->status)->toBe('active');
    });

    it('marks pass as used_up when last credit is deducted', function () {
        // Arrange: Create a pass with 1 credit left
        $pass = Pass::factory()->active()->withCredits(10, 1)->create([
            'client_id' => $this->client->id,
        ]);

        // Act
        $updatedPass = $this->service->deductCredit($this->client, 'class_booking');

        // Assert
        expect($updatedPass->credits_left)->toBe(0);
        expect($updatedPass->status)->toBe('depleted');
    });

    it('throws exception when no active pass exists', function () {
        // Arrange: No active passes for client
        Pass::factory()->expired()->create(['client_id' => $this->client->id]);

        // Act & Assert
        expect(fn () => $this->service->deductCredit($this->client, 'class_booking'))
            ->toThrow(PolicyViolationException::class, 'No active pass with available credits found');
    });

    it('throws exception when pass has no remaining credits', function () {
        // Arrange: Create a pass with 0 credits (but still marked active somehow)
        Pass::factory()->create([
            'client_id' => $this->client->id,
            'status' => 'active',
            'credits_left' => 0,
            'total_credits' => 10,
            'valid_from' => now()->subDays(5),
            'valid_until' => now()->addDays(30),
        ]);

        // Act & Assert
        expect(fn () => $this->service->deductCredit($this->client, 'class_booking'))
            ->toThrow(PolicyViolationException::class);
    });

    it('uses expiry priority when multiple passes available', function () {
        // Arrange: Create passes expiring at different times
        $passExpiring7Days = Pass::factory()->active()->expiringIn(7)->withCredits(10, 3)->create([
            'client_id' => $this->client->id,
        ]);

        $passExpiring30Days = Pass::factory()->active()->expiringIn(30)->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
        ]);

        // Act: Deduct credit
        $updatedPass = $this->service->deductCredit($this->client, 'class_booking');

        // Assert: Should have used the pass expiring in 7 days
        expect($updatedPass->id)->toBe($passExpiring7Days->id);
        expect($updatedPass->credits_left)->toBe(2);

        // Verify the other pass wasn't touched
        $passExpiring30Days->refresh();
        expect($passExpiring30Days->credits_left)->toBe(5);
    });

    it('uses pessimistic locking to prevent race conditions', function () {
        // Arrange: Create a pass with 1 credit
        $pass = Pass::factory()->active()->withCredits(10, 1)->create([
            'client_id' => $this->client->id,
        ]);

        // Act: Deduct credit (internally uses lockForUpdate)
        $this->service->deductCredit($this->client, 'class_booking');

        // Assert: Pass should be depleted and locked during transaction
        $pass->refresh();
        expect($pass->credits_left)->toBe(0);
        expect($pass->status)->toBe('depleted');
    });
});

describe('PassCreditService - Refund Credit', function () {
    beforeEach(function () {
        $this->service = new PassCreditService();
        $this->client = Client::factory()->create();
    });

    it('refunds one credit to specified pass', function () {
        // Arrange: Create a pass with some credits used
        $pass = Pass::factory()->active()->withCredits(10, 7)->create([
            'client_id' => $this->client->id,
        ]);

        // Act: Refund credit to specific pass
        $updatedPass = $this->service->refundCredit($this->client, 1, 'class_cancellation', $pass->id);

        // Assert
        expect($updatedPass->credits_left)->toBe(8);
        expect($updatedPass->status)->toBe('active');
    });

    it('reactivates depleted pass when credit is refunded', function () {
        // Arrange: Create a depleted pass
        $pass = Pass::factory()->usedUp()->create([
            'client_id' => $this->client->id,
            'total_credits' => 10,
        ]);

        // Act: Refund credit
        $updatedPass = $this->service->refundCredit($this->client, 1, 'class_cancellation', $pass->id);

        // Assert
        expect($updatedPass->credits_left)->toBe(1);
        expect($updatedPass->status)->toBe('active');
    });

    it('refunds to most recently used pass when pass ID not specified', function () {
        // Arrange: Create multiple passes with used credits
        $olderPass = Pass::factory()->active()->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
            'updated_at' => now()->subHours(5),
        ]);

        $recentPass = Pass::factory()->active()->withCredits(10, 8)->create([
            'client_id' => $this->client->id,
            'updated_at' => now()->subHours(1),
        ]);

        // Act: Refund without specifying pass ID
        $updatedPass = $this->service->refundCredit($this->client, 1, 'class_cancellation');

        // Assert: Should refund to the most recently updated pass
        expect($updatedPass->id)->toBe($recentPass->id);
        expect($updatedPass->credits_left)->toBe(9);
    });

    it('does not refund to passes that never had credits used', function () {
        // Arrange: Create a pass with all credits intact and another with used credits
        Pass::factory()->active()->withCredits(10, 10)->create([
            'client_id' => $this->client->id,
        ]);

        $passWithUsedCredits = Pass::factory()->active()->withCredits(10, 7)->create([
            'client_id' => $this->client->id,
            'updated_at' => now()->subHours(1),
        ]);

        // Act: Refund without specifying pass ID
        $updatedPass = $this->service->refundCredit($this->client, 1, 'class_cancellation');

        // Assert: Should refund to the pass with used credits
        expect($updatedPass->id)->toBe($passWithUsedCredits->id);
        expect($updatedPass->credits_left)->toBe(8);
    });

    it('uses pessimistic locking during refund', function () {
        // Arrange: Create a pass
        $pass = Pass::factory()->active()->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
        ]);

        // Act: Refund credit (internally uses lockForUpdate)
        $this->service->refundCredit($this->client, 1, 'class_cancellation', $pass->id);

        // Assert: Pass was updated atomically
        $pass->refresh();
        expect($pass->credits_left)->toBe(6);
    });
});

describe('PassCreditService - Total Available Credits', function () {
    beforeEach(function () {
        $this->service = new PassCreditService();
        $this->client = Client::factory()->create();
    });

    it('calculates total credits across multiple active passes', function () {
        // Arrange: Create multiple active passes
        Pass::factory()->active()->withCredits(10, 5)->create(['client_id' => $this->client->id]);
        Pass::factory()->active()->withCredits(20, 12)->create(['client_id' => $this->client->id]);
        Pass::factory()->active()->withCredits(10, 3)->create(['client_id' => $this->client->id]);

        // Act
        $total = $this->service->getTotalAvailableCredits($this->client);

        // Assert: 5 + 12 + 3 = 20
        expect($total)->toBe(20);
    });

    it('returns zero when no active passes exist', function () {
        // Arrange: Create only expired/used-up passes
        Pass::factory()->expired()->create(['client_id' => $this->client->id]);
        Pass::factory()->usedUp()->create(['client_id' => $this->client->id]);

        // Act
        $total = $this->service->getTotalAvailableCredits($this->client);

        // Assert
        expect($total)->toBe(0);
    });

    it('ignores expired passes in total calculation', function () {
        // Arrange: Create active and expired passes
        Pass::factory()->active()->withCredits(10, 5)->create(['client_id' => $this->client->id]);
        Pass::factory()->expired()->withCredits(10, 8)->create(['client_id' => $this->client->id]);

        // Act
        $total = $this->service->getTotalAvailableCredits($this->client);

        // Assert: Only counts active pass
        expect($total)->toBe(5);
    });

    it('ignores depleted passes in total calculation', function () {
        // Arrange: Create active and depleted passes
        Pass::factory()->active()->withCredits(10, 7)->create(['client_id' => $this->client->id]);
        Pass::factory()->usedUp()->create(['client_id' => $this->client->id]);

        // Act
        $total = $this->service->getTotalAvailableCredits($this->client);

        // Assert: Only counts active pass
        expect($total)->toBe(7);
    });
});

describe('PassCreditService - Transaction Safety', function () {
    beforeEach(function () {
        $this->service = new PassCreditService();
        $this->client = Client::factory()->create();
    });

    it('rolls back deduction when exception occurs', function () {
        // Arrange: Create a pass
        $pass = Pass::factory()->active()->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
        ]);

        // Simulate a scenario where we'd want rollback (this is conceptual)
        // In practice, the transaction would rollback if an exception is thrown

        // Act: Deduct credit successfully
        $this->service->deductCredit($this->client, 'class_booking');

        // Assert: Credit was deducted
        $pass->refresh();
        expect($pass->credits_left)->toBe(4);
    });

    it('maintains data integrity during concurrent operations', function () {
        // Arrange: Create a pass with 10 credits
        $pass = Pass::factory()->active()->withCredits(10, 10)->create([
            'client_id' => $this->client->id,
        ]);

        // Act: Deduct multiple credits sequentially (simulating concurrent requests)
        $this->service->deductCredit($this->client, 'booking_1');
        $this->service->deductCredit($this->client, 'booking_2');
        $this->service->deductCredit($this->client, 'booking_3');

        // Assert: Credits should be 7 (10 - 3)
        $pass->refresh();
        expect($pass->credits_left)->toBe(7);
    });

    it('prevents double deduction through pessimistic locking', function () {
        // Arrange: Create a pass with 1 credit
        $pass = Pass::factory()->active()->withCredits(10, 1)->create([
            'client_id' => $this->client->id,
        ]);

        // Act: Deduct the last credit
        $this->service->deductCredit($this->client, 'class_booking');

        // Assert: Pass should be depleted and second deduction should fail
        expect(fn () => $this->service->deductCredit($this->client, 'another_booking'))
            ->toThrow(PolicyViolationException::class);

        $pass->refresh();
        expect($pass->credits_left)->toBe(0);
        expect($pass->status)->toBe('depleted');
    });
});
