<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Tests\TestCase;
use App\Models\User;
use App\Models\StaffProfile;
use App\Models\Client;
use App\Models\Event;
use App\Models\Room;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class StaffEventsApiTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test staff can list their own events
     */
    public function test_staff_can_list_their_own_events(): void
    {
        // Arrange: Create a staff user with events
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);

        $room = Room::factory()->create();
        $client = Client::factory()->create();

        // Create events for this staff within this week
        Event::factory()->count(3)->create([
            'staff_id' => $staffProfile->id,
            'room_id' => $room->id,
            'client_id' => $client->id,
            'type' => 'INDIVIDUAL',
            'starts_at' => Carbon::now()->addDays(1)->setTime(10, 0),
            'ends_at' => Carbon::now()->addDays(1)->setTime(11, 0),
        ]);

        // Create events for another staff (should not appear)
        $otherStaff = StaffProfile::factory()->create();
        Event::factory()->count(2)->create([
            'staff_id' => $otherStaff->id,
            'room_id' => $room->id,
            'client_id' => $client->id,
        ]);

        // Act: Make request as staff
        Sanctum::actingAs($staff);
        $response = $this->getJson('/api/v1/staff/my-events');

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'type',
                    'starts_at',
                    'ends_at',
                    'status',
                    'room',
                    'client',
                ]
            ]
        ]);
        $response->assertJsonCount(3, 'data');
    }

    /**
     * Test staff can create an individual event
     */
    public function test_staff_can_create_individual_event(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $client = Client::factory()->create();
        $serviceType = ServiceType::firstOrCreate(
            ['code' => 'PT'],
            ['name' => 'Personal Training', 'description' => 'Test PT', 'default_entry_fee_brutto' => 10000, 'default_trainer_fee_brutto' => 7000, 'is_active' => true]
        );

        $eventData = [
            'type' => 'INDIVIDUAL',
            'room_id' => $room->id,
            'client_id' => $client->id,
            'service_type_id' => $serviceType->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toIso8601String(),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0)->toIso8601String(),
            'notes' => 'Test individual session',
        ];

        // Act
        Sanctum::actingAs($staff);
        $response = $this->postJson('/api/v1/staff/events', $eventData);

        // Assert
        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'starts_at',
                'ends_at',
                'status',
                'notes',
            ]
        ]);

        $this->assertDatabaseHas('events', [
            'type' => 'INDIVIDUAL',
            'staff_id' => $staffProfile->id,
            'client_id' => $client->id,
            'room_id' => $room->id,
        ]);
    }

    /**
     * Test staff can create a block event (no client)
     */
    public function test_staff_can_create_block_event(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();

        $eventData = [
            'type' => 'BLOCK',
            'room_id' => $room->id,
            'starts_at' => Carbon::tomorrow()->setTime(14, 0)->toIso8601String(),
            'ends_at' => Carbon::tomorrow()->setTime(15, 0)->toIso8601String(),
            'notes' => 'Blocked for maintenance',
        ];

        // Act
        Sanctum::actingAs($staff);
        $response = $this->postJson('/api/v1/staff/events', $eventData);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('events', [
            'type' => 'BLOCK',
            'staff_id' => $staffProfile->id,
            'client_id' => null,
            'room_id' => $room->id,
        ]);
    }

    /**
     * Test staff cannot create event with room conflict
     */
    public function test_staff_cannot_create_event_with_room_conflict(): void
    {
        // Arrange: Create existing event
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $client = Client::factory()->create();
        $serviceType = ServiceType::firstOrCreate(
            ['code' => 'PT'],
            ['name' => 'Personal Training', 'description' => 'Test PT', 'default_entry_fee_brutto' => 10000, 'default_trainer_fee_brutto' => 7000, 'is_active' => true]
        );

        $startsAt = Carbon::tomorrow()->setTime(10, 0);
        $endsAt = Carbon::tomorrow()->setTime(11, 0);

        Event::factory()->create([
            'room_id' => $room->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Attempt to create overlapping event
        $eventData = [
            'type' => 'INDIVIDUAL',
            'room_id' => $room->id,
            'client_id' => $client->id,
            'service_type_id' => $serviceType->id,
            'starts_at' => $startsAt->copy()->addMinutes(30)->toIso8601String(), // Overlaps
            'ends_at' => $endsAt->copy()->addMinutes(30)->toIso8601String(),
        ];

        // Act
        Sanctum::actingAs($staff);
        $response = $this->postJson('/api/v1/staff/events', $eventData);

        // Assert: Conflict detection returns 409, not 422
        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
        ]);
    }

    /**
     * Test staff can update their own event (same-day move)
     */
    public function test_staff_can_update_own_event_same_day(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $client = Client::factory()->create();

        $originalStart = Carbon::tomorrow()->setTime(10, 0);
        $event = Event::factory()->create([
            'staff_id' => $staffProfile->id,
            'room_id' => $room->id,
            'client_id' => $client->id,
            'starts_at' => $originalStart,
            'ends_at' => $originalStart->copy()->addHour(),
        ]);

        // Update to different time on the same day
        $newStart = Carbon::tomorrow()->setTime(14, 0);
        $updateData = [
            'starts_at' => $newStart->format('Y-m-d H:i:s'),
            'ends_at' => $newStart->copy()->addHour()->format('Y-m-d H:i:s'),
        ];

        // Act
        Sanctum::actingAs($staff);

        // Debug: Check event exists before making request
        $this->assertDatabaseHas('events', ['id' => $event->id]);

        $response = $this->patchJson("/api/v1/staff/events/{$event->id}", $updateData);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'starts_at' => $newStart,
        ]);
    }

    /**
     * Test staff cannot move event to different day without admin
     */
    public function test_staff_cannot_move_event_to_different_day(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $client = Client::factory()->create();

        $originalStart = Carbon::tomorrow()->setTime(10, 0);
        $event = Event::factory()->create([
            'staff_id' => $staffProfile->id,
            'room_id' => $room->id,
            'client_id' => $client->id,
            'starts_at' => $originalStart,
            'ends_at' => $originalStart->copy()->addHour(),
        ]);

        // Attempt to move to different day
        $newStart = Carbon::tomorrow()->addDay()->setTime(10, 0);
        $updateData = [
            'starts_at' => $newStart->toIso8601String(),
            'ends_at' => $newStart->copy()->addHour()->toIso8601String(),
        ];

        // Act
        Sanctum::actingAs($staff);
        $response = $this->patchJson("/api/v1/staff/events/{$event->id}", $updateData);

        // Assert: Should return validation error (422) not forbidden (403)
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['starts_at']);
    }

    /**
     * Test staff can delete their own event
     */
    public function test_staff_can_delete_own_event(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $client = Client::factory()->create();

        $event = Event::factory()->create([
            'staff_id' => $staffProfile->id,
            'room_id' => $room->id,
            'client_id' => $client->id,
        ]);

        // Act
        Sanctum::actingAs($staff);
        $response = $this->deleteJson("/api/v1/staff/events/{$event->id}");

        // Assert
        $response->assertNoContent();
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    /**
     * Test staff cannot modify other staff's events
     */
    public function test_staff_cannot_modify_other_staff_events(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);

        $otherStaffProfile = StaffProfile::factory()->create();
        $room = Room::factory()->create();
        $client = Client::factory()->create();

        $event = Event::factory()->create([
            'staff_id' => $otherStaffProfile->id,
            'room_id' => $room->id,
            'client_id' => $client->id,
        ]);

        // Act: Attempt to update
        Sanctum::actingAs($staff);
        $response = $this->patchJson("/api/v1/staff/events/{$event->id}", [
            'notes' => 'Trying to modify',
        ]);

        // Assert
        $response->assertForbidden();
    }

    /**
     * Test client cannot access staff events endpoint
     */
    public function test_client_cannot_access_staff_events_endpoint(): void
    {
        // Arrange
        $client = User::factory()->create(['role' => 'client']);

        // Act
        Sanctum::actingAs($client);
        $response = $this->getJson('/api/v1/staff/my-events');

        // Assert
        $response->assertForbidden();
    }

    /**
     * Test unauthenticated user cannot access staff events
     */
    public function test_unauthenticated_user_cannot_access_staff_events(): void
    {
        $response = $this->getJson('/api/v1/staff/my-events');
        $response->assertUnauthorized();
    }

    /**
     * Test admin can access staff events endpoints
     */
    public function test_admin_can_access_staff_events_endpoints(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);

        // Note: Admin won't have their own StaffProfile, but can still access the endpoint
        // This tests that 'role:staff,admin' middleware works

        // Act
        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/staff/my-events');

        // Assert: Admin has access (200 or empty array is fine)
        $response->assertOk();
    }

    /**
     * Test staff can create event with multiple Technical Guests
     */
    public function test_staff_can_create_event_with_multiple_technical_guests(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $mainClient = Client::factory()->create();

        // Create Technical Guest client
        $technicalGuest = Client::factory()->create([
            'user_id' => null,
            'full_name' => 'Technikai Vendég',
        ]);

        // Store technical guest ID in settings
        \DB::table('settings')->updateOrInsert(
            ['key' => 'technical_guest_client_id'],
            [
                'value' => json_encode($technicalGuest->id),
                'description' => 'ID of the special Technical Guest client',
                'updated_at' => now(),
            ]
        );

        $serviceType = ServiceType::firstOrCreate(
            ['code' => 'PT'],
            ['name' => 'Personal Training', 'description' => 'Test PT', 'default_entry_fee_brutto' => 10000, 'default_trainer_fee_brutto' => 7000, 'is_active' => true]
        );

        // Use negative IDs to represent technical guests (as per validation rules)
        $eventData = [
            'type' => 'INDIVIDUAL',
            'room_id' => $room->id,
            'client_id' => $mainClient->id,
            'service_type_id' => $serviceType->id,
            'additional_client_ids' => [
                -1,
                -1,
                -1,
            ], // 3 technical guests (negative IDs)
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toIso8601String(),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0)->toIso8601String(),
            'notes' => 'Group session with 3 unknown guests',
        ];

        // Act
        Sanctum::actingAs($staff);
        $response = $this->postJson('/api/v1/staff/events', $eventData);

        // Assert
        $response->assertCreated();

        $this->assertDatabaseHas('events', [
            'type' => 'INDIVIDUAL',
            'staff_id' => $staffProfile->id,
            'client_id' => $mainClient->id,
        ]);

        // Check that technical guest was added with quantity = 3
        // Note: Controller converts negative IDs to the actual technical guest client ID
        $this->assertDatabaseHas('event_additional_clients', [
            'client_id' => $technicalGuest->id,
            'quantity' => 3,
        ]);

        // Verify response includes expanded list
        $event = Event::latest()->first();
        $event->load(['additionalClients']);
        $event->append('expandedAdditionalClients');

        expect($event->expandedAdditionalClients)->toHaveCount(3);
        expect($event->expandedAdditionalClients[0]->id)->toBe($technicalGuest->id);
        expect($event->expandedAdditionalClients[1]->id)->toBe($technicalGuest->id);
        expect($event->expandedAdditionalClients[2]->id)->toBe($technicalGuest->id);
    }

    /**
     * Test staff can create event with mixed regular clients and Technical Guests
     */
    public function test_staff_can_create_event_with_mixed_clients_and_technical_guests(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $mainClient = Client::factory()->create();
        $regularClient = Client::factory()->create();

        // Create Technical Guest
        $technicalGuest = Client::factory()->create([
            'user_id' => null,
            'full_name' => 'Technikai Vendég',
        ]);

        \DB::table('settings')->updateOrInsert(
            ['key' => 'technical_guest_client_id'],
            ['value' => json_encode($technicalGuest->id), 'updated_at' => now()]
        );

        $serviceType = ServiceType::firstOrCreate(
            ['code' => 'PT'],
            ['name' => 'Personal Training', 'description' => 'Test PT', 'default_entry_fee_brutto' => 10000, 'default_trainer_fee_brutto' => 7000, 'is_active' => true]
        );

        // Use negative IDs for technical guests (as per validation rules)
        $eventData = [
            'type' => 'INDIVIDUAL',
            'room_id' => $room->id,
            'client_id' => $mainClient->id,
            'service_type_id' => $serviceType->id,
            'additional_client_ids' => [
                $regularClient->id,
                -1, // technical guest
                -1, // technical guest
            ],
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toIso8601String(),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0)->toIso8601String(),
        ];

        // Act
        Sanctum::actingAs($staff);
        $response = $this->postJson('/api/v1/staff/events', $eventData);

        // Assert
        $response->assertCreated();

        // Check regular client (quantity = 1)
        $this->assertDatabaseHas('event_additional_clients', [
            'client_id' => $regularClient->id,
            'quantity' => 1,
        ]);

        // Check technical guest (quantity = 2) - controller converts -1 to actual technical guest ID
        $this->assertDatabaseHas('event_additional_clients', [
            'client_id' => $technicalGuest->id,
            'quantity' => 2,
        ]);
    }

    /**
     * Test staff cannot add regular client multiple times
     */
    public function test_staff_cannot_add_regular_client_multiple_times(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $mainClient = Client::factory()->create();
        $regularClient = Client::factory()->create();
        $serviceType = ServiceType::firstOrCreate(
            ['code' => 'PT'],
            ['name' => 'Personal Training', 'description' => 'Test PT', 'default_entry_fee_brutto' => 10000, 'default_trainer_fee_brutto' => 7000, 'is_active' => true]
        );

        $eventData = [
            'type' => 'INDIVIDUAL',
            'room_id' => $room->id,
            'client_id' => $mainClient->id,
            'service_type_id' => $serviceType->id,
            'additional_client_ids' => [
                $regularClient->id,
                $regularClient->id, // Duplicate - should fail
            ],
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toIso8601String(),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0)->toIso8601String(),
        ];

        // Act
        Sanctum::actingAs($staff);
        $response = $this->postJson('/api/v1/staff/events', $eventData);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['additional_client_ids']);
    }

    /**
     * Test staff can update event to add more Technical Guests
     */
    public function test_staff_can_update_event_to_add_more_technical_guests(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $client = Client::factory()->create();

        // Create Technical Guest
        $technicalGuest = Client::factory()->create([
            'user_id' => null,
            'full_name' => 'Technikai Vendég',
        ]);

        \DB::table('settings')->updateOrInsert(
            ['key' => 'technical_guest_client_id'],
            ['value' => json_encode($technicalGuest->id), 'updated_at' => now()]
        );

        // Create event with 1 technical guest
        $event = Event::factory()->create([
            'staff_id' => $staffProfile->id,
            'room_id' => $room->id,
            'client_id' => $client->id,
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0),
        ]);

        $event->additionalClients()->attach($technicalGuest->id, ['quantity' => 1]);

        // Update to 3 technical guests using negative IDs (as per validation rules)
        $updateData = [
            'additional_client_ids' => [
                -1,
                -1,
                -1,
            ],
        ];

        // Act
        Sanctum::actingAs($staff);
        $response = $this->patchJson("/api/v1/staff/events/{$event->id}", $updateData);

        // Assert
        $response->assertOk();

        // Verify quantity updated to 3 - controller converts -1 to actual technical guest ID
        $this->assertDatabaseHas('event_additional_clients', [
            'event_id' => $event->id,
            'client_id' => $technicalGuest->id,
            'quantity' => 3,
        ]);
    }

    /**
     * Test expanded additional clients appear correctly in API response
     */
    public function test_expanded_additional_clients_in_api_response(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staff->id]);
        $room = Room::factory()->create();
        $client = Client::factory()->create();

        // Create Technical Guest
        $technicalGuest = Client::factory()->create([
            'user_id' => null,
            'full_name' => 'Technikai Vendég',
        ]);

        \DB::table('settings')->updateOrInsert(
            ['key' => 'technical_guest_client_id'],
            ['value' => json_encode($technicalGuest->id), 'updated_at' => now()]
        );

        $event = Event::factory()->create([
            'staff_id' => $staffProfile->id,
            'room_id' => $room->id,
            'client_id' => $client->id,
            'starts_at' => Carbon::now()->addDay()->setTime(10, 0),
            'ends_at' => Carbon::now()->addDay()->setTime(11, 0),
        ]);

        $event->additionalClients()->attach($technicalGuest->id, ['quantity' => 2]);

        // Act
        Sanctum::actingAs($staff);
        $response = $this->getJson('/api/v1/staff/my-events');

        // Assert
        $response->assertOk();
        $responseData = $response->json('data');

        // Find our event in the response
        $eventData = collect($responseData)->firstWhere('id', $event->id);

        expect($eventData)->not->toBeNull();
        expect($eventData['expanded_additional_clients'])->toHaveCount(2);
    }
}
