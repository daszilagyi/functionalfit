<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Resources\ClassOccurrenceResource;
use App\Models\ClassOccurrence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    /**
     * Browse available group classes with filters
     *
     * GET /api/classes
     * Query params: date_from, date_to, room_id, class_template_id, has_capacity
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClassOccurrence::query()
            ->with(['template', 'room', 'trainer', 'registrations'])
            ->upcoming()
            ->where('status', 'scheduled');

        // Filter by date range (support both date_from/date_to and start_date/end_date)
        $dateFrom = $request->input('date_from') ?? $request->input('start_date');
        $dateTo = $request->input('date_to') ?? $request->input('end_date');

        if ($dateFrom) {
            $query->where('starts_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('starts_at', '<=', $dateTo);
        }

        // Filter by room
        if ($request->has('room_id')) {
            $query->where('room_id', $request->integer('room_id'));
        }

        // Filter by class template (e.g., "Yoga", "Spinning")
        if ($request->has('class_template_id')) {
            $query->where('class_template_id', $request->integer('class_template_id'));
        }

        // Filter only classes with available capacity
        // Note: This uses a subquery to count registrations and compare with capacity
        if ($request->boolean('has_capacity')) {
            $query->whereRaw('
                (SELECT COUNT(*)
                 FROM class_registrations
                 WHERE class_registrations.occurrence_id = class_occurrences.id
                   AND class_registrations.status IN (?, ?))
                < class_occurrences.capacity
            ', ['booked', 'attended']);
        }

        $classes = $query->orderBy('starts_at')->get();

        return ApiResponse::success(ClassOccurrenceResource::collection($classes));
    }

    /**
     * Show a specific class occurrence
     *
     * GET /api/classes/{id}
     */
    public function show(int $id): JsonResponse
    {
        $occurrence = ClassOccurrence::with([
            'template',
            'room',
            'trainer',
            'registrations.client.user'
        ])->findOrFail($id);

        return ApiResponse::success(new ClassOccurrenceResource($occurrence));
    }
}
