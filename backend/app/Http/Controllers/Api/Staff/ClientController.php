<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * List all clients (staff can only see clients, not admin/staff users)
     *
     * GET /api/v1/staff/clients
     */
    public function index(Request $request): JsonResponse
    {
        $query = Client::with('user')
            ->select('clients.*')
            ->join('users', 'clients.user_id', '=', 'users.id')
            ->where('users.role', 'client');

        // Search filter
        if ($request->has('search') && strlen($request->input('search')) >= 2) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%")
                    ->orWhere('users.phone', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        // Validate sort parameters
        $allowedSortFields = ['name', 'email', 'created_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        $sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        // Map sort field to actual column
        $sortColumn = match ($sortBy) {
            'name' => 'users.name',
            'email' => 'users.email',
            default => 'clients.created_at',
        };

        $clients = $query->orderBy($sortColumn, $sortDir)
            ->paginate(50);

        // Transform the response
        $clients->getCollection()->transform(function ($client) {
            return [
                'id' => $client->id,
                'user_id' => $client->user_id,
                'name' => $client->user->name ?? $client->full_name,
                'email' => $client->user->email ?? null,
                'phone' => $client->user->phone ?? null,
                'status' => $client->user->status ?? 'active',
                'created_at' => $client->created_at,
            ];
        });

        return ApiResponse::success($clients);
    }

    /**
     * Create a new client (staff creates client with 'client' role only)
     *
     * POST /api/v1/staff/clients
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // Generate a random password (user can reset it later)
                $password = Str::random(12);

                // Create the user with client role
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'password' => Hash::make($password),
                    'role' => 'client',
                    'status' => 'active',
                ]);

                // Create the client profile
                $client = Client::create([
                    'user_id' => $user->id,
                    'full_name' => $validated['name'],
                ]);

                return ApiResponse::created([
                    'id' => $client->id,
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                    'created_at' => $client->created_at,
                ], 'Vendég sikeresen létrehozva');
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Hiba a vendég létrehozásakor: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get a single client
     *
     * GET /api/v1/staff/clients/{id}
     */
    public function show(int $id): JsonResponse
    {
        $client = Client::with('user')
            ->whereHas('user', function ($q) {
                $q->where('role', 'client');
            })
            ->findOrFail($id);

        return ApiResponse::success([
            'id' => $client->id,
            'user_id' => $client->user_id,
            'name' => $client->user->name ?? $client->full_name,
            'email' => $client->user->email ?? null,
            'phone' => $client->user->phone ?? null,
            'status' => $client->user->status ?? 'active',
            'date_of_birth' => $client->date_of_birth,
            'emergency_contact_name' => $client->emergency_contact_name,
            'emergency_contact_phone' => $client->emergency_contact_phone,
            'notes' => $client->notes,
            'daily_training_notification' => $client->daily_training_notification ?? true,
            'created_at' => $client->created_at,
        ]);
    }

    /**
     * Update a client (staff can update basic info)
     *
     * PATCH /api/v1/staff/clients/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $client = Client::with('user')
            ->whereHas('user', function ($q) {
                $q->where('role', 'client');
            })
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($client->user_id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'date_of_birth' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'daily_training_notification' => ['sometimes', 'boolean'],
        ]);

        try {
            return DB::transaction(function () use ($validated, $client) {
                // Update user fields
                $userFields = [];
                if (isset($validated['name'])) $userFields['name'] = $validated['name'];
                if (isset($validated['email'])) $userFields['email'] = $validated['email'];
                if (array_key_exists('phone', $validated)) $userFields['phone'] = $validated['phone'];
                if (isset($validated['status'])) $userFields['status'] = $validated['status'];

                if (!empty($userFields) && $client->user) {
                    $client->user->update($userFields);
                }

                // Update client fields
                $clientFields = [];
                if (isset($validated['name'])) $clientFields['full_name'] = $validated['name'];
                if (array_key_exists('date_of_birth', $validated)) $clientFields['date_of_birth'] = $validated['date_of_birth'];
                if (array_key_exists('emergency_contact_name', $validated)) $clientFields['emergency_contact_name'] = $validated['emergency_contact_name'];
                if (array_key_exists('emergency_contact_phone', $validated)) $clientFields['emergency_contact_phone'] = $validated['emergency_contact_phone'];
                if (array_key_exists('notes', $validated)) $clientFields['notes'] = $validated['notes'];
                if (array_key_exists('daily_training_notification', $validated)) $clientFields['daily_training_notification'] = $validated['daily_training_notification'];

                if (!empty($clientFields)) {
                    $client->update($clientFields);
                }

                $client->refresh();

                return ApiResponse::success([
                    'id' => $client->id,
                    'user_id' => $client->user_id,
                    'name' => $client->user->name ?? $client->full_name,
                    'email' => $client->user->email ?? null,
                    'phone' => $client->user->phone ?? null,
                    'status' => $client->user->status ?? 'active',
                    'date_of_birth' => $client->date_of_birth,
                    'emergency_contact_name' => $client->emergency_contact_name,
                    'emergency_contact_phone' => $client->emergency_contact_phone,
                    'notes' => $client->notes,
                    'daily_training_notification' => $client->daily_training_notification ?? true,
                    'created_at' => $client->created_at,
                ], 'Vendég sikeresen frissítve');
            });
        } catch (\Exception $e) {
            return ApiResponse::error('Hiba a vendég frissítésekor: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Search clients by name or email
     *
     * GET /api/v1/staff/clients/search?q=searchterm
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return ApiResponse::success([]);
        }

        // Search clients with user accounts
        $clientsWithUser = Client::with('user')
            ->whereHas('user', function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->limit(20)
            ->get();

        // Also search clients without user accounts (like Technical Guest)
        $clientsWithoutUser = Client::whereNull('user_id')
            ->where('full_name', 'like', "%{$query}%")
            ->limit(20)
            ->get();

        // Merge both result sets
        $allClients = $clientsWithUser->merge($clientsWithoutUser)
            ->take(20)
            ->map(function ($client) {
                return $this->formatClientResponse($client);
            });

        return ApiResponse::success($allClients);
    }

    /**
     * Get clients by IDs (batch fetch)
     *
     * GET /api/v1/staff/clients/batch?ids=1,2,3
     */
    public function batch(Request $request): JsonResponse
    {
        $ids = $request->input('ids', '');

        if (empty($ids)) {
            return ApiResponse::success([]);
        }

        // Parse comma-separated IDs
        $idArray = array_map('intval', explode(',', $ids));

        $clients = Client::with('user')
            ->whereIn('id', $idArray)
            ->get()
            ->map(function ($client) {
                return $this->formatClientResponse($client);
            });

        return ApiResponse::success($clients);
    }

    /**
     * Format client data for API response
     */
    private function formatClientResponse(Client $client): array
    {
        $result = [
            'id' => (string) $client->id,
            'is_technical_guest' => $client->isTechnicalGuest(),
        ];

        if ($client->user) {
            $result['user'] = [
                'id' => (string) $client->user->id,
                'name' => $client->user->name,
                'email' => $client->user->email,
                'phone' => $client->user->phone ?? null,
            ];
        } else {
            // For clients without user accounts (e.g., Technical Guest)
            $result['user'] = [
                'id' => null,
                'name' => $client->full_name,
                'email' => null,
                'phone' => null,
            ];
        }

        return $result;
    }
}
