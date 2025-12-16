<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalendarChangeFilterRequest;
use App\Http\Resources\CalendarChangeLogDetailResource;
use App\Http\Resources\CalendarChangeLogResource;
use App\Http\Responses\ApiResponse;
use App\Models\CalendarChangeLog;
use Illuminate\Http\JsonResponse;

class CalendarChangeController extends Controller
{
    /**
     * Get paginated calendar changes with filters.
     *
     * GET /api/v1/admin/calendar-changes
     *
     * Query parameters:
     * - actorUserId: Filter by actor user ID
     * - roomId: Filter by room ID
     * - site: Filter by site (SASAD, TB, ÚJBUDA)
     * - action: Filter by action type (EVENT_CREATED, EVENT_UPDATED, EVENT_DELETED)
     * - changedFrom: Date range start
     * - changedTo: Date range end
     * - sort: Sort field (default: changed_at)
     * - order: Sort order (asc|desc, default: desc)
     * - page: Pagination page
     * - perPage: Items per page (default: 50, max: 100)
     */
    public function index(CalendarChangeFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = CalendarChangeLog::query();

        // Apply filters (snake_case keys from frontend)
        if (isset($validated['actor_user_id'])) {
            $query->byActor((int) $validated['actor_user_id']);
        }

        if (isset($validated['room_id'])) {
            $query->byRoom((int) $validated['room_id']);
        }

        if (isset($validated['site'])) {
            $query->bySite($validated['site']);
        }

        if (isset($validated['action'])) {
            $query->byAction($validated['action']);
        }

        if (isset($validated['changed_from']) && isset($validated['changed_to'])) {
            $query->changedBetween($validated['changed_from'], $validated['changed_to']);
        }

        // Apply sorting
        $sortField = $validated['sort'];
        $sortOrder = $validated['order'];
        $query->orderBy($sortField, $sortOrder);

        // Paginate
        $perPage = $validated['per_page'];
        $changes = $query->paginate($perPage);

        // Transform using resource with Laravel-standard snake_case meta
        return response()->json([
            'data' => CalendarChangeLogResource::collection($changes->items()),
            'meta' => [
                'current_page' => $changes->currentPage(),
                'per_page' => $changes->perPage(),
                'total' => $changes->total(),
                'last_page' => $changes->lastPage(),
                'from' => $changes->firstItem(),
                'to' => $changes->lastItem(),
            ],
        ]);
    }

    /**
     * Get detailed calendar change by ID.
     *
     * GET /api/v1/admin/calendar-changes/{id}
     */
    public function show(int $id): JsonResponse
    {
        $change = CalendarChangeLog::findOrFail($id);

        return response()->json(new CalendarChangeLogDetailResource($change));
    }

    /**
     * Get calendar changes for the current staff user.
     *
     * GET /api/v1/staff/calendar-changes
     *
     * Automatically filters to show only changes made by the authenticated user.
     */
    public function staffIndex(CalendarChangeFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Get the authenticated user
        $user = auth()->user();

        if (!$user) {
            return ApiResponse::error('Unauthenticated', null, 401);
        }

        // Force filter by current user
        $query = CalendarChangeLog::query()->byActor($user->id);

        // Apply other filters (snake_case keys, excluding actor_user_id since we force it)
        if (isset($validated['room_id'])) {
            $query->byRoom((int) $validated['room_id']);
        }

        if (isset($validated['site'])) {
            $query->bySite($validated['site']);
        }

        if (isset($validated['action'])) {
            $query->byAction($validated['action']);
        }

        if (isset($validated['changed_from']) && isset($validated['changed_to'])) {
            $query->changedBetween($validated['changed_from'], $validated['changed_to']);
        }

        // Apply sorting
        $sortField = $validated['sort'];
        $sortOrder = $validated['order'];
        $query->orderBy($sortField, $sortOrder);

        // Paginate
        $perPage = $validated['per_page'];
        $changes = $query->paginate($perPage);

        // Transform using resource with Laravel-standard snake_case meta
        return response()->json([
            'data' => CalendarChangeLogResource::collection($changes->items()),
            'meta' => [
                'current_page' => $changes->currentPage(),
                'per_page' => $changes->perPage(),
                'total' => $changes->total(),
                'last_page' => $changes->lastPage(),
                'from' => $changes->firstItem(),
                'to' => $changes->lastItem(),
            ],
        ]);
    }
}
