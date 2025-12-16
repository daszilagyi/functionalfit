<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSiteRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    /**
     * List all sites
     *
     * GET /api/v1/admin/sites
     */
    public function index(Request $request): JsonResponse
    {
        $query = Site::query()->with('rooms:id,site_id,name');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name or city
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $sites = $query->orderBy('name')->get();

        // Add room count
        $sites->each(function ($site) {
            $site->rooms_count = $site->rooms->count();
        });

        return ApiResponse::success($sites);
    }

    /**
     * Show a specific site
     *
     * GET /api/v1/admin/sites/{id}
     */
    public function show(int $id): JsonResponse
    {
        $site = Site::with('rooms')->findOrFail($id);

        return ApiResponse::success($site);
    }

    /**
     * Create a new site
     *
     * POST /api/v1/admin/sites
     */
    public function store(StoreSiteRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Auto-generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        $site = Site::create($data);

        return ApiResponse::created($site, 'Site created successfully');
    }

    /**
     * Update a site
     *
     * PUT /api/v1/admin/sites/{id}
     */
    public function update(StoreSiteRequest $request, int $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        $data = $request->validated();

        // Auto-update slug if name changed and slug not explicitly provided
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        $site->update($data);

        return ApiResponse::success($site->fresh(), 'Site updated successfully');
    }

    /**
     * Soft delete a site
     *
     * DELETE /api/v1/admin/sites/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        // Check if site has active rooms
        $activeRoomsCount = $site->rooms()->count();

        if ($activeRoomsCount > 0) {
            return ApiResponse::error(
                'Cannot delete site with existing rooms',
                ['rooms_count' => $activeRoomsCount],
                409
            );
        }

        // Soft delete
        $site->update(['is_active' => false]);
        $site->delete();

        return ApiResponse::noContent();
    }

    /**
     * Toggle site active status
     *
     * PATCH /api/v1/admin/sites/{id}/toggle-active
     */
    public function toggleActive(int $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        $site->update(['is_active' => !$site->is_active]);

        return ApiResponse::success($site, 'Site status updated');
    }
}
