<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassPricingDefaultRequest;
use App\Http\Requests\UpdateClassPricingDefaultRequest;
use App\Http\Requests\StoreClientClassPricingRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ClassPricingDefault;
use App\Models\ClientClassPricing;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    /**
     * List all default pricing configurations.
     *
     * GET /api/v1/admin/pricing/class-defaults
     */
    public function listDefaults(Request $request): JsonResponse
    {
        $query = ClassPricingDefault::with(['classTemplate', 'creator']);

        // Filter by class template
        if ($request->has('class_template_id')) {
            $query->where('class_template_id', $request->input('class_template_id'));
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by validity at specific time
        if ($request->has('valid_at')) {
            $validAt = \Carbon\Carbon::parse($request->input('valid_at'));
            $query->validAt($validAt);
        }

        $defaults = $query->orderBy('class_template_id')
            ->orderByDesc('valid_from')
            ->get();

        return ApiResponse::success($defaults);
    }

    /**
     * Create a new default pricing configuration.
     * Only one pricing can be active per class template.
     *
     * POST /api/v1/admin/pricing/class-defaults
     */
    public function storeDefault(StoreClassPricingDefaultRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $pricing = ClassPricingDefault::create($validated);

        // Load relationships for response
        $pricing->load(['classTemplate', 'creator']);

        return ApiResponse::created($pricing, 'Default pricing created successfully');
    }

    /**
     * Update an existing default pricing configuration.
     *
     * PUT/PATCH /api/v1/admin/pricing/class-defaults/{id}
     */
    public function updateDefault(int $id, UpdateClassPricingDefaultRequest $request): JsonResponse
    {
        $pricing = ClassPricingDefault::findOrFail($id);

        $validated = $request->validated();

        $pricing->update($validated);

        // Load relationships for response
        $pricing->load(['classTemplate', 'creator']);

        return ApiResponse::success($pricing, 'Default pricing updated successfully');
    }

    /**
     * Toggle active status of a default pricing configuration.
     *
     * PATCH /api/v1/admin/pricing/class-defaults/{id}/toggle-active
     */
    public function toggleActiveDefault(int $id): JsonResponse
    {
        $pricing = ClassPricingDefault::findOrFail($id);

        $pricing->update([
            'is_active' => !$pricing->is_active,
        ]);

        // Load relationships for response
        $pricing->load(['classTemplate', 'creator']);

        $message = $pricing->is_active
            ? 'Default pricing activated successfully'
            : 'Default pricing deactivated successfully';

        return ApiResponse::success($pricing, $message);
    }

    /**
     * Delete a default pricing configuration (soft delete).
     *
     * DELETE /api/v1/admin/pricing/class-defaults/{id}
     */
    public function destroyDefault(int $id): JsonResponse
    {
        $pricing = ClassPricingDefault::findOrFail($id);

        $pricing->delete();

        return ApiResponse::success(null, 'Default pricing deleted successfully');
    }

    /**
     * Assign pricing to a class template (deactivates any existing active pricing).
     * If the pricing belongs to a different class template, it will be reassigned.
     *
     * POST /api/v1/admin/pricing/assign
     */
    public function assignPricing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_template_id' => ['required', 'integer', 'exists:class_templates,id'],
            'pricing_id' => ['required', 'integer', 'exists:class_pricing_defaults,id'],
        ]);

        $classTemplateId = $validated['class_template_id'];
        $pricingId = $validated['pricing_id'];

        // Get the pricing (can be from any class template)
        $pricing = ClassPricingDefault::findOrFail($pricingId);

        // Deactivate all existing pricing for this class template
        ClassPricingDefault::where('class_template_id', $classTemplateId)
            ->update(['is_active' => false]);

        // Update the pricing to belong to this class template and activate it
        $pricing->update([
            'class_template_id' => $classTemplateId,
            'is_active' => true,
        ]);

        // Load relationships for response
        $pricing->load(['classTemplate', 'creator']);

        return ApiResponse::success($pricing, 'Pricing assigned successfully');
    }

    /**
     * Get client-specific pricing configurations.
     *
     * GET /api/v1/admin/pricing/clients/{clientId}
     */
    public function listClientPricing(int $clientId, Request $request): JsonResponse
    {
        $query = ClientClassPricing::where('client_id', $clientId)
            ->with(['client', 'classTemplate', 'classOccurrence', 'creator']);

        // Filter by class template
        if ($request->has('class_template_id')) {
            $query->where('class_template_id', $request->input('class_template_id'));
        }

        // Filter by class occurrence
        if ($request->has('class_occurrence_id')) {
            $query->where('class_occurrence_id', $request->input('class_occurrence_id'));
        }

        // Filter by validity at specific time
        if ($request->has('valid_at')) {
            $validAt = \Carbon\Carbon::parse($request->input('valid_at'));
            $query->validAt($validAt);
        }

        $pricing = $query->orderByDesc('valid_from')->get();

        return ApiResponse::success($pricing);
    }

    /**
     * Create a client-specific pricing configuration.
     *
     * POST /api/v1/admin/pricing/client-class
     */
    public function storeClientPricing(StoreClientClassPricingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $pricing = ClientClassPricing::create($validated);

        // Load relationships for response
        $pricing->load(['client', 'classTemplate', 'classOccurrence', 'creator']);

        return ApiResponse::created($pricing, 'Client-specific pricing created successfully');
    }

    /**
     * Assign pricing to an event (INDIVIDUAL or BLOCK type).
     *
     * POST /api/v1/admin/pricing/assign-event
     */
    public function assignEventPricing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'pricing_id' => ['required', 'integer', 'exists:class_pricing_defaults,id'],
        ]);

        $event = Event::findOrFail($validated['event_id']);
        $pricing = ClassPricingDefault::findOrFail($validated['pricing_id']);

        // Update the event with the new pricing
        $event->update([
            'pricing_id' => $pricing->id,
        ]);

        // Load relationships for response
        $event->load(['pricing', 'staff', 'client', 'room']);

        return ApiResponse::success($event, 'Pricing assigned to event successfully');
    }
}
