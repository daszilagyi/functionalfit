<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Room;
use App\Models\Site;
use App\Models\Client;
use App\Models\StaffProfile;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AdminReportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Site $site;
    private Room $room;
    private StaffProfile $trainer;
    private Client $client;
    private ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create(['role' => 'admin']);

        // Create site and room
        $this->site = Site::factory()->create(['name' => 'Test Site']);
        $this->room = Room::factory()->create([
            'site_id' => $this->site->id,
            'name' => 'Test Room',
        ]);

        // Create trainer
        $trainerUser = User::factory()->create(['role' => 'staff']);
        $this->trainer = StaffProfile::factory()->create([
            'user_id' => $trainerUser->id,
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
    // TRAINER SUMMARY REPORT TESTS
    // =========================================================================

    public function test_admin_can_get_trainer_summary_grouped_by_site(): void
    {
        // Arrange: Create attended events
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

        Sanctum::actingAs($this->admin);

        // Act
        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
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
                    'total_trainer_fee',
                    'total_entry_fee',
                    'total_hours',
                    'total_sessions',
                    'trainer_count',
                    'currency',
                ],
                'trainers',
                'filters',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['summary']['total_sessions']);
        $this->assertEquals(15000, $data['summary']['total_trainer_fee']); // 3 * 5000
        $this->assertEquals(24000, $data['summary']['total_entry_fee']); // 3 * 8000
    }

    public function test_admin_can_get_trainer_summary_grouped_by_room(): void
    {
        // Arrange
        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Sanctum::actingAs($this->admin);

        // Act
        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'room',
        ]));

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(2, $data['summary']['total_sessions']);
    }

    public function test_admin_can_filter_trainer_summary_by_trainer_id(): void
    {
        // Arrange: Create another trainer with events
        $otherTrainerUser = User::factory()->create(['role' => 'staff']);
        $otherTrainer = StaffProfile::factory()->create(['user_id' => $otherTrainerUser->id]);

        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Event::factory()->count(3)->create([
            'staff_id' => $otherTrainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Sanctum::actingAs($this->admin);

        // Act: Filter by specific trainer
        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
            'trainerId' => $this->trainer->id,
        ]));

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(2, $data['summary']['total_sessions']);
        $this->assertEquals(1, $data['summary']['trainer_count']);
    }

    public function test_trainer_summary_requires_from_date(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from']);
    }

    public function test_trainer_summary_requires_valid_group_by(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'invalid',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['groupBy']);
    }

    // =========================================================================
    // SITE CLIENT LIST REPORT TESTS
    // =========================================================================

    public function test_admin_can_get_site_client_list(): void
    {
        // Arrange
        Event::factory()->count(3)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'service_type_id' => $this->serviceType->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
            'entry_fee_brutto' => 5000,
        ]);

        Sanctum::actingAs($this->admin);

        // Act
        $response = $this->getJson('/api/v1/reports/admin/site-client-list?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'site' => $this->site->id,
        ]));

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'site_id',
                    'site_name',
                    'total_clients',
                    'total_entry_fee',
                    'total_hours',
                    'total_sessions',
                    'currency',
                ],
                'clients',
                'filters',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals($this->site->id, $data['summary']['site_id']);
        $this->assertEquals(1, $data['summary']['total_clients']);
        $this->assertEquals(15000, $data['summary']['total_entry_fee']); // 3 * 5000
    }

    public function test_site_client_list_requires_site_id(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/reports/admin/site-client-list?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site']);
    }

    // =========================================================================
    // FINANCE OVERVIEW REPORT TESTS
    // =========================================================================

    public function test_admin_can_get_finance_overview_grouped_by_month(): void
    {
        // Arrange
        Event::factory()->count(5)->create([
            'staff_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(10),
            'ends_at' => Carbon::now()->subDays(10)->addHour(),
            'entry_fee_brutto' => 8000,
            'trainer_fee_brutto' => 5000,
        ]);

        Sanctum::actingAs($this->admin);

        // Act
        $response = $this->getJson('/api/v1/reports/admin/finance-overview?' . http_build_query([
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
                    'total_entry_fee',
                    'total_trainer_fee',
                    'net_income',
                    'total_sessions',
                    'total_hours',
                    'average_attendance_rate',
                    'currency',
                ],
                'periods',
                'filters',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(40000, $data['summary']['total_entry_fee']); // 5 * 8000
        $this->assertEquals(25000, $data['summary']['total_trainer_fee']); // 5 * 5000
        $this->assertEquals(15000, $data['summary']['net_income']); // 40000 - 25000
    }

    public function test_admin_can_get_finance_overview_grouped_by_week(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/reports/admin/finance-overview?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'week',
        ]));

        $response->assertOk();
        $this->assertEquals('week', $response->json('data.filters.group_by'));
    }

    public function test_finance_overview_requires_valid_group_by(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/reports/admin/finance-overview?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'year', // Invalid - should be month, week, or day
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['groupBy']);
    }

    // =========================================================================
    // AUTHORIZATION TESTS
    // =========================================================================

    public function test_staff_cannot_access_admin_reports(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertForbidden();
    }

    public function test_client_cannot_access_admin_reports(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $response = $this->getJson('/api/v1/reports/admin/finance-overview?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'month',
        ]));

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_admin_reports(): void
    {
        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertUnauthorized();
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function test_trainer_summary_returns_empty_when_no_data(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
            'from' => Carbon::now()->subMonth()->format('Y-m-d'),
            'to' => Carbon::now()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(0, $data['summary']['total_sessions']);
        $this->assertEquals(0, $data['summary']['trainer_count']);
    }

    public function test_date_range_validation(): void
    {
        Sanctum::actingAs($this->admin);

        // to date before from date
        $response = $this->getJson('/api/v1/reports/admin/trainer-summary?' . http_build_query([
            'from' => Carbon::now()->format('Y-m-d'),
            'to' => Carbon::now()->subMonth()->format('Y-m-d'),
            'groupBy' => 'site',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to']);
    }
}
