<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Room;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * List all rooms
     *
     * GET /api/admin/rooms
     */
    public function index(Request $request): JsonResponse
    {
        $query = Room::query()->with('site:id,name,slug');

        // Filter by site_id
        if ($request->has('site_id')) {
            $query->where('site_id', $request->input('site_id'));
        }

        $rooms = $query->orderBy('site_id')->orderBy('name')->get();

        return ApiResponse::success($rooms);
    }

    /**
     * Show a specific room
     *
     * GET /api/admin/rooms/{id}
     */
    public function show(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        return ApiResponse::success($room);
    }

    /**
     * Create a new room
     *
     * POST /api/admin/rooms
     */
    public function store(StoreRoomRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Auto-populate legacy 'site' field for SQLite compatibility
        if (isset($data['site_id'])) {
            $site = Site::find($data['site_id']);
            if ($site) {
                $data['site'] = $site->name;
            }
        }

        $room = Room::create($data);

        return ApiResponse::created($room->load('site'), 'Room created');
    }

    /**
     * Update a room
     *
     * PATCH /api/admin/rooms/{id}
     */
    public function update(StoreRoomRequest $request, int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $data = $request->validated();

        // Auto-populate legacy 'site' field for SQLite compatibility
        if (isset($data['site_id'])) {
            $site = Site::find($data['site_id']);
            if ($site) {
                $data['site'] = $site->name;
            }
        }

        $room->update($data);

        return ApiResponse::success($room->load('site'), 'Room updated');
    }

    /**
     * Soft delete a room
     *
     * DELETE /api/admin/rooms/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        // Check if room has future events
        $hasFutureEvents = $room->events()->where('starts_at', '>', now())->exists();
        $hasFutureClasses = $room->classOccurrences()->where('starts_at', '>', now())->exists();

        if ($hasFutureEvents || $hasFutureClasses) {
            return ApiResponse::error(
                'Cannot delete room with future events or classes',
                ['future_events' => $hasFutureEvents, 'future_classes' => $hasFutureClasses],
                409
            );
        }

        $room->update(['status' => 'inactive']);
        $room->delete(); // Soft delete

        return ApiResponse::noContent();
    }
}
