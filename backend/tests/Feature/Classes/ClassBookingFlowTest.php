<?php

declare(strict_types=1);

use App\Models\ClassOccurrence;
use App\Models\ClassRegistration;
use App\Models\ClassTemplate;
use App\Models\Client;
use App\Models\Pass;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('GET /api/v1/classes - Browse Classes', function () {
    beforeEach(function () {
        $this->user = User::factory()->client()->create();
        $this->client = Client::factory()->create(['user_id' => $this->user->id]);
        Sanctum::actingAs($this->user);
    });

    it('returns list of upcoming class occurrences', function () {
        // Arrange: Create class occurrences
        $template = ClassTemplate::factory()->create();
        ClassOccurrence::factory()->count(3)
            ->startingAt(Carbon::now()->addDays(1))
            ->create(['template_id' => $template->id]);

        // Act
        $response = $this->getJson('/api/v1/classes');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'starts_at', 'ends_at', 'capacity', 'status'],
                ],
            ]);
    });

    it('filters classes by date range', function () {
        // Arrange: Create classes at different future dates (must be in future for upcoming() scope)
        $template = ClassTemplate::factory()->create();
        $startDate = Carbon::now()->addDays(5)->startOfDay();
        $endDate = Carbon::now()->addDays(10)->endOfDay();

        // Class within date range
        ClassOccurrence::factory()
            ->startingAt($startDate->copy()->addDays(1))
            ->create(['template_id' => $template->id]);

        // Class outside date range (after end date)
        ClassOccurrence::factory()
            ->startingAt($endDate->copy()->addDays(5))
            ->create(['template_id' => $template->id]);

        // Act: Filter for the date range
        $response = $this->getJson('/api/v1/classes?start_date=' . $startDate->toDateString() . '&end_date=' . $endDate->toDateString());

        // Assert: Should only return the class within range
        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(1);
    });

    it('shows available spots for each class', function () {
        // Arrange: Create a future class with capacity 10 and 3 registrations
        $occurrence = ClassOccurrence::factory()
            ->withCapacity(10)
            ->startingAt(Carbon::now()->addDays(3))
            ->create();
        ClassRegistration::factory()->count(3)->confirmed()->create([
            'occurrence_id' => $occurrence->id,
        ]);

        // Act
        $response = $this->getJson('/api/v1/classes');

        // Assert
        $response->assertStatus(200);
        $classData = collect($response->json('data'))->firstWhere('id', $occurrence->id);
        expect($classData)->not->toBeNull();
        expect($classData['available_spots'])->toBe(7); // 10 - 3
    });
});

describe('GET /api/v1/classes - Authentication', function () {
    it('requires authentication to browse classes', function () {
        // Act: Try without authentication (no Sanctum::actingAs)
        $response = $this->getJson('/api/v1/classes');

        // Assert
        $response->assertStatus(401);
    });
});

describe('POST /api/v1/classes/{id}/book - Book Class', function () {
    beforeEach(function () {
        $this->user = User::factory()->client()->create();
        $this->client = Client::factory()->create(['user_id' => $this->user->id]);
        Sanctum::actingAs($this->user);
    });

    it('successfully books a class with available capacity', function () {
        // Arrange: Create active pass and class with capacity
        Pass::factory()->active()->withCredits(10, 5)->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()->withCapacity(10)->create();

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Booking confirmed',
            ]);

        $this->assertDatabaseHas('class_registrations', [
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
            'status' => 'booked',
        ]);
    });

    it('adds to waitlist when class is full', function () {
        // Arrange: Create class with capacity 2, already full
        Pass::factory()->active()->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()->withCapacity(2)->create();
        ClassRegistration::factory()->count(2)->confirmed()->create([
            'occurrence_id' => $occurrence->id,
        ]);

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('class_registrations', [
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
            'status' => 'waitlist',
        ]);
    });

    it('prevents double booking', function () {
        // Arrange: Create pass and book a future class
        Pass::factory()->active()->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()
            ->startingAt(Carbon::now()->addDays(3))
            ->create();
        ClassRegistration::factory()->confirmed()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
            'payment_status' => 'paid',
        ]);

        // Act: Try to book again
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert: Validation error returned from form request
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id' => 'Already registered for this class']);
    });

    it('allows booking without active pass with unpaid status', function () {
        // Arrange: Client has no active passes, create future class
        $occurrence = ClassOccurrence::factory()
            ->startingAt(Carbon::now()->addDays(3))
            ->create();

        // Act: Book without having an active pass
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert: Booking should succeed with 'unpaid' payment_status
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Booking confirmed (added to unpaid balance)',
            ]);

        $this->assertDatabaseHas('class_registrations', [
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
            'status' => 'booked',
            'payment_status' => 'unpaid',
            'credits_used' => 0,
        ]);
    });

    it('prevents booking cancelled classes', function () {
        // Arrange: Create cancelled class in the future
        Pass::factory()->active()->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()
            ->cancelled()
            ->startingAt(Carbon::now()->addDays(3))
            ->create();

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert: Validation error returned from form request
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_id' => 'This class has been cancelled']);
    });

    it('prevents booking past classes', function () {
        // Arrange: Create past class
        Pass::factory()->active()->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()
            ->startingAt(Carbon::now()->subHours(2))
            ->create();

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert: Validation error returned from form request
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['occurrence_id' => 'Cannot book classes in the past']);
    });
});

describe('POST /api/v1/classes/{id}/book - Authentication', function () {
    it('requires authentication', function () {
        // Arrange
        $occurrence = ClassOccurrence::factory()->create();

        // Act: Try without authentication (no Sanctum::actingAs)
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert
        $response->assertStatus(401);
    });
});

describe('POST /api/v1/classes/{id}/cancel - Cancel Booking', function () {
    beforeEach(function () {
        $this->user = User::factory()->client()->create();
        $this->client = Client::factory()->create(['user_id' => $this->user->id]);
        Sanctum::actingAs($this->user);
    });

    it('successfully cancels booking within 24h window', function () {
        // Arrange: Book a class starting in 2 days
        $pass = Pass::factory()->active()->withCredits(10, 4)->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()
            ->startingAt(Carbon::now()->addDays(2))
            ->create();
        $registration = ClassRegistration::factory()->confirmed()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
        ]);

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/cancel");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Booking cancelled and credit refunded',
            ]);

        $this->assertDatabaseHas('class_registrations', [
            'id' => $registration->id,
            'status' => 'cancelled',
        ]);

        // Check credit refund
        $pass->refresh();
        expect($pass->credits_left)->toBe(5); // 4 + 1 refund
    });

    it('prevents cancellation within 24h window', function () {
        // Arrange: Book a class starting in 12 hours
        Pass::factory()->active()->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()
            ->startingAt(Carbon::now()->addHours(12))
            ->create();
        ClassRegistration::factory()->confirmed()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
        ]);

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/cancel");

        // Assert
        $response->assertStatus(423)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot cancel within 24 hours of class start',
            ]);
    });

    it('prevents cancelling already cancelled booking', function () {
        // Arrange: Create cancelled booking for a future class
        $occurrence = ClassOccurrence::factory()
            ->startingAt(Carbon::now()->addDays(3))
            ->create();
        ClassRegistration::factory()->cancelled()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
        ]);

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/cancel");

        // Assert: Validation catches this as "no active registration" since cancelled is not in ['booked', 'waitlist']
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id' => 'No active registration found for this class']);
    });

    it('cancels waitlist without credit refund', function () {
        // Arrange: Create waitlist booking (no credit used)
        $pass = Pass::factory()->active()->withCredits(10, 10)->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()
            ->startingAt(Carbon::now()->addDays(2))
            ->create();
        ClassRegistration::factory()->waitlist()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
        ]);

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/cancel");

        // Assert
        $response->assertStatus(200);

        // Check no credit change
        $pass->refresh();
        expect($pass->credits_left)->toBe(10); // No change
    });

    it('promotes next waitlist client when cancelling confirmed booking', function () {
        // Arrange: Full class with waitlist, starting in 2 days (past 24h cancel window)
        // Create a pass for the cancelling client so credits can be refunded
        $pass = Pass::factory()->active()->withCredits(10, 9)->create(['client_id' => $this->client->id]);

        $occurrence = ClassOccurrence::factory()
            ->withCapacity(2)
            ->startingAt(Carbon::now()->addDays(2))
            ->create();

        // Create 2 confirmed and 1 waitlist
        $confirmedClient1 = Client::factory()->create();
        ClassRegistration::factory()->confirmed()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $confirmedClient1->id,
        ]);

        ClassRegistration::factory()->confirmed()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
        ]);

        $waitlistClient = Client::factory()->create();
        ClassRegistration::factory()->waitlist()->create([
            'occurrence_id' => $occurrence->id,
            'client_id' => $waitlistClient->id,
            'booked_at' => Carbon::now()->subMinutes(10),
        ]);

        // Act: Cancel our booking
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/cancel");

        // Assert
        $response->assertStatus(200);

        // Waitlist client should be promoted
        $this->assertDatabaseHas('class_registrations', [
            'occurrence_id' => $occurrence->id,
            'client_id' => $waitlistClient->id,
            'status' => 'booked',
        ]);
    });
});

describe('POST /api/v1/classes/{id}/cancel - Authentication', function () {
    it('requires authentication', function () {
        // Arrange
        $occurrence = ClassOccurrence::factory()->create();

        // Act: Try without authentication (no Sanctum::actingAs)
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/cancel");

        // Assert
        $response->assertStatus(401);
    });
});

describe('Class Booking - Edge Cases', function () {
    beforeEach(function () {
        $this->user = User::factory()->client()->create();
        $this->client = Client::factory()->create(['user_id' => $this->user->id]);
        Sanctum::actingAs($this->user);
    });

    it('handles concurrent booking attempts for last spot', function () {
        // Arrange: Class with 1 spot left
        Pass::factory()->active()->create(['client_id' => $this->client->id]);
        $occurrence = ClassOccurrence::factory()->withCapacity(2)->create();
        ClassRegistration::factory()->confirmed()->create([
            'occurrence_id' => $occurrence->id,
        ]);

        // Act: Try to book (should get the last spot)
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('class_registrations', [
            'occurrence_id' => $occurrence->id,
            'client_id' => $this->client->id,
            'status' => 'booked',
        ]);
    });

    it('handles booking with pass expiring soon', function () {
        // Arrange: Pass expiring in 1 day
        Pass::factory()->active()->expiringIn(1)->withCredits(10, 1)->create([
            'client_id' => $this->client->id,
        ]);
        $occurrence = ClassOccurrence::factory()->create();

        // Act
        $response = $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert: Should allow booking even if pass expires soon
        $response->assertStatus(201);
    });

    it('uses expiry priority when multiple passes available', function () {
        // Arrange: Create two passes, one expiring sooner
        $passSoonExpire = Pass::factory()->active()->expiringIn(5)->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
        ]);
        Pass::factory()->active()->expiringIn(30)->withCredits(10, 5)->create([
            'client_id' => $this->client->id,
        ]);
        $occurrence = ClassOccurrence::factory()->create();

        // Act: Book (should use soon-expiring pass)
        $this->postJson("/api/v1/classes/{$occurrence->id}/book");

        // Assert: Soon-expiring pass should be used
        $passSoonExpire->refresh();
        expect($passSoonExpire->credits_left)->toBe(4);
    });
});
