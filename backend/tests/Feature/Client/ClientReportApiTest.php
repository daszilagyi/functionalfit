<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Room;
use App\Models\Site;
use App\Models\Client;
use App\Models\Pass;
use App\Models\StaffProfile;
use App\Models\ServiceType;
use App\Models\ClassOccurrence;
use App\Models\ClassTemplate;
use App\Models\ClassRegistration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ClientReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Client $client;
    private StaffProfile $trainer;
    private Site $site;
    private Room $room;
    private ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create client user with profile
        $this->clientUser = User::factory()->create(['role' => 'client']);
        $this->client = Client::factory()->create([
            'user_id' => $this->clientUser->id,
        ]);

        // Create trainer
        $trainerUser = User::factory()->create(['role' => 'staff']);
        $this->trainer = StaffProfile::factory()->create([
            'user_id' => $trainerUser->id,
        ]);

        // Create site and room
        $this->site = Site::factory()->create(['name' => 'Test Site']);
        $this->room = Room::factory()->create([
            'site_id' => $this->site->id,
            'name' => 'Test Room',
        ]);

        // Create service type
        $this->serviceType = ServiceType::factory()->create(['name' => 'Personal Training']);
    }

    // =========================================================================
    // CLIENT MY ACTIVITY REPORT TESTS
    // =========================================================================

    public function test_client_can_get_my_activity_grouped_by_service_type(): void
    {
        // Arrange: Create attended events for this client
        Event::factory()->count(3)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'service_type_id' => $this->serviceType->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        Sanctum::actingAs($this->clientUser);

        // Act
        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'total_sessions',
                    'attended',
                    'no_show',
                    'attendance_rate',
                    'total_credits_used',
                ],
                'breakdown',
                'filters',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['summary']['total_sessions']);
        $this->assertEquals(3, $data['summary']['attended']);
        $this->assertEquals(0, $data['summary']['no_show']);
        $this->assertEquals(100.0, $data['summary']['attendance_rate']);
    }

    public function test_client_can_get_my_activity_grouped_by_month(): void
    {
        // Arrange
        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(10),
            'ends_at' => Carbon::now()->subDays(10)->addHour(),
        ]);

        Sanctum::actingAs($this->clientUser);

        // Act
        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'month',
        ]));

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('month', $data['filters']['group_by']);
    }

    public function test_client_activity_includes_no_shows(): void
    {
        // Arrange: Create mix of attended and no-show events
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
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'no_show',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Sanctum::actingAs($this->clientUser);

        // Act
        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(5, $data['summary']['total_sessions']);
        $this->assertEquals(3, $data['summary']['attended']);
        $this->assertEquals(2, $data['summary']['no_show']);
        $this->assertEquals(60.0, $data['summary']['attendance_rate']); // 3/5 * 100
    }

    public function test_client_only_sees_own_activity(): void
    {
        // Arrange: Create events for another client
        $otherClientUser = User::factory()->create(['role' => 'client']);
        $otherClient = Client::factory()->create(['user_id' => $otherClientUser->id]);

        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        Event::factory()->count(5)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $otherClient->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Sanctum::actingAs($this->clientUser);

        // Act
        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        // Assert: Should only see 2 sessions (own)
        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(2, $data['summary']['total_sessions']);
    }

    // =========================================================================
    // CLIENT MY FINANCE REPORT TESTS
    // =========================================================================

    public function test_client_can_get_my_finance(): void
    {
        // Arrange: Create pass and events
        Pass::factory()->create([
            'client_id' => $this->client->id,
            'total_credits' => 10,
            'remaining_credits' => 7,
            'price' => 25000,
            'status' => 'active',
            'created_at' => Carbon::now()->subDays(15),
        ]);

        Event::factory()->count(3)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        Sanctum::actingAs($this->clientUser);

        // Act
        $response = $this->getJson('/api/v1/reports/client/my-finance?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'month',
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'total_passes_purchased',
                    'total_amount_spent',
                    'total_credits_purchased',
                    'total_credits_used',
                    'currency',
                ],
                'breakdown',
                'active_passes',
                'filters',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['summary']['total_passes_purchased']);
        $this->assertEquals(25000, $data['summary']['total_amount_spent']);
        $this->assertEquals(10, $data['summary']['total_credits_purchased']);
        $this->assertEquals(3, $data['summary']['total_credits_used']);
    }

    public function test_client_finance_shows_active_passes(): void
    {
        // Arrange: Create active and inactive passes
        Pass::factory()->create([
            'client_id' => $this->client->id,
            'total_credits' => 10,
            'remaining_credits' => 5,
            'status' => 'active',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        Pass::factory()->create([
            'client_id' => $this->client->id,
            'total_credits' => 5,
            'remaining_credits' => 0,
            'status' => 'expired',
            'created_at' => Carbon::now()->subDays(60),
        ]);

        Sanctum::actingAs($this->clientUser);

        // Act
        $response = $this->getJson('/api/v1/reports/client/my-finance?' . http_build_query([
            'from' => Carbon::now()->subMonths(3)->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'month',
        ]));

        // Assert: Should show 1 active pass
        $response->assertOk();
        $activePasses = $response->json('data.active_passes');
        $this->assertCount(1, $activePasses);
        $this->assertEquals(5, $activePasses[0]['remaining_credits']);
    }

    // =========================================================================
    // VALIDATION TESTS
    // =========================================================================

    public function test_my_activity_requires_from_date(): void
    {
        Sanctum::actingAs($this->clientUser);

        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from']);
    }

    public function test_my_activity_requires_valid_group_by(): void
    {
        Sanctum::actingAs($this->clientUser);

        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'week', // Invalid - should be service_type or month
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['groupBy']);
    }

    public function test_my_finance_requires_valid_group_by(): void
    {
        Sanctum::actingAs($this->clientUser);

        $response = $this->getJson('/api/v1/reports/client/my-finance?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'week', // Invalid - should be month only
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['groupBy']);
    }

    // =========================================================================
    // AUTHORIZATION TESTS
    // =========================================================================

    public function test_staff_cannot_access_client_reports(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_client_reports(): void
    {
        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        $response->assertUnauthorized();
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function test_my_activity_returns_empty_when_no_sessions(): void
    {
        Sanctum::actingAs($this->clientUser);

        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(0, $data['summary']['total_sessions']);
        $this->assertEquals(0, $data['summary']['attendance_rate']);
        $this->assertEmpty($data['breakdown']);
    }

    public function test_my_finance_returns_empty_when_no_passes(): void
    {
        Sanctum::actingAs($this->clientUser);

        $response = $this->getJson('/api/v1/reports/client/my-finance?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'month',
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(0, $data['summary']['total_passes_purchased']);
        $this->assertEquals(0, $data['summary']['total_credits_used']);
        $this->assertEmpty($data['active_passes']);
    }

    public function test_date_range_validation(): void
    {
        Sanctum::actingAs($this->clientUser);

        // to date before from date
        $response = $this->getJson('/api/v1/reports/client/my-activity?' . http_build_query([
            'from' => Carbon::now()->format('Y-m-d'),
            'to' => Carbon::now()->subMonth()->format('Y-m-d'),
            'groupBy' => 'service_type',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to']);
    }
}
