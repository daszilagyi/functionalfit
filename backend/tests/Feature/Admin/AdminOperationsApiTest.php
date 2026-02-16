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

    /**
     * Test admin can update user password
     */
    public function test_admin_can_update_user_password(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'client',
            'password' => \Illuminate\Support\Facades\Hash::make('old_password'),
        ]);
        \App\Models\Client::factory()->create(['user_id' => $user->id]);

        $updateData = [
            'password' => 'new_secure_password_123',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/users/{$user->id}", $updateData);

        // Assert
        $response->assertOk();
        $user->refresh();

        // Verify password was hashed and updated
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new_secure_password_123', $user->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('old_password', $user->password));
    }

    /**
     * Test admin can change user role from client to staff and creates StaffProfile
     */
    public function test_admin_can_change_client_to_staff_creates_staff_profile(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'client']);
        $client = \App\Models\Client::factory()->create(['user_id' => $user->id]);

        // Verify user has no staff profile initially
        $this->assertNull($user->fresh()->staffProfile);

        $updateData = [
            'role' => 'staff',
            'specialization' => 'Personal Training',
            'default_hourly_rate' => 5000.00,
            'is_available_for_booking' => true,
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/users/{$user->id}", $updateData);

        // Assert
        $response->assertOk();
        $user->refresh();

        // Verify role changed
        $this->assertEquals('staff', $user->role);

        // Verify StaffProfile was created
        $this->assertNotNull($user->staffProfile);
        $this->assertEquals('Personal Training', $user->staffProfile->specialization);
        $this->assertEquals(5000.00, $user->staffProfile->default_hourly_rate);
        $this->assertTrue($user->staffProfile->is_available_for_booking);

        // Verify old Client profile still exists (not deleted)
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test admin can change user role from client to admin and creates StaffProfile
     */
    public function test_admin_can_change_client_to_admin_creates_staff_profile(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'client']);
        \App\Models\Client::factory()->create(['user_id' => $user->id]);

        $updateData = [
            'role' => 'admin',
            'bio' => 'System Administrator',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/users/{$user->id}", $updateData);

        // Assert
        $response->assertOk();
        $user->refresh();

        $this->assertEquals('admin', $user->role);
        $this->assertNotNull($user->staffProfile);
        $this->assertEquals('System Administrator', $user->staffProfile->bio);
    }

    /**
     * Test admin can change user role from staff to client and creates Client
     */
    public function test_admin_can_change_staff_to_client_creates_client(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'staff', 'name' => 'John Doe']);
        $staffProfile = \App\Models\StaffProfile::factory()->create(['user_id' => $user->id]);

        // Verify user has no client profile initially
        $this->assertNull($user->fresh()->client);

        $updateData = [
            'role' => 'client',
            'date_of_birth' => '1990-05-15',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_phone' => '+36301234567',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/users/{$user->id}", $updateData);

        // Assert
        $response->assertOk();
        $user->refresh();

        // Verify role changed
        $this->assertEquals('client', $user->role);

        // Verify Client was created with user's name
        $this->assertNotNull($user->client);
        $this->assertEquals('John Doe', $user->client->full_name);
        $this->assertEquals('1990-05-15', $user->client->date_of_birth->format('Y-m-d'));
        $this->assertEquals('Jane Doe', $user->client->emergency_contact_name);
        $this->assertEquals('+36301234567', $user->client->emergency_contact_phone);

        // Verify old StaffProfile still exists (not deleted)
        $this->assertDatabaseHas('staff_profiles', [
            'id' => $staffProfile->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test admin can change user role from admin to client and creates Client
     */
    public function test_admin_can_change_admin_to_client_creates_client(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);
        \App\Models\StaffProfile::factory()->create(['user_id' => $user->id]);

        $updateData = [
            'role' => 'client',
            'notes' => 'Former admin',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/users/{$user->id}", $updateData);

        // Assert
        $response->assertOk();
        $user->refresh();

        $this->assertEquals('client', $user->role);
        $this->assertNotNull($user->client);
        $this->assertEquals('Admin User', $user->client->full_name);
        $this->assertEquals('Former admin', $user->client->notes);
    }

    /**
     * Test role change doesn't create duplicate profiles
     */
    public function test_role_change_does_not_create_duplicate_profiles(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'staff', 'name' => 'Test User']);

        // Create both profiles manually (edge case)
        $existingStaffProfile = \App\Models\StaffProfile::factory()->create(['user_id' => $user->id]);
        $existingClient = \App\Models\Client::factory()->create(['user_id' => $user->id]);

        $updateData = [
            'role' => 'client',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/users/{$user->id}", $updateData);

        // Assert
        $response->assertOk();

        // Verify only one Client record exists for this user
        $clientCount = \App\Models\Client::where('user_id', $user->id)->count();
        $this->assertEquals(1, $clientCount);

        // Verify it's the existing one
        $this->assertDatabaseHas('clients', [
            'id' => $existingClient->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test role change with password update works correctly
     */
    public function test_role_change_and_password_update_together(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'role' => 'client',
            'password' => \Illuminate\Support\Facades\Hash::make('old_password'),
        ]);
        \App\Models\Client::factory()->create(['user_id' => $user->id]);

        $updateData = [
            'role' => 'staff',
            'password' => 'new_password_123',
            'specialization' => 'Yoga Instructor',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/users/{$user->id}", $updateData);

        // Assert
        $response->assertOk();
        $user->refresh();

        // Verify role changed
        $this->assertEquals('staff', $user->role);

        // Verify password was updated and hashed
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new_password_123', $user->password));

        // Verify StaffProfile was created
        $this->assertNotNull($user->staffProfile);
        $this->assertEquals('Yoga Instructor', $user->staffProfile->specialization);
    }

    /**
     * Test staff profile gets default values when created from role change
     */
    public function test_staff_profile_uses_defaults_on_role_change(): void
    {
        // Arrange
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'client']);
        \App\Models\Client::factory()->create(['user_id' => $user->id]);

        // Change role without providing staff-specific fields
        $updateData = [
            'role' => 'staff',
        ];

        // Act
        Sanctum::actingAs($admin);
        $response = $this->patchJson("/api/v1/admin/users/{$user->id}", $updateData);

        // Assert
        $response->assertOk();
        $user->refresh();

        // Verify StaffProfile was created with default values
        $this->assertNotNull($user->staffProfile);
        $this->assertFalse($user->staffProfile->is_available_for_booking); // Should default to false
        $this->assertFalse($user->staffProfile->daily_schedule_notification); // Should default to false
    }
}
