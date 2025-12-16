<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Http\Requests\UpdateServiceTypeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ServiceType;
use App\Services\PriceCodeService;
use Illuminate\Http\JsonResponse;

class ServiceTypeController extends Controller
{
    public function __construct(
        private readonly PriceCodeService $priceCodeService
    ) {}

    /**
     * List all service types.
     */
    public function index(): JsonResponse
    {
        $serviceTypes = ServiceType::orderBy('name')->get();

        return ApiResponse::success($serviceTypes);
    }

    /**
     * Create a new service type.
     */
    public function store(StoreServiceTypeRequest $request): JsonResponse
    {
        $serviceType = ServiceType::create($request->validated());

        // Generate price codes for all existing clients
        $priceCodesCreated = $this->priceCodeService->generatePriceCodesForNewServiceType(
            $serviceType,
            auth()->id()
        );

        return ApiResponse::created([
            'service_type' => $serviceType,
            'price_codes_created' => $priceCodesCreated,
        ]);
    }

    /**
     * Show a specific service type.
     */
    public function show(ServiceType $serviceType): JsonResponse
    {
        return ApiResponse::success($serviceType);
    }

    /**
     * Update a service type.
     */
    public function update(UpdateServiceTypeRequest $request, ServiceType $serviceType): JsonResponse
    {
        $serviceType->update($request->validated());

        return ApiResponse::success($serviceType);
    }

    /**
     * Delete a service type.
     */
    public function destroy(ServiceType $serviceType): JsonResponse
    {
        // Check for existing references before delete
        if ($serviceType->clientPriceCodes()->exists()) {
            return ApiResponse::error(
                'Cannot delete service type with existing client price codes. Deactivate it instead.',
                409
            );
        }

        if ($serviceType->events()->exists()) {
            return ApiResponse::error(
                'Cannot delete service type with existing events. Deactivate it instead.',
                409
            );
        }

        $serviceType->delete();

        return ApiResponse::success(null, 'Service type deleted');
    }

    /**
     * Toggle the active status of a service type.
     */
    public function toggleActive(ServiceType $serviceType): JsonResponse
    {
        $serviceType->update(['is_active' => !$serviceType->is_active]);

        return ApiResponse::success($serviceType);
    }
}
