<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing admin-level application settings.
 */
class AdminSettingsController extends Controller
{
    /**
     * Get notification settings.
     *
     * GET /api/v1/admin/settings/notifications
     */
    public function getNotificationSettings(): JsonResponse
    {
        return ApiResponse::success([
            'daily_schedule_notification_hour' => (int) Setting::get('daily_schedule_notification_hour', 7),
            'debug_email_enabled' => (bool) Setting::get('debug_email_enabled', false),
            'debug_email_address' => Setting::get('debug_email_address', ''),
            'email_company_name' => Setting::get('email_company_name', 'FunctionalFit Egeszsegkozpont'),
            'email_support_email' => Setting::get('email_support_email', 'support@functionalfit.hu'),
        ]);
    }

    /**
     * Update notification settings.
     *
     * PUT /api/v1/admin/settings/notifications
     */
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'daily_schedule_notification_hour' => ['sometimes', 'integer', 'min:0', 'max:23'],
            'debug_email_enabled' => ['sometimes', 'boolean'],
            'debug_email_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'email_company_name' => ['sometimes', 'string', 'max:255'],
            'email_support_email' => ['sometimes', 'email', 'max:255'],
        ]);

        if (isset($validated['daily_schedule_notification_hour'])) {
            Setting::set('daily_schedule_notification_hour', $validated['daily_schedule_notification_hour']);
        }

        if (isset($validated['debug_email_enabled'])) {
            Setting::set('debug_email_enabled', $validated['debug_email_enabled']);
        }

        if (array_key_exists('debug_email_address', $validated)) {
            Setting::set('debug_email_address', $validated['debug_email_address'] ?? '');
        }

        if (isset($validated['email_company_name'])) {
            Setting::set('email_company_name', $validated['email_company_name']);
        }

        if (isset($validated['email_support_email'])) {
            Setting::set('email_support_email', $validated['email_support_email']);
        }

        return ApiResponse::success([
            'daily_schedule_notification_hour' => (int) Setting::get('daily_schedule_notification_hour', 7),
            'debug_email_enabled' => (bool) Setting::get('debug_email_enabled', false),
            'debug_email_address' => Setting::get('debug_email_address', ''),
            'email_company_name' => Setting::get('email_company_name', 'FunctionalFit Egeszsegkozpont'),
            'email_support_email' => Setting::get('email_support_email', 'support@functionalfit.hu'),
        ], 'Notification settings updated successfully');
    }

    /**
     * Manually trigger daily schedule notifications (for testing).
     *
     * POST /api/v1/admin/settings/notifications/send-daily-schedules
     */
    public function sendDailySchedules(): JsonResponse
    {
        \Artisan::call('schedule:send-daily-trainer-notifications', ['--force' => true]);
        $output = \Artisan::output();

        return ApiResponse::success([
            'output' => $output,
        ], 'Daily schedule notifications triggered');
    }
}
