<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
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
