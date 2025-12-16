<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\EventChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Get audit log for event changes (admin only)
     *
     * GET /api/admin/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = EventChange::with(['event.client.user', 'event.room', 'user'])
            ->orderBy('created_at', 'desc');

        // Filter by event_id if provided
        if ($request->has('event_id')) {
            $query->where('event_id', $request->integer('event_id'));
        }

        // Filter by action if provided
        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to'));
        }

        // Filter by user who made the change
        if ($request->has('user_id')) {
            $query->where('by_user_id', $request->integer('user_id'));
        }

        // Paginate results
        $perPage = $request->integer('per_page', 50);
        $logs = $query->paginate($perPage);

        return ApiResponse::success($logs);
    }

    /**
     * Get audit log for a specific event (admin only)
     *
     * GET /api/admin/events/{eventId}/audit-logs
     */
    public function showEventLogs(int $eventId): JsonResponse
    {
        $logs = EventChange::with(['user'])
            ->where('event_id', $eventId)
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success($logs);
    }
}
