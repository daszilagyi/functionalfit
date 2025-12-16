<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationPreferenceController extends Controller
{
    /**
     * Get current user's notification preferences
     *
     * GET /api/v1/notification-preferences
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = [
            'email_reminder_24h' => false,
            'email_reminder_2h' => false,
            'gcal_sync_enabled' => false,
            'gcal_calendar_id' => null,
        ];

        // Check if user is a client
        if ($user->client) {
            $preferences = [
                'email_reminder_24h' => $user->client->email_reminder_24h,
                'email_reminder_2h' => $user->client->email_reminder_2h,
                'gcal_sync_enabled' => $user->client->gcal_sync_enabled,
                'gcal_calendar_id' => $user->client->gcal_calendar_id,
            ];
        }
        // Check if user is staff
        elseif ($user->staffProfile) {
            $preferences = [
                'email_reminder_24h' => $user->staffProfile->email_reminder_24h,
                'email_reminder_2h' => $user->staffProfile->email_reminder_2h,
                'gcal_sync_enabled' => false, // Staff GCal managed at org level
                'gcal_calendar_id' => null,
            ];
        }

        return response()->json([
            'data' => $preferences,
        ]);
    }

    /**
     * Update current user's notification preferences
     *
     * PUT /api/v1/notification-preferences
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email_reminder_24h' => 'sometimes|boolean',
            'email_reminder_2h' => 'sometimes|boolean',
            'gcal_sync_enabled' => 'sometimes|boolean',
            'gcal_calendar_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Update client preferences
        if ($user->client) {
            $user->client->update($validated);

            return response()->json([
                'message' => 'Notification preferences updated successfully',
                'data' => [
                    'email_reminder_24h' => $user->client->email_reminder_24h,
                    'email_reminder_2h' => $user->client->email_reminder_2h,
                    'gcal_sync_enabled' => $user->client->gcal_sync_enabled,
                    'gcal_calendar_id' => $user->client->gcal_calendar_id,
                ],
            ]);
        }

        // Update staff preferences (no GCal for staff)
        if ($user->staffProfile) {
            $staffData = array_intersect_key($validated, [
                'email_reminder_24h' => true,
                'email_reminder_2h' => true,
            ]);

            $user->staffProfile->update($staffData);

            return response()->json([
                'message' => 'Notification preferences updated successfully',
                'data' => [
                    'email_reminder_24h' => $user->staffProfile->email_reminder_24h,
                    'email_reminder_2h' => $user->staffProfile->email_reminder_2h,
                    'gcal_sync_enabled' => false,
                    'gcal_calendar_id' => null,
                ],
            ]);
        }

        return response()->json([
            'message' => 'User has no client or staff profile',
        ], 400);
    }
}
