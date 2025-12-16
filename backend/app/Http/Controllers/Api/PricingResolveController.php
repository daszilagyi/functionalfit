<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\MissingPricingException;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\PriceCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingResolveController extends Controller
{
    public function __construct(
        private readonly PriceCodeService $priceCodeService
    ) {}

    /**
     * Resolve pricing by client email and service type code.
     * Used by staff UI when creating events.
     *
     * GET /api/v1/pricing/resolve?client_email=test@example.com&service_type_code=PT
     */
    public function resolve(Request $request): JsonResponse
    {
        $request->validate([
            'client_email' => 'required|email',
            'service_type_code' => 'required|string|max:64',
        ]);

        try {
            $pricing = $this->priceCodeService->resolveByEmailAndServiceType(
                $request->input('client_email'),
                $request->input('service_type_code')
            );

            return ApiResponse::success($pricing);
        } catch (MissingPricingException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Resolve pricing by client ID and service type ID.
     *
     * GET /api/v1/pricing/resolve-by-ids?client_id=1&service_type_id=1
     */
    public function resolveByIds(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'service_type_id' => 'required|integer|exists:service_types,id',
        ]);

        try {
            $pricing = $this->priceCodeService->resolveByClientAndServiceType(
                (int) $request->input('client_id'),
                (int) $request->input('service_type_id')
            );

            return ApiResponse::success($pricing);
        } catch (MissingPricingException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }
}
