<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CalendarChangeLog;
use App\Models\Client;
use App\Models\Event;
use App\Models\Room;
use App\Models\ServiceType;
use App\Models\Site;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarChangeLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $client;
    private Event $event;
    private Room $room;
    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users with different roles
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->staff = User::factory()->create(['role' => 'staff']);
        StaffProfile::factory()->create(['user_id' => $this->staff->id]);

        $this->client = User::factory()->create(['role' => 'client']);
        Client::factory()->create(['user_id' => $this->client->id]);

        // Create test data
        $this->site = Site::factory()->create();
        $this->room = Room::factory()->create(['site_id' => $this->site->id]);
        $serviceType = ServiceType::factory()->create();

        $this->event = Event::factory()->create([
            'room_id' => $this->room->id,
            'service_type_id' => $serviceType->id,
        ]);
    }

    // Event Lifecycle Logging Tests

    public function test_creates_log_when_event_is_created(): void
    {
        $this->actingAs($this->admin);

        $serviceType = ServiceType::factory()->create();
        $staffProfile = StaffProfile::factory()->create();
        $client = Client::factory()->create();

        $eventData = [
            'type' => 'INDIVIDUAL',
            'status' => 'scheduled',
            'staff_id' => $staffProfile->id,
            'client_id' => $client->id,
            'room_id' => $this->room->id,
            'service_type_id' => $serviceType->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ];

        $event = Event::create($eventData);

        // Observer should have created a log
        $log = CalendarChangeLog::where('entity_id', $event->id)
            ->where('action', 'EVENT_CREATED')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('EVENT_CREATED', $log->action);
        $this->assertEquals($event->id, $log->entity_id);
        $this->assertNull($log->before_json);
        $this->assertNotNull($log->after_json);
    }

    public function test_creates_log_with_correct_snapshot_when_event_is_updated(): void
    {
        $this->actingAs($this->admin);

        // Update event
        $newStartsAt = now()->addDays(2);
        $newEndsAt = $newStartsAt->copy()->addHour();

        $this->event->update([
            'starts_at' => $newStartsAt,
            'ends_at' => $newEndsAt,
        ]);

        // Get the log
        $log = CalendarChangeLog::where('entity_id', $this->event->id)
            ->where('action', 'EVENT_UPDATED')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->before_json);
        $this->assertNotNull($log->after_json);

        // Verify snapshot structure
        $this->assertArrayHasKey('starts_at', $log->before_json);
        $this->assertArrayHasKey('starts_at', $log->after_json);
    }

    public function test_calculates_changed_fields_correctly_on_update(): void
    {
        $this->actingAs($this->admin);

        // Update multiple fields
        $this->event->update([
            'status' => 'cancelled',
            'notes' => 'Client cancelled',
        ]);

        $log = CalendarChangeLog::where('entity_id', $this->event->id)
            ->where('action', 'EVENT_UPDATED')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->changed_fields);
        $this->assertContains('status', $log->changed_fields);
        $this->assertContains('notes', $log->changed_fields);
    }

    public function test_creates_log_when_event_is_deleted(): void
    {
        $this->actingAs($this->admin);

        $eventId = $this->event->id;
        $this->event->delete();

        $log = CalendarChangeLog::where('entity_id', $eventId)
            ->where('action', 'EVENT_DELETED')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('EVENT_DELETED', $log->action);
        $this->assertNotNull($log->before_json);
        $this->assertNull($log->after_json);
    }

    // Snapshot Content Tests

    public function test_includes_all_required_fields_in_snapshot(): void
    {
        $this->actingAs($this->admin);

        $event = Event::factory()->create([
            'room_id' => $this->room->id,
        ]);

        $log = CalendarChangeLog::where('entity_id', $event->id)
            ->where('action', 'EVENT_CREATED')
            ->first();

        $snapshot = $log->after_json;

        // Verify all required fields
        $requiredFields = [
            'id', 'title', 'starts_at', 'ends_at', 'site',
            'room_id', 'room_name', 'trainer_id', 'trainer_name',
            'client_id', 'client_email', 'status'
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $snapshot, "Snapshot missing required field: {$field}");
        }
    }

    // Diff Logic Tests

    public function test_sets_before_json_to_null_on_create(): void
    {
        $this->actingAs($this->admin);

        $event = Event::factory()->create(['room_id' => $this->room->id]);

        $log = CalendarChangeLog::where('entity_id', $event->id)
            ->where('action', 'EVENT_CREATED')
            ->first();

        $this->assertNull($log->before_json);
        $this->assertNotNull($log->after_json);
    }

    public function test_sets_after_json_to_null_on_delete(): void
    {
        $this->actingAs($this->admin);

        $eventId = $this->event->id;
        $this->event->delete();

        $log = CalendarChangeLog::where('entity_id', $eventId)
            ->where('action', 'EVENT_DELETED')
            ->first();

        $this->assertNotNull($log->before_json);
        $this->assertNull($log->after_json);
    }

    public function test_populates_both_before_and_after_on_update(): void
    {
        $this->actingAs($this->admin);

        $this->event->update(['notes' => 'Test note']);

        $log = CalendarChangeLog::where('entity_id', $this->event->id)
            ->where('action', 'EVENT_UPDATED')
            ->latest()
            ->first();

        $this->assertNotNull($log->before_json);
        $this->assertNotNull($log->after_json);
        $this->assertIsArray($log->changed_fields);
    }

    // API Endpoints Tests

    public function test_admin_can_list_all_calendar_changes(): void
    {
        CalendarChangeLog::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/calendar-changes');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'changed_at',
                        'action',
                        'entity_type',
                        'entity_id',
                        'actor_name',
                        'actor_role',
                        'site',
                        'room_name',
                        'starts_at',
                        'ends_at',
                    ],
                ],
                'meta' => [
                    'page',
                    'perPage',
                    'total',
                    'lastPage',
                ],
            ]);

        $this->assertGreaterThanOrEqual(5, $response->json('meta.total'));
    }

    public function test_admin_can_filter_by_site(): void
    {
        CalendarChangeLog::factory()->atSite('SASAD')->count(3)->create();
        CalendarChangeLog::factory()->atSite('TB')->count(2)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/calendar-changes?site=SASAD');

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('SASAD', $item['site']);
        }
    }

    public function test_admin_can_filter_by_room(): void
    {
        $room1 = Room::factory()->create();
        $room2 = Room::factory()->create();

        CalendarChangeLog::factory()->inRoom($room1)->count(3)->create();
        CalendarChangeLog::factory()->inRoom($room2)->count(2)->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/calendar-changes?roomId={$room1->id}");

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals($room1->id, $item['room_id']);
        }
    }

    public function test_admin_can_filter_by_action_type(): void
    {
        CalendarChangeLog::factory()->created()->count(3)->create();
        CalendarChangeLog::factory()->updated()->count(2)->create();
        CalendarChangeLog::factory()->deleted()->count(1)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/calendar-changes?action=EVENT_CREATED');

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('EVENT_CREATED', $item['action']);
        }
    }

    public function test_admin_can_filter_by_date_range(): void
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now()->subDays(1);

        CalendarChangeLog::factory()
            ->changedAt($startDate->copy()->addDays(2))
            ->count(3)
            ->create();

        CalendarChangeLog::factory()
            ->changedAt(Carbon::now())
            ->count(2)
            ->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/calendar-changes?' . http_build_query([
                'changedFrom' => $startDate->toIso8601String(),
                'changedTo' => $endDate->toIso8601String(),
            ]));

        $response->assertOk();

        $this->assertGreaterThanOrEqual(3, $response->json('meta.total'));
    }

    public function test_admin_can_view_change_detail(): void
    {
        $log = CalendarChangeLog::factory()->updated()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/calendar-changes/{$log->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'id',
                'changed_at',
                'action',
                'entity_type',
                'entity_id',
                'actor_user_id',
                'actor_name',
                'actor_role',
                'site',
                'room_id',
                'room_name',
                'starts_at',
                'ends_at',
                'before_json',
                'after_json',
                'changed_fields',
                'ip_address',
                'user_agent',
            ])
            ->assertJsonPath('id', $log->id);
    }

    public function test_staff_can_only_see_their_own_changes(): void
    {
        // Create changes by different users
        CalendarChangeLog::factory()->byActor($this->staff)->count(3)->create();
        CalendarChangeLog::factory()->byActor($this->admin)->count(2)->create();

        $response = $this->actingAs($this->staff)
            ->getJson('/api/v1/staff/calendar-changes');

        $response->assertOk();

        $data = $response->json('data');

        // All returned changes should be by this staff member
        foreach ($data as $item) {
            $this->assertEquals($this->staff->id, $item['actor_user_id']);
        }

        // Should only see their own changes (3)
        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_client_cannot_access_calendar_changes(): void
    {
        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/admin/calendar-changes');

        $response->assertForbidden();
    }

    // RBAC Tests

    public function test_returns_403_for_unauthenticated_user(): void
    {
        $response = $this->getJson('/api/v1/admin/calendar-changes');

        $response->assertUnauthorized();
    }

    public function test_returns_403_for_client_role(): void
    {
        $response = $this->actingAs($this->client)
            ->getJson('/api/v1/admin/calendar-changes');

        $response->assertForbidden();
    }

    public function test_staff_cannot_access_admin_endpoint(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson('/api/v1/admin/calendar-changes');

        $response->assertForbidden();
    }

    public function test_admin_has_full_access(): void
    {
        CalendarChangeLog::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/calendar-changes');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(5, $response->json('meta.total'));
    }

    // Edge Cases and Additional Tests

    public function test_pagination_works_correctly(): void
    {
        CalendarChangeLog::factory()->count(25)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/calendar-changes?perPage=10&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.perPage', 10)
            ->assertJsonPath('meta.page', 1);

        $this->assertCount(10, $response->json('data'));
    }

    public function test_sorting_works_correctly(): void
    {
        // Create logs with different timestamps
        CalendarChangeLog::factory()
            ->changedAt(Carbon::now()->subDays(3))
            ->create();
        CalendarChangeLog::factory()
            ->changedAt(Carbon::now()->subDays(1))
            ->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/calendar-changes?sort=changed_at&order=asc');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));

        // Verify ascending order
        for ($i = 1; $i < count($data); $i++) {
            $prev = Carbon::parse($data[$i - 1]['changed_at']);
            $current = Carbon::parse($data[$i]['changed_at']);
            $this->assertLessThanOrEqual($current->timestamp, $prev->timestamp);
        }
    }

    public function test_handles_missing_relationships_gracefully(): void
    {
        // Create event without some relationships
        $event = new Event([
            'type' => 'BLOCK',
            'status' => 'scheduled',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);
        $event->save();

        $log = CalendarChangeLog::where('entity_id', $event->id)->first();

        // Should still create log even with null relationships
        $this->assertNotNull($log);
    }

    public function test_multiple_filters_work_together(): void
    {
        $room = Room::factory()->create();
        $site = $room->site->name;

        CalendarChangeLog::factory()
            ->updated()
            ->inRoom($room)
            ->count(3)
            ->create();

        CalendarChangeLog::factory()
            ->created()
            ->inRoom($room)
            ->count(2)
            ->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/admin/calendar-changes?roomId={$room->id}&action=EVENT_UPDATED");

        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals($room->id, $item['room_id']);
            $this->assertEquals('EVENT_UPDATED', $item['action']);
        }
    }

    public function test_returns_empty_result_when_no_matches(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/admin/calendar-changes?site=NONEXISTENT');

        $response->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
    }
}
