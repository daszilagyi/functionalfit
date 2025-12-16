<?php

declare(strict_types=1);

namespace Tests\Feature\Staff;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Room;
use App\Models\Site;
use App\Models\Client;
use App\Models\StaffProfile;
use App\Models\ServiceType;
use App\Models\ClassOccurrence;
use App\Models\ClassTemplate;
use App\Models\ClassRegistration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class StaffReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;
    private StaffProfile $trainer;
    private Site $site;
    private Room $room;
    private Client $client;
    private ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create staff user with profile
        $this->staffUser = User::factory()->create(['role' => 'staff']);
        $this->trainer = StaffProfile::factory()->create([
            'user_id' => $this->staffUser->id,
        ]);

        // Create site and room
        $this->site = Site::factory()->create(['name' => 'Test Site']);
        $this->room = Room::factory()->create([
            'site_id' => $this->site->id,
            'name' => 'Test Room',
        ]);

        // Create client
        $clientUser = User::factory()->create(['role' => 'client']);
        $this->client = Client::factory()->create([
            'user_id' => $clientUser->id,
        ]);

        // Create service type
        $this->serviceType = ServiceType::factory()->create(['name' => 'Personal Training']);
    }

    // =========================================================================
    // STAFF MY SUMMARY REPORT TESTS
    // =========================================================================

    public function test_staff_can_get_my_summary_grouped_by_site(): void
    {
        // Arrange: Create attended events for this trainer
        Event::factory()->count(3)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'service_type_id' => $this->serviceType->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
            'trainer_fee_brutto' => 5000,
            'entry_fee_brutto' => 8000,
        ]);

        Sanctum::actingAs($this->staffUser);

        // Act
        $response = $this->getJson('/api/v1/reports/my-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'total_hours',
                    'total_sessions',
                    'individual_sessions',
                    'group_sessions',
                    'total_trainer_fee',
                    'total_entry_fee',
                    'currency',
                ],
                'breakdown',
                'filters',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['summary']['total_sessions']);
        $this->assertEquals(3, $data['summary']['individual_sessions']);
        $this->assertEquals(0, $data['summary']['group_sessions']);
    }

    public function test_staff_can_get_my_summary_grouped_by_service_type(): void
    {
        // Arrange
        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'service_type_id' => $this->serviceType->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Sanctum::actingAs($this->staffUser);

        // Act
        $response = $this->getJson('/api/v1/reports/my-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data['breakdown']); // One service type
    }

    public function test_staff_only_sees_own_sessions(): void
    {
        // Arrange: Create events for another trainer
        $otherTrainerUser = User::factory()->create(['role' => 'staff']);
        $otherTrainer = StaffProfile::factory()->create(['user_id' => $otherTrainerUser->id]);

        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Event::factory()->count(5)->create([
            'staff_id' => $otherTrainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Sanctum::actingAs($this->staffUser);

        // Act
        $response = $this->getJson('/api/v1/reports/my-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        // Assert: Should only see 2 sessions (own)
        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(2, $data['summary']['total_sessions']);
    }

    // =========================================================================
    // STAFF MY CLIENTS REPORT TESTS
    // =========================================================================

    public function test_staff_can_get_my_clients(): void
    {
        // Arrange: Create events with multiple clients
        $client2User = User::factory()->create(['role' => 'client']);
        $client2 = Client::factory()->create(['user_id' => $client2User->id]);

        Event::factory()->count(3)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $client2->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Sanctum::actingAs($this->staffUser);

        // Act
        $response = $this->getJson('/api/v1/reports/my-clients?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'total_clients',
                    'total_sessions',
                    'total_individual',
                    'total_group',
                ],
                'clients',
                'filters',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['summary']['total_clients']);
        $this->assertEquals(5, $data['summary']['total_sessions']);
    }

    public function test_staff_clients_sorted_by_total_sessions(): void
    {
        // Arrange
        $client2User = User::factory()->create(['role' => 'client']);
        $client2 = Client::factory()->create(['user_id' => $client2User->id]);

        Event::factory()->count(1)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        Event::factory()->count(5)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $client2->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Sanctum::actingAs($this->staffUser);

        // Act
        $response = $this->getJson('/api/v1/reports/my-clients?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
        ]));

        // Assert: Client with 5 sessions should be first
        $response->assertOk();
        $clients = $response->json('data.clients');
        $this->assertEquals($client2->id, $clients[0]['client_id']);
        $this->assertEquals(5, $clients[0]['total_sessions']);
    }

    // =========================================================================
    // STAFF MY TRENDS REPORT TESTS
    // =========================================================================

    public function test_staff_can_get_my_trends_weekly(): void
    {
        // Arrange
        Event::factory()->count(3)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
            'trainer_fee_brutto' => 5000,
            'entry_fee_brutto' => 8000,
        ]);

        Sanctum::actingAs($this->staffUser);

        // Act
        $response = $this->getJson('/api/v1/reports/my-trends?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'granularity' => 'week',
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'total_sessions',
                    'total_hours',
                    'total_entry_fee',
                    'total_trainer_fee',
                    'average_attendance_rate',
                    'currency',
                ],
                'periods',
                'filters',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['summary']['total_sessions']);
        $this->assertEquals('week', $data['filters']['granularity']);
    }

    public function test_staff_can_get_my_trends_monthly(): void
    {
        Sanctum::actingAs($this->staffUser);

        $response = $this->getJson('/api/v1/reports/my-trends?' . http_build_query([
            'from' => Carbon::now()->subMonths(3)->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'granularity' => 'month',
        ]));

        $response->assertOk();
        $this->assertEquals('month', $response->json('data.filters.granularity'));
    }

    // =========================================================================
    // VALIDATION TESTS
    // =========================================================================

    public function test_my_summary_requires_from_date(): void
    {
        Sanctum::actingAs($this->staffUser);

        $response = $this->getJson('/api/v1/reports/my-summary?' . http_build_query([
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from']);
    }

    public function test_my_summary_requires_valid_group_by(): void
    {
        Sanctum::actingAs($this->staffUser);

        $response = $this->getJson('/api/v1/reports/my-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'invalid',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['groupBy']);
    }

    public function test_my_trends_requires_valid_granularity(): void
    {
        Sanctum::actingAs($this->staffUser);

        $response = $this->getJson('/api/v1/reports/my-trends?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'granularity' => 'daily', // Invalid - should be week or month
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['granularity']);
    }

    // =========================================================================
    // AUTHORIZATION TESTS
    // =========================================================================

    public function test_client_cannot_access_staff_reports(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/reports/my-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_staff_reports(): void
    {
        $response = $this->getJson('/api/v1/reports/my-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertUnauthorized();
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function test_my_summary_returns_empty_when_no_sessions(): void
    {
        Sanctum::actingAs($this->staffUser);

        $response = $this->getJson('/api/v1/reports/my-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(0, $data['summary']['total_sessions']);
        $this->assertEmpty($data['breakdown']);
    }

    public function test_my_clients_returns_empty_when_no_clients(): void
    {
        Sanctum::actingAs($this->staffUser);

        $response = $this->getJson('/api/v1/reports/my-clients?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(0, $data['summary']['total_clients']);
        $this->assertEmpty($data['clients']);
    }
}
