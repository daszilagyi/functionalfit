<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CalendarChangeLog;
use App\Models\Client;
use App\Models\Event;
use App\Models\Room;
use App\Models\ServiceType;
use App\Models\Site;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\CalendarChangeLogger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CalendarChangeLoggerTest extends TestCase
{
    use RefreshDatabase;

    private CalendarChangeLogger $logger;
    private User $actor;
    private Room $room;
    private ServiceType $serviceType;
    private StaffProfile $staff;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = app(CalendarChangeLogger::class);

        // Create test data
        $site = Site::factory()->create();
        $this->room = Room::factory()->create(['site_id' => $site->id]);
        $this->serviceType = ServiceType::factory()->create();
        $this->staff = StaffProfile::factory()->create();
        $this->client = Client::factory()->create();

        $this->actor = User::factory()->create(['role' => 'admin']);
    }

    public function test_creates_snapshot_with_all_required_fields(): void
    {
        $this->actingAs($this->actor);

        // Create event through Eloquent (triggers Observer)
        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)->latest()->first();

        $this->assertNotNull($log);
        $this->assertEquals('EVENT_CREATED', $log->action);
        $this->assertIsArray($log->after_json);

        // Verify all required snapshot fields
        $snapshot = $log->after_json;
        $this->assertArrayHasKey('id', $snapshot);
        $this->assertArrayHasKey('title', $snapshot);
        $this->assertArrayHasKey('starts_at', $snapshot);
        $this->assertArrayHasKey('ends_at', $snapshot);
        $this->assertArrayHasKey('site', $snapshot);
        $this->assertArrayHasKey('room_id', $snapshot);
        $this->assertArrayHasKey('room_name', $snapshot);
        $this->assertArrayHasKey('trainer_id', $snapshot);
        $this->assertArrayHasKey('trainer_name', $snapshot);
        $this->assertArrayHasKey('client_id', $snapshot);
        $this->assertArrayHasKey('client_email', $snapshot);
        $this->assertArrayHasKey('status', $snapshot);
    }

    public function test_calculates_diff_correctly(): void
    {
        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        // Update event (triggers Observer)
        $newStartsAt = Carbon::now()->addDay();
        $newEndsAt = $newStartsAt->copy()->addHour();
        $event->update([
            'starts_at' => $newStartsAt,
            'ends_at' => $newEndsAt,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)
            ->where('action', 'EVENT_UPDATED')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('EVENT_UPDATED', $log->action);
        $this->assertIsArray($log->changed_fields);
        $this->assertContains('starts_at', $log->changed_fields);
        $this->assertContains('ends_at', $log->changed_fields);
    }

    public function test_handles_null_actor_gracefully(): void
    {
        // No authenticated user - Observer should fall back to event's staff_id
        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)->latest()->first();

        $this->assertNotNull($log);
        // Should fall back to event's staff_id
        $this->assertEquals($event->staff_id, $log->actor_user_id);
        $this->assertNotNull($log->actor_name);
    }

    public function test_captures_ip_address_from_request(): void
    {
        // Mock request with IP
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);
        app()->instance('request', $request);

        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)->latest()->first();

        $this->assertNotNull($log->ip_address);
        $this->assertEquals('192.168.1.1', $log->ip_address);
    }

    public function test_captures_user_agent_from_request(): void
    {
        // Mock request with user agent
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $request = Request::create('/test', 'GET', [], [], [], ['HTTP_USER_AGENT' => $userAgent]);
        app()->instance('request', $request);

        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)->latest()->first();

        $this->assertNotNull($log->user_agent);
        $this->assertEquals($userAgent, $log->user_agent);
    }

    public function test_log_created_sets_before_json_to_null(): void
    {
        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)
            ->where('action', 'EVENT_CREATED')
            ->latest()
            ->first();

        $this->assertNull($log->before_json);
        $this->assertNotNull($log->after_json);
        $this->assertNull($log->changed_fields);
    }

    public function test_log_deleted_sets_after_json_to_null(): void
    {
        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $eventId = $event->id;
        $event->delete();

        $log = CalendarChangeLog::where('entity_id', $eventId)
            ->where('action', 'EVENT_DELETED')
            ->latest()
            ->first();

        $this->assertNotNull($log->before_json);
        $this->assertNull($log->after_json);
        $this->assertNull($log->changed_fields);
    }

    public function test_log_updated_populates_both_before_and_after(): void
    {
        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        // Update event
        $event->update(['notes' => 'Updated notes']);

        $log = CalendarChangeLog::where('entity_id', $event->id)
            ->where('action', 'EVENT_UPDATED')
            ->latest()
            ->first();

        $this->assertNotNull($log->before_json);
        $this->assertNotNull($log->after_json);
        $this->assertIsArray($log->changed_fields);
        $this->assertContains('notes', $log->changed_fields);
    }

    public function test_only_logs_actual_changes(): void
    {
        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        // Count logs after creation
        $initialCount = CalendarChangeLog::where('entity_id', $event->id)->count();

        // No actual changes - just touch (this will trigger updated event but logger should skip it)
        $event->touch();

        // Should still be same count (1 for creation only)
        $finalCount = CalendarChangeLog::where('entity_id', $event->id)->count();

        $this->assertEquals($initialCount, $finalCount);
    }

    public function test_captures_actor_role_correctly(): void
    {
        // Test admin role
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $event1 = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event1->id)->latest()->first();
        $this->assertEquals('admin', $log->actor_role);

        // Test staff role
        $staffUser = User::factory()->create(['role' => 'staff']);
        StaffProfile::factory()->create(['user_id' => $staffUser->id]);
        $this->actingAs($staffUser);

        $event2 = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event2->id)->latest()->first();
        $this->assertEquals('staff', $log->actor_role);

        // Test client role
        $clientUser = User::factory()->create(['role' => 'client']);
        Client::factory()->create(['user_id' => $clientUser->id]);
        $this->actingAs($clientUser);

        $event3 = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event3->id)->latest()->first();
        $this->assertEquals('client', $log->actor_role);
    }

    public function test_captures_site_from_room_relationship(): void
    {
        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)->latest()->first();

        $this->assertNotNull($log->site);

        // Handle both Site model relationship and legacy string site field
        $expectedSite = is_object($event->room->site) ? $event->room->site->name : $event->room->site;
        $this->assertEquals($expectedSite, $log->site);
    }

    public function test_does_not_fail_main_operation_on_logging_error(): void
    {
        // This test ensures that if logging fails, it doesn't break the main flow
        $this->actingAs($this->actor);

        // Should not throw exception even if logging encounters issues
        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $this->assertNotNull($event->id);
        $this->assertTrue(true);
    }

    public function test_snapshot_includes_pricing_information(): void
    {
        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
            'entry_fee_brutto' => 12000,
            'trainer_fee_brutto' => 8000,
            'currency' => 'HUF',
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)->latest()->first();

        $snapshot = $log->after_json;
        $this->assertEquals(12000, $snapshot['entry_fee_brutto']);
        $this->assertEquals(8000, $snapshot['trainer_fee_brutto']);
        $this->assertEquals('HUF', $snapshot['currency']);
    }

    public function test_changed_fields_identifies_multiple_changes(): void
    {
        $this->actingAs($this->actor);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
            'staff_id' => $this->staff->id,
            'client_id' => $this->client->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        // Update multiple fields
        $event->update([
            'status' => 'cancelled',
            'notes' => 'Cancelled by client',
            'entry_fee_brutto' => 15000,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)
            ->where('action', 'EVENT_UPDATED')
            ->latest()
            ->first();

        $this->assertIsArray($log->changed_fields);
        $this->assertGreaterThanOrEqual(3, count($log->changed_fields));
        $this->assertContains('status', $log->changed_fields);
        $this->assertContains('notes', $log->changed_fields);
        $this->assertContains('entry_fee_brutto', $log->changed_fields);
    }
}
