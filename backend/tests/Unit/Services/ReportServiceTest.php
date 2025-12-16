<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ReportService;
use App\Models\User;
use App\Models\Event;
use App\Models\Room;
use App\Models\Site;
use App\Models\Client;
use App\Models\Pass;
use App\Models\StaffProfile;
use App\Models\ServiceType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $reportService;
    private StaffProfile $trainer;
    private Client $client;
    private Room $room;
    private Site $site;
    private ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportService = app(ReportService::class);

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
    // generateAdminTrainerSummary TESTS
    // =========================================================================

    public function test_generate_admin_trainer_summary_returns_correct_structure(): void
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

        // Act
        $result = $this->reportService->generateAdminTrainerSummary(
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            groupBy: 'site'
        );

        // Assert
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('trainers', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertArrayHasKey('total_trainer_fee', $result['summary']);
        $this->assertArrayHasKey('total_entry_fee', $result['summary']);
        $this->assertArrayHasKey('total_sessions', $result['summary']);
    }

    public function test_generate_admin_trainer_summary_calculates_totals_correctly(): void
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

        // Act
        $result = $this->reportService->generateAdminTrainerSummary(
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            groupBy: 'site'
        );

        // Assert
        $this->assertEquals(15000, $result['summary']['total_trainer_fee']); // 3 * 5000
        $this->assertEquals(24000, $result['summary']['total_entry_fee']); // 3 * 8000
        $this->assertEquals(3, $result['summary']['total_sessions']);
    }

    public function test_generate_admin_trainer_summary_filters_by_trainer_id(): void
    {
        // Arrange: Create two trainers with events
        $otherTrainerUser = User::factory()->create(['role' => 'staff']);
        $otherTrainer = StaffProfile::factory()->create(['user_id' => $otherTrainerUser->id]);

        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        Event::factory()->count(5)->create([
            'staff_id' => $otherTrainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        // Act
        $result = $this->reportService->generateAdminTrainerSummary(
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            groupBy: 'site',
            trainerId: $this->trainer->id
        );

        // Assert
        $this->assertEquals(2, $result['summary']['total_sessions']);
        $this->assertEquals(1, $result['summary']['trainer_count']);
    }

    // =========================================================================
    // generateAdminSiteClientList TESTS
    // =========================================================================

    public function test_generate_admin_site_client_list_returns_correct_structure(): void
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
        ]);

        // Act
        $result = $this->reportService->generateAdminSiteClientList(
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            siteId: $this->site->id
        );

        // Assert
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('clients', $result);
        $this->assertArrayHasKey('filters', $result);
        $this->assertEquals($this->site->id, $result['summary']['site_id']);
    }

    // =========================================================================
    // generateAdminFinanceOverview TESTS
    // =========================================================================

    public function test_generate_admin_finance_overview_calculates_net_income(): void
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

        // Act
        $result = $this->reportService->generateAdminFinanceOverview(
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            groupBy: 'month'
        );

        // Assert
        $this->assertEquals(40000, $result['summary']['total_entry_fee']);
        $this->assertEquals(25000, $result['summary']['total_trainer_fee']);
        $this->assertEquals(15000, $result['summary']['net_income']); // 40000 - 25000
    }

    // =========================================================================
    // generateStaffMySummary TESTS
    // =========================================================================

    public function test_generate_staff_my_summary_scopes_to_staff_id(): void
    {
        // Arrange: Create another trainer
        $otherTrainerUser = User::factory()->create(['role' => 'staff']);
        $otherTrainer = StaffProfile::factory()->create(['user_id' => $otherTrainerUser->id]);

        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        Event::factory()->count(5)->create([
            'staff_id' => $otherTrainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        // Act
        $result = $this->reportService->generateStaffMySummary(
            staffId: $this->trainer->id,
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            groupBy: 'site'
        );

        // Assert: Should only see own 2 sessions
        $this->assertEquals(2, $result['summary']['total_sessions']);
    }

    // =========================================================================
    // generateStaffMyClients TESTS
    // =========================================================================

    public function test_generate_staff_my_clients_lists_unique_clients(): void
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

        // Act
        $result = $this->reportService->generateStaffMyClients(
            staffId: $this->trainer->id,
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d')
        );

        // Assert
        $this->assertEquals(2, $result['summary']['total_clients']);
        $this->assertEquals(5, $result['summary']['total_sessions']);
    }

    // =========================================================================
    // generateClientMyActivity TESTS
    // =========================================================================

    public function test_generate_client_my_activity_calculates_attendance_rate(): void
    {
        // Arrange: Mix of attended and no-show
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

        // Act
        $result = $this->reportService->generateClientMyActivity(
            clientId: $this->client->id,
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            groupBy: 'service_type'
        );

        // Assert
        $this->assertEquals(5, $result['summary']['total_sessions']);
        $this->assertEquals(3, $result['summary']['attended']);
        $this->assertEquals(2, $result['summary']['no_show']);
        $this->assertEquals(60.0, $result['summary']['attendance_rate']); // 3/5 * 100
    }

    // =========================================================================
    // generateClientMyFinance TESTS
    // =========================================================================

    public function test_generate_client_my_finance_includes_passes_and_credits(): void
    {
        // Arrange
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

        // Act
        $result = $this->reportService->generateClientMyFinance(
            clientId: $this->client->id,
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            groupBy: 'month'
        );

        // Assert
        $this->assertEquals(1, $result['summary']['total_passes_purchased']);
        $this->assertEquals(25000, $result['summary']['total_amount_spent']);
        $this->assertEquals(10, $result['summary']['total_credits_purchased']);
        $this->assertEquals(3, $result['summary']['total_credits_used']);
        $this->assertCount(1, $result['active_passes']);
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    public function test_empty_date_range_returns_zero_values(): void
    {
        $result = $this->reportService->generateAdminTrainerSummary(
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->format('Y-m-d'),
            groupBy: 'site'
        );

        $this->assertEquals(0, $result['summary']['total_sessions']);
        $this->assertEquals(0, $result['summary']['total_trainer_fee']);
        $this->assertEquals(0, $result['summary']['trainer_count']);
    }

    public function test_only_attended_events_counted_in_trainer_summary(): void
    {
        // Arrange: Create attended and non-attended events
        Event::factory()->count(3)->create([
            'staff_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'attended',
            'starts_at' => Carbon::now()->subDays(5),
            'ends_at' => Carbon::now()->subDays(5)->addHour(),
        ]);

        Event::factory()->count(2)->create([
            'staff_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'no_show',
            'starts_at' => Carbon::now()->subDays(3),
            'ends_at' => Carbon::now()->subDays(3)->addHour(),
        ]);

        Event::factory()->count(1)->create([
            'staff_id' => $this->trainer->id,
            'room_id' => $this->room->id,
            'attendance_status' => 'scheduled',
            'starts_at' => Carbon::now()->addDays(5),
            'ends_at' => Carbon::now()->addDays(5)->addHour(),
        ]);

        // Act
        $result = $this->reportService->generateAdminTrainerSummary(
            from: Carbon::now()->subMonth()->format('Y-m-d'),
            to: Carbon::now()->addMonth()->format('Y-m-d'),
            groupBy: 'site'
        );

        // Assert: Should only count 3 attended sessions
        $this->assertEquals(3, $result['summary']['total_sessions']);
    }
}
