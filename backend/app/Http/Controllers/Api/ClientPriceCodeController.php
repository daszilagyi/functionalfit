<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientPriceCodeRequest;
use App\Http\Requests\UpdateClientPriceCodeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Client;
use App\Models\ClientPriceCode;
use Illuminate\Http\JsonResponse;

class ClientPriceCodeController extends Controller
{
    /**
     * List all price codes for a client.
     */
    public function index(Client $client): JsonResponse
    {
        $priceCodes = $client->priceCodes()
            ->with('serviceType')
            ->orderBy('service_type_id')
            ->get();

        return ApiResponse::success($priceCodes);
    }

    /**
     * Create a new price code for a client.
     */
    public function store(StoreClientPriceCodeRequest $request, Client $client): JsonResponse
    {
        $data = $request->validated();
        $data['client_id'] = $client->id;
        $data['client_email'] = $client->user?->email ?? '';
        $data['created_by'] = auth()->id();

        $priceCode = ClientPriceCode::create($data);
        $priceCode->load('serviceType');

        return ApiResponse::created($priceCode);
    }

    /**
     * Update a price code.
     */
    public function update(UpdateClientPriceCodeRequest $request, ClientPriceCode $clientPriceCode): JsonResponse
    {
        $clientPriceCode->update($request->validated());
        $clientPriceCode->load('serviceType');

        return ApiResponse::success($clientPriceCode);
    }

    /**
     * Delete a price code.
     */
    public function destroy(ClientPriceCode $clientPriceCode): JsonResponse
    {
        $clientPriceCode->delete();

        return ApiResponse::success(null, 'Price code deleted');
    }

    /**
     * Toggle the active status of a price code.
     */
    public function toggleActive(ClientPriceCode $clientPriceCode): JsonResponse
    {
        $clientPriceCode->update(['is_active' => !$clientPriceCode->is_active]);
        $clientPriceCode->load('serviceType');

        return ApiResponse::success($clientPriceCode);
    }
}
