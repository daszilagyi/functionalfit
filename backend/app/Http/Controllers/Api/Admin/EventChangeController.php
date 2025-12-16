<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\EventChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventChangeController extends Controller
{
    /**
     * Get paginated event changes with filters
     *
     * GET /api/v1/admin/event-changes
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => 'nullable|exists:staff,id',
            'action' => 'nullable|in:created,updated,moved,cancelled,deleted',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = EventChange::query()
            ->with(['event.staff', 'event.client', 'event.room', 'user.staff', 'user.client'])
            ->latest('created_at');

        // Filter by staff_id
        if (isset($validated['staff_id'])) {
            $query->whereHas('event', function ($q) use ($validated) {
                $q->where('staff_id', $validated['staff_id']);
            });
        }

        // Filter by action
        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        // Filter by date range
        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $perPage = $validated['per_page'] ?? 25;
        $changes = $query->paginate($perPage);

        return ApiResponse::success($changes, 'Event changes retrieved successfully');
    }
}
