<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassOccurrenceResource;
use App\Http\Responses\ApiResponse;
use App\Models\ClassOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /**
     * List public class occurrences (unauthenticated)
     *
     * GET /api/v1/public/classes
     * Query params: from, to, site, room, trainer
     */
    public function listClasses(Request $request): JsonResponse
    {
        $query = ClassOccurrence::query()
            ->with(['template', 'room.site', 'trainer.user'])
            ->where('status', 'scheduled')
            ->where('starts_at', '>', now())
            // Only show classes from templates marked as public visible
            ->whereHas('template', function ($q) {
                $q->where('is_public_visible', true);
            })
            ->orderBy('starts_at');

        // Filter by date range
        if ($request->has('from')) {
            $from = $request->input('from');
            // Append time if only date provided
            if (strlen($from) === 10) {
                $from .= ' 00:00:00';
            }
            $query->where('starts_at', '>=', $from);
        }

        if ($request->has('to')) {
            $to = $request->input('to');
            // Append end of day time if only date provided
            if (strlen($to) === 10) {
                $to .= ' 23:59:59';
            }
            $query->where('starts_at', '<=', $to);
        }

        // Filter by room
        if ($request->has('room')) {
            $query->where('room_id', $request->integer('room'));
        }

        // Filter by trainer
        if ($request->has('trainer')) {
            $query->where('trainer_id', $request->integer('trainer'));
        }

        // Filter by site name (room belongs to site via site_id)
        if ($request->has('site') && $request->input('site')) {
            $siteName = $request->input('site');
            $query->whereHas('room.site', function ($q) use ($siteName) {
                $q->where('name', $siteName);
            });
        }

        $classes = $query->get();

        return ApiResponse::success(ClassOccurrenceResource::collection($classes));
    }
}
