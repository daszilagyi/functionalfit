<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClassTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassTemplateController extends Controller
{
    /**
     * List all class templates
     *
     * GET /api/admin/class-templates
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClassTemplate::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by is_active (frontend naming)
        if ($request->has('is_active')) {
            $status = $request->boolean('is_active') ? 'active' : 'inactive';
            $query->where('status', $status);
        }

        $templates = $query->orderBy('title')->get();

        // Transform to frontend-compatible format
        $transformed = $templates->map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->title,
                'title' => $template->title, // Keep for backwards compatibility
                'description' => $template->description,
                'duration_minutes' => $template->duration_min,
                'duration_min' => $template->duration_min, // Keep for backwards compatibility
                'default_capacity' => $template->capacity,
                'capacity' => $template->capacity, // Keep for backwards compatibility
                'credits_required' => $template->credits_required,
                'base_price_huf' => $template->base_price_huf,
                'color' => $template->tags['color'] ?? null, // Color might be in tags
                'is_active' => $template->status === 'active',
                'status' => $template->status, // Keep for backwards compatibility
                'room_id' => $template->room_id,
                'trainer_id' => $template->trainer_id,
                'weekly_rrule' => $template->weekly_rrule,
                'tags' => $template->tags,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
            ];
        });

        return ApiResponse::success($transformed);
    }

    /**
     * Show a specific class template
     *
     * GET /api/admin/class-templates/{id}
     */
    public function show(int $id): JsonResponse
    {
        $template = ClassTemplate::findOrFail($id);

        return ApiResponse::success($template);
    }

    /**
     * Create a new class template
     *
     * POST /api/admin/class-templates
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Accept both 'title' and 'name' for backwards compatibility
            'title' => ['required_without:name', 'string', 'max:255'],
            'name' => ['required_without:title', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            // Accept both 'duration_min' and 'duration_minutes'
            'duration_min' => ['required_without:duration_minutes', 'integer', 'min:15', 'max:480'],
            'duration_minutes' => ['required_without:duration_min', 'integer', 'min:15', 'max:480'],
            // Accept both 'capacity' and 'default_capacity'
            'capacity' => ['required_without:default_capacity', 'integer', 'min:1'],
            'default_capacity' => ['required_without:capacity', 'integer', 'min:1'],
            'credits_required' => ['nullable', 'integer', 'min:0'],
            'base_price_huf' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:7'],
            'tags' => ['nullable', 'array'],
            'weekly_rrule' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Normalize field names from frontend to backend
        $tags = $validated['tags'] ?? [];
        if (isset($validated['color'])) {
            $tags['color'] = $validated['color'];
        }

        $data = [
            'title' => $validated['title'] ?? $validated['name'],
            'description' => $validated['description'] ?? null,
            'room_id' => $validated['room_id'] ?? null,
            'trainer_id' => $validated['trainer_id'] ?? null,
            'duration_min' => $validated['duration_min'] ?? $validated['duration_minutes'],
            'capacity' => $validated['capacity'] ?? $validated['default_capacity'],
            'credits_required' => $validated['credits_required'] ?? null,
            'base_price_huf' => $validated['base_price_huf'] ?? null,
            'tags' => !empty($tags) ? $tags : null,
            'weekly_rrule' => $validated['weekly_rrule'] ?? null,
            'status' => isset($validated['is_active'])
                ? ($validated['is_active'] ? 'active' : 'inactive')
                : ($validated['status'] ?? 'active'),
        ];

        $template = ClassTemplate::create($data);

        return ApiResponse::created($template, 'Class template created');
    }

    /**
     * Update a class template
     *
     * PATCH /api/admin/class-templates/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $template = ClassTemplate::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'trainer_id' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'duration_min' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'default_capacity' => ['sometimes', 'integer', 'min:1'],
            'credits_required' => ['nullable', 'integer', 'min:0'],
            'base_price_huf' => ['nullable', 'numeric', 'min:0'],
            'color' => ['nullable', 'string', 'max:7'],
            'tags' => ['nullable', 'array'],
            'weekly_rrule' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Build update data with field name normalization
        $data = [];

        if (isset($validated['title'])) {
            $data['title'] = $validated['title'];
        } elseif (isset($validated['name'])) {
            $data['title'] = $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $data['description'] = $validated['description'];
        }

        if (array_key_exists('room_id', $validated)) {
            $data['room_id'] = $validated['room_id'];
        }

        if (array_key_exists('trainer_id', $validated)) {
            $data['trainer_id'] = $validated['trainer_id'];
        }

        if (isset($validated['duration_min'])) {
            $data['duration_min'] = $validated['duration_min'];
        } elseif (isset($validated['duration_minutes'])) {
            $data['duration_min'] = $validated['duration_minutes'];
        }

        if (isset($validated['capacity'])) {
            $data['capacity'] = $validated['capacity'];
        } elseif (isset($validated['default_capacity'])) {
            $data['capacity'] = $validated['default_capacity'];
        }

        if (array_key_exists('credits_required', $validated)) {
            $data['credits_required'] = $validated['credits_required'];
        }

        if (array_key_exists('base_price_huf', $validated)) {
            $data['base_price_huf'] = $validated['base_price_huf'];
        }

        // Handle color in tags
        if (isset($validated['color'])) {
            $tags = $template->tags ?? [];
            $tags['color'] = $validated['color'];
            $data['tags'] = $tags;
        } elseif (array_key_exists('tags', $validated)) {
            $data['tags'] = $validated['tags'];
        }

        if (array_key_exists('weekly_rrule', $validated)) {
            $data['weekly_rrule'] = $validated['weekly_rrule'];
        }

        if (isset($validated['is_active'])) {
            $data['status'] = $validated['is_active'] ? 'active' : 'inactive';
        } elseif (isset($validated['status'])) {
            $data['status'] = $validated['status'];
        }

        $template->update($data);

        return ApiResponse::success($template, 'Class template updated');
    }

    /**
     * Soft delete a class template
     *
     * DELETE /api/admin/class-templates/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $template = ClassTemplate::findOrFail($id);

        // Check if template has future occurrences
        $hasFutureOccurrences = $template->occurrences()->where('starts_at', '>', now())->exists();

        if ($hasFutureOccurrences) {
            return ApiResponse::error(
                'Cannot delete template with future occurrences',
                ['future_occurrences' => $hasFutureOccurrences],
                409
            );
        }

        $template->update(['status' => 'inactive']);
        $template->delete(); // Soft delete

        return ApiResponse::noContent();
    }
}
