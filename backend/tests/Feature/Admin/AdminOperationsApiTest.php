<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Room;
use App\Models\ClassTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AdminOperationsApiTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test admin can list all users
     */
    public function test_admin_can_list_all_users(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(5)->create(['role' => 'client']);
        User::factory()->count(3)->create(['role' => 'staff']);

        // Act
        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/users');

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'role',
                        'name',
                        'email',
                        'status',
                    ]
                ],
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ]
        ]);
    }

    /**
     * Test admin can filter users by role
     */
    public function test_admin_can_filter_users_by_role(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'staff']);
        User::factory()->count(5)->create(['role' => 'client']);

        // Act
        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/users?role=staff');

        // Assert
        $response->assertOk();
        $data = $response->json('data.data'); // ApiResponse wraps paginated data in 'success' + 'data'
        $this->assertCount(3, $data);
        $this->assertTrue(collect($data)->every(fn($user) => $user['role'] === 'staff'));
    }

    /**
     * Test admin can create a new room
     */
    public function test_admin_can_create_room(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $site = \App\Models\Site::factory()->create();

        $roomData = [
            'site_id' => $site->id,
            'name' => 'Test Gym',
            'capacity' => 15,
            'color' => '#FF5733',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/v1/admin/rooms', $roomData);

        // Assert
        $response->assertCreated();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'site_id',
                'name',
                'capacity',
                'color',
            ]
        ]);

        $this->assertDatabaseHas('rooms', [
            'site_id' => $site->id,
            'name' => 'Test Gym',
            'capacity' => 15,
        ]);
    }

    /**
     * Test admin can update a room
     */
    public function test_admin_can_update_room(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $site = \App\Models\Site::factory()->create();
        $room = Room::factory()->create([
            'site_id' => $site->id,
            'name' => 'Old Name',
        ]);

        $updateData = [
            'name' => 'Updated Gym Name',
            'capacity' => 20,
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/rooms/{$room->id}", $updateData);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'name' => 'Updated Gym Name',
            'capacity' => 20,
        ]);
    }

    /**
     * Test admin can delete a room
     */
    public function test_admin_can_delete_room(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $room = Room::factory()->create();

        // Act
        Sanctum::actingAs($admin);
        $response = $this->deleteJson("/api/v1/admin/rooms/{$room->id}");

        // Assert
        $response->assertNoContent();
        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
    }

    /**
     * Test admin can list class templates
     */
    public function test_admin_can_list_class_templates(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        ClassTemplate::factory()->count(5)->create(['status' => 'active']);
        ClassTemplate::factory()->count(2)->create(['status' => 'inactive']);

        // Act
        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/class-templates');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(7, 'data');
    }

    /**
     * Test admin can filter class templates by active status
     */
    public function test_admin_can_filter_class_templates_by_status(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        ClassTemplate::factory()->count(3)->create(['status' => 'active']);
        ClassTemplate::factory()->count(2)->create(['status' => 'inactive']);

        // Act
        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/admin/class-templates?status=active');

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertTrue(collect($data)->every(fn($template) => $template['status'] === 'active'));
    }

    /**
     * Test admin can create a class template
     */
    public function test_admin_can_create_class_template(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $room = \App\Models\Room::factory()->create();
        $trainer = \App\Models\StaffProfile::factory()->create();

        $templateData = [
            'title' => 'HIIT Training',
            'description' => 'High Intensity Interval Training',
            'duration_min' => 45,
            'capacity' => 12,
            'credits_required' => 1,
            'base_price_huf' => 2500,
            'room_id' => $room->id,
            'trainer_id' => $trainer->id,
            'weekly_rrule' => 'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            'status' => 'active',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/v1/admin/class-templates', $templateData);

        // Assert
        $response->assertCreated();
        $this->assertDatabaseHas('class_templates', [
            'title' => 'HIIT Training',
            'duration_min' => 45,
            'capacity' => 12,
        ]);
    }

    /**
     * Test admin can update a class template
     */
    public function test_admin_can_update_class_template(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $template = ClassTemplate::factory()->create([
            'title' => 'Yoga',
            'status' => 'active',
        ]);

        $updateData = [
            'title' => 'Power Yoga',
            'status' => 'inactive',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/class-templates/{$template->id}", $updateData);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('class_templates', [
            'id' => $template->id,
            'title' => 'Power Yoga',
            'status' => 'inactive',
        ]);
    }

    /**
     * Test admin can delete a class template
     */
    public function test_admin_can_delete_class_template(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $template = ClassTemplate::factory()->create();

        // Act
        Sanctum::actingAs($admin);
        $response = $this->deleteJson("/api/v1/admin/class-templates/{$template->id}");

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('class_templates', [
            'id' => $template->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test staff user cannot access admin endpoints
     */
    public function test_staff_cannot_access_admin_endpoints(): void
    {
        // Arrange
        $staff = User::factory()->create(['role' => 'staff']);

        // Act
        Sanctum::actingAs($staff);
        $response = $this->getJson('/api/v1/admin/users');

        // Assert
        $response->assertForbidden();
    }

    /**
     * Test client user cannot access admin endpoints
     */
    public function test_client_cannot_access_admin_endpoints(): void
    {
        // Arrange
        $client = User::factory()->create(['role' => 'client']);

        // Act
        Sanctum::actingAs($client);
        $response = $this->getJson('/api/v1/admin/rooms');

        // Assert
        $response->assertForbidden();
    }

    /**
     * Test unauthenticated user cannot access admin endpoints
     */
    public function test_unauthenticated_cannot_access_admin_endpoints(): void
    {
        $response = $this->getJson('/api/v1/admin/class-templates');
        $response->assertUnauthorized();
    }

    /**
     * Test validation for creating room
     */
    public function test_validation_for_creating_room(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);

        $invalidData = [
            'site_id' => 99999, // Non-existent site ID
            // Missing 'name' field
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/v1/admin/rooms', $invalidData);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['site_id', 'name']);
    }

    /**
     * Test validation for creating class template
     */
    public function test_validation_for_creating_class_template(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);

        $invalidData = [
            'title' => '', // Empty title
            'duration_min' => -10, // Invalid negative duration
            'capacity' => 0, // Invalid zero capacity
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/v1/admin/class-templates', $invalidData);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'duration_min', 'capacity']);
    }
}
