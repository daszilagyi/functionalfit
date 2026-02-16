<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffPriceCodeRequest;
use App\Http\Requests\UpdateStaffPriceCodeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\StaffProfile;
use App\Models\StaffPriceCode;
use Illuminate\Http\JsonResponse;

class StaffPriceCodeController extends Controller
{
    /**
     * List all price codes for a staff profile.
     */
    public function index(StaffProfile $staffProfile): JsonResponse
    {
        $priceCodes = $staffProfile->priceCodes()
            ->with('serviceType')
            ->orderBy('service_type_id')
            ->get();

        return ApiResponse::success($priceCodes);
    }

    /**
     * Create a new price code for a staff profile.
     */
    public function store(StoreStaffPriceCodeRequest $request, StaffProfile $staffProfile): JsonResponse
    {
        $data = $request->validated();
        $data['staff_profile_id'] = $staffProfile->id;
        $data['staff_email'] = $staffProfile->user?->email ?? '';
        $data['created_by'] = auth()->id();

        $priceCode = StaffPriceCode::create($data);
        $priceCode->load('serviceType');

        return ApiResponse::created($priceCode);
    }

    /**
     * Update a price code.
     */
    public function update(UpdateStaffPriceCodeRequest $request, StaffPriceCode $staffPriceCode): JsonResponse
    {
        $staffPriceCode->update($request->validated());
        $staffPriceCode->load('serviceType');

        return ApiResponse::success($staffPriceCode);
    }

    /**
     * Delete a price code.
     */
    public function destroy(StaffPriceCode $staffPriceCode): JsonResponse
    {
        $staffPriceCode->delete();

        return ApiResponse::success(null, 'Price code deleted');
    }

    /**
     * Toggle the active status of a price code.
     */
    public function toggleActive(StaffPriceCode $staffPriceCode): JsonResponse
    {
        $staffPriceCode->update(['is_active' => !$staffPriceCode->is_active]);
        $staffPriceCode->load('serviceType');

        return ApiResponse::success($staffPriceCode);
    }
}
