<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\Client;
use App\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users (admin only)
     *
     * GET /api/admin/users
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['staffProfile', 'client']);

        // Filter by role(s) - supports single role or comma-separated roles
        if ($request->has('role')) {
            $roles = $request->input('role');
            if (is_string($roles) && str_contains($roles, ',')) {
                $rolesArray = array_map('trim', explode(',', $roles));
                $query->whereIn('role', $rolesArray);
            } else {
                $query->where('role', $roles);
            }
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by unpaid balance (only clients with unpaid_balance > 0)
        if ($request->boolean('has_unpaid_balance')) {
            $query->whereHas('client', function ($q) {
                $q->where('unpaid_balance', '>', 0);
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSortFields = ['name', 'email', 'created_at'];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = min((int) $request->input('per_page', 100), 200);
        $users = $query->paginate($perPage);

        return ApiResponse::success($users);
    }

    /**
     * Show a specific user
     *
     * GET /api/admin/users/{id}
     */
    public function show(int $id): JsonResponse
    {
        $user = User::with(['staffProfile', 'client', 'client.passes'])->findOrFail($id);

        return ApiResponse::success($user);
    }

    /**
     * Create a new user (admin only)
     *
     * POST /api/admin/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::create([
                'role' => $request->input('role'),
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->input('password')),
                'status' => $request->input('status', 'active'),
            ]);

            // Create role-specific profile
            if ($request->input('role') === 'client') {
                Client::create([
                    'user_id' => $user->id,
                    'full_name' => $request->input('name'),
                    'date_of_birth' => $request->input('date_of_birth'),
                    'emergency_contact_name' => $request->input('emergency_contact_name'),
                    'emergency_contact_phone' => $request->input('emergency_contact_phone'),
                    'notes' => $request->input('notes'),
                ]);
            } elseif (in_array($request->input('role'), ['staff', 'admin'])) {
                StaffProfile::create([
                    'user_id' => $user->id,
                    'specialization' => $request->input('specialization'),
                    'bio' => $request->input('bio'),
                    'default_hourly_rate' => $request->input('default_hourly_rate'),
                    'is_available_for_booking' => $request->boolean('is_available_for_booking', true),
                ]);
            }

            return ApiResponse::created($user->load(['staffProfile', 'client']), 'User created');
        });
    }

    /**
     * Update a user
     *
     * PATCH /api/admin/users/{id}
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::with(['staffProfile', 'client'])->findOrFail($id);

        return DB::transaction(function () use ($request, $user) {
            $user->update($request->validated());

            // Update role-specific profile
            $clientFields = ['date_of_birth', 'emergency_contact_name', 'emergency_contact_phone', 'notes'];
            if ($user->client && $request->hasAny($clientFields)) {
                $user->client->update($request->only($clientFields));
            }

            $staffFields = ['specialization', 'bio', 'default_hourly_rate', 'is_available_for_booking', 'daily_schedule_notification'];
            if ($user->staffProfile && $request->hasAny($staffFields)) {
                $user->staffProfile->update($request->only($staffFields));
            }

            return ApiResponse::success($user->fresh(['staffProfile', 'client']), 'User updated');
        });
    }

    /**
     * Soft delete a user
     *
     * DELETE /api/admin/users/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::with('client')->findOrFail($id);

        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return ApiResponse::error('Cannot delete your own account', null, 403);
        }

        return DB::transaction(function () use ($user) {
            // If user is a client, cancel all their upcoming class registrations
            if ($user->client) {
                $clientId = $user->client->id;

                // Cancel upcoming class registrations (soft cancel by setting status)
                \App\Models\ClassRegistration::where('client_id', $clientId)
                    ->whereIn('status', ['booked', 'waitlist'])
                    ->whereHas('occurrence', function ($query) {
                        $query->where('starts_at', '>', now());
                    })
                    ->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ]);

                // Cancel upcoming 1:1 events
                \App\Models\Event::where('client_id', $clientId)
                    ->where('type', 'individual')
                    ->where('starts_at', '>', now())
                    ->whereNotIn('status', ['cancelled', 'completed'])
                    ->update(['status' => 'cancelled']);
            }

            $user->update(['status' => 'inactive']);
            $user->delete(); // Soft delete

            return ApiResponse::noContent();
        });
    }
}
