<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SettingsSeeder extends Seeder
{
    /**
     * Seed global application settings.
     *
     * These control business logic and system behavior.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $settings = [
            [
                'key' => 'client_cancellation_policy_hours',
                'value' => json_encode(24),
                'description' => 'Hours before class start that clients can cancel without penalty',
                'updated_at' => $now,
            ],
            [
                'key' => 'staff_cancellation_policy_hours',
                'value' => json_encode(12),
                'description' => 'Hours before event start that staff can self-cancel without admin approval',
                'updated_at' => $now,
            ],
            [
                'key' => 'credit_deduction_timing',
                'value' => json_encode('checkin'),
                'description' => 'When to deduct pass credits: "booking" or "checkin"',
                'updated_at' => $now,
            ],
            [
                'key' => 'staff_move_same_day_only',
                'value' => json_encode(true),
                'description' => 'Whether staff can only move events within the same calendar day',
                'updated_at' => $now,
            ],
            [
                'key' => 'gcal_sync_enabled',
                'value' => json_encode(true),
                'description' => 'Enable Google Calendar push synchronization',
                'updated_at' => $now,
            ],
            [
                'key' => 'gcal_inbound_sync_enabled',
                'value' => json_encode(false),
                'description' => 'Enable Google Calendar inbound pull synchronization (recommended: false)',
                'updated_at' => $now,
            ],
            [
                'key' => 'notification_defaults',
                'value' => json_encode([
                    'booking_confirmation' => ['email'],
                    'reminder_24h' => ['email', 'sms'],
                    'reminder_3h' => ['sms'],
                    'cancellation_confirmation' => ['email'],
                    'waitlist_available' => ['email', 'sms'],
                    'class_cancelled' => ['email', 'sms'],
                    'monthly_summary' => ['email'],
                    'no_show_notification' => ['email'],
                ]),
                'description' => 'Default notification channels for each template type',
                'updated_at' => $now,
            ],
            [
                'key' => 'pass_types',
                'value' => json_encode([
                    [
                        'type' => '5_session',
                        'credits' => 5,
                        'validity_days' => 30,
                        'description' => '5 alkalom - 30 nap',
                    ],
                    [
                        'type' => '10_session',
                        'credits' => 10,
                        'validity_days' => 60,
                        'description' => '10 alkalom - 60 nap',
                    ],
                    [
                        'type' => '20_session',
                        'credits' => 20,
                        'validity_days' => 90,
                        'description' => '20 alkalom - 90 nap',
                    ],
                    [
                        'type' => 'monthly_unlimited',
                        'credits' => 999,
                        'validity_days' => 30,
                        'description' => 'Havi korlátlan bérlet',
                    ],
                ]),
                'description' => 'Available pass types and their default configurations',
                'updated_at' => $now,
            ],
            [
                'key' => 'timezone',
                'value' => json_encode('Europe/Budapest'),
                'description' => 'Application timezone (UTC+1/+2 with DST)',
                'updated_at' => $now,
            ],
            [
                'key' => 'business_hours',
                'value' => json_encode([
                    'monday' => ['open' => '06:00', 'close' => '22:00'],
                    'tuesday' => ['open' => '06:00', 'close' => '22:00'],
                    'wednesday' => ['open' => '06:00', 'close' => '22:00'],
                    'thursday' => ['open' => '06:00', 'close' => '22:00'],
                    'friday' => ['open' => '06:00', 'close' => '22:00'],
                    'saturday' => ['open' => '08:00', 'close' => '20:00'],
                    'sunday' => ['open' => '08:00', 'close' => '20:00'],
                ]),
                'description' => 'Default business hours for each day of the week',
                'updated_at' => $now,
            ],
        ];

        DB::table('settings')->insert($settings);

        $this->command->info('Seeded ' . count($settings) . ' global settings');
    }
}
