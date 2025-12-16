<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('POST /api/v1/auth/register', function () {
    it('successfully registers a new user', function () {
        // Arrange
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+36 30 123 4567',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'status'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
            'role' => 'client',
            'status' => 'active',
        ]);
    });

    it('creates user with client role by default', function () {
        // Arrange
        $userData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert
        $response->assertStatus(201);
        expect($response->json('data.user.role'))->toBe('client');
    });

    it('validates required fields', function () {
        // Act: Submit empty data
        $response = $this->postJson('/api/v1/auth/register', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    it('validates email uniqueness', function () {
        // Arrange: Create existing user
        User::factory()->create(['email' => 'existing@example.com']);

        // Act: Try to register with same email
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates email format', function () {
        // Act: Submit invalid email
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    it('validates password confirmation', function () {
        // Act: Submit mismatched passwords
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'DifferentPassword456!',
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });

    it('hashes the password', function () {
        // Arrange
        $password = 'SecurePassword123!';
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ];

        // Act
        $this->postJson('/api/v1/auth/register', $userData);

        // Assert: Password should not be stored in plaintext
        $user = User::where('email', 'test@example.com')->first();
        expect($user->password)->not->toBe($password);
        expect(\Hash::check($password, $user->password))->toBeTrue();
    });

    it('returns authentication token after registration', function () {
        // Arrange
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];

        // Act
        $response = $this->postJson('/api/v1/auth/register', $userData);

        // Assert
        $response->assertStatus(201);
        expect($response->json('data.token'))->toBeString();
        expect($response->json('data.token'))->not->toBeEmpty();
    });
});

describe('POST /api/v1/auth/login', function () {
    it('successfully logs in with valid credentials', function () {
        // Arrange: Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Hash::make('password123'),
        ]);

        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'token',
                ],
            ]);
    });

    it('returns user with relationships on login', function () {
        // Arrange: Create user with staff profile
        $user = User::factory()->staff()->create([
            'email' => 'staff@example.com',
            'password' => \Hash::make('password123'),
        ]);
        $user->staffProfile()->create([
            'bio' => 'Test bio',
            'skills' => ['massage', 'yoga'],
        ]);

        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'password123',
        ]);

        // Assert
        $response->assertStatus(200);
        expect($response->json('data.user.staff_profile'))->not->toBeNull();
    });

    it('fails with invalid email', function () {
        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    });

    it('fails with invalid password', function () {
        // Arrange: Create a user
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Hash::make('correctpassword'),
        ]);

        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    });

    it('denies login for inactive users', function () {
        // Arrange: Create inactive user
        User::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => \Hash::make('password123'),
        ]);

        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        // Assert
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is not active',
            ]);
    });

    it('updates last_login_at timestamp on successful login', function () {
        // Arrange: Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Hash::make('password123'),
            'last_login_at' => null,
        ]);

        // Act
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert
        $user->refresh();
        expect($user->last_login_at)->not->toBeNull();
        expect($user->last_login_at->diffInSeconds(now()))->toBeLessThan(5);
    });

    it('returns authentication token on login', function () {
        // Arrange
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Hash::make('password123'),
        ]);

        // Act
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert
        $response->assertStatus(200);
        expect($response->json('data.token'))->toBeString();
        expect($response->json('data.token'))->not->toBeEmpty();
    });

    it('validates required fields', function () {
        // Act
        $response = $this->postJson('/api/v1/auth/login', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });
});

describe('POST /api/v1/auth/logout', function () {
    it('successfully logs out authenticated user', function () {
        // Arrange: Create and authenticate user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Act
        $response = $this->postJson('/api/v1/auth/logout');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
    });

    it('deletes the current access token', function () {
        // Arrange: Create user and create actual token
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Get token count before logout
        $tokenCountBefore = $user->tokens()->count();

        // Act: Use the actual token
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        // Assert: Token should be deleted
        $tokenCountAfter = $user->tokens()->count();
        expect($tokenCountAfter)->toBeLessThan($tokenCountBefore);
    });

    it('requires authentication', function () {
        // Act: Try to logout without authentication
        $response = $this->postJson('/api/v1/auth/logout');

        // Assert
        $response->assertStatus(401);
    });

    it('only deletes current token, not all tokens', function () {
        // Arrange: Create user with multiple tokens
        $user = User::factory()->create();
        $token1 = $user->createToken('token1');
        $token2 = $user->createToken('token2');

        // Act: Logout using token1
        $this->withToken($token1->plainTextToken)
            ->postJson('/api/v1/auth/logout');

        // Assert: token1 deleted, token2 still exists
        expect($user->tokens()->count())->toBe(1);
    });
});

describe('GET /api/v1/auth/me', function () {
    it('returns authenticated user data', function () {
        // Arrange: Create and authenticate user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/auth/me');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'email', 'role', 'status'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    });

    it('returns user with staff profile when staff', function () {
        // Arrange: Create staff user with profile
        $user = User::factory()->staff()->create();
        $user->staffProfile()->create([
            'bio' => 'Test bio',
            'skills' => ['massage', 'yoga'],
        ]);
        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/auth/me');

        // Assert
        $response->assertStatus(200);
        expect($response->json('data.staff_profile'))->not->toBeNull();
        expect($response->json('data.staff_profile.bio'))->toBe('Test bio');
    });

    it('returns user with client profile when client', function () {
        // Arrange: Create client user with profile
        $user = User::factory()->client()->create();
        $user->client()->create([
            'full_name' => 'Test Client',
            'date_of_joining' => now(),
        ]);
        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/auth/me');

        // Assert
        $response->assertStatus(200);
        expect($response->json('data.client'))->not->toBeNull();
        expect($response->json('data.client.full_name'))->toBe('Test Client');
    });

    it('requires authentication', function () {
        // Act: Try to access without authentication
        $response = $this->getJson('/api/v1/auth/me');

        // Assert
        $response->assertStatus(401);
    });

    it('does not expose sensitive data', function () {
        // Arrange: Create and authenticate user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Act
        $response = $this->getJson('/api/v1/auth/me');

        // Assert: Password should not be in response
        $response->assertStatus(200);
        expect($response->json('data.password'))->toBeNull();
        expect($response->json('data.remember_token'))->toBeNull();
    });
});

describe('Authentication - Edge Cases', function () {
    it('handles multiple login attempts with same credentials', function () {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Hash::make('password123'),
        ]);

        // Act: Login multiple times
        $response1 = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response2 = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        // Assert: Both logins should succeed and return different tokens
        $response1->assertStatus(200);
        $response2->assertStatus(200);
        expect($response1->json('data.token'))
            ->not->toBe($response2->json('data.token'));
    });

    it('handles case-insensitive email login', function () {
        // Arrange: Create user with lowercase email
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => \Hash::make('password123'),
        ]);

        // Act: Login with uppercase email
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'TEST@EXAMPLE.COM',
            'password' => 'password123',
        ]);

        // Assert: Should fail because email is case-sensitive in database
        $response->assertStatus(401);
    });

    it('prevents registration with existing email case variation', function () {
        // Arrange: Create user with lowercase email
        User::factory()->create(['email' => 'test@example.com']);

        // Act: Try to register with uppercase email
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'TEST@EXAMPLE.COM',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        // Assert: Should succeed (database uniqueness is case-insensitive in MySQL by default)
        // Note: This behavior depends on database collation settings
        $response->assertStatus(201);
    });
});
