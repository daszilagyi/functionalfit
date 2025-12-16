<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\TechnicalGuestService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing application settings.
 *
 * Provides endpoints for retrieving various system configuration values.
 */
class SettingsController extends Controller
{
    /**
     * Get the Technical Guest client ID.
     *
     * The Technical Guest is a special client used for unknown/walk-in participants.
     * This endpoint returns the client ID that can be used when creating events
     * without a specific client assigned.
     *
     * GET /api/v1/settings/technical-guest-client-id
     *
     * @return JsonResponse
     */
    public function getTechnicalGuestClientId(): JsonResponse
    {
        $guestId = TechnicalGuestService::getId();

        if ($guestId === null) {
            return ApiResponse::error(
                'Technical Guest client not configured',
                null,
                500
            );
        }

        return ApiResponse::success([
            'technical_guest_client_id' => $guestId,
        ]);
    }
}
