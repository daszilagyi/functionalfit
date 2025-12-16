<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * List all active rooms (staff read-only access)
     *
     * GET /api/v1/staff/rooms
     */
    public function index(Request $request): JsonResponse
    {
        $query = Room::query();

        // Filter by site if provided
        if ($request->has('site')) {
            $query->where('site', $request->input('site'));
        }

        $rooms = $query
            ->orderBy('site')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($rooms);
    }

    /**
     * Show a specific room (staff read-only access)
     *
     * GET /api/v1/staff/rooms/{id}
     */
    public function show(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        return ApiResponse::success($room);
    }
}
