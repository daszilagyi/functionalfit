<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add notification preference fields to clients and staff_profiles tables.
     * These fields control whether users receive automated reminder emails.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'email_reminder_24h')) {
                $table->boolean('email_reminder_24h')->default(true)->after('gdpr_consent_at')
                    ->comment('Receive 24h reminder emails for upcoming classes and 1:1 sessions');
            }
            if (!Schema::hasColumn('clients', 'email_reminder_2h')) {
                $table->boolean('email_reminder_2h')->default(false)->after('email_reminder_24h')
                    ->comment('Receive 2h reminder emails for upcoming classes and 1:1 sessions');
            }
            if (!Schema::hasColumn('clients', 'gcal_sync_enabled')) {
                $table->boolean('gcal_sync_enabled')->default(false)->after('email_reminder_2h')
                    ->comment('Sync bookings to personal Google Calendar');
            }
            if (!Schema::hasColumn('clients', 'gcal_calendar_id')) {
                $table->string('gcal_calendar_id')->nullable()->after('gcal_sync_enabled')
                    ->comment('Google Calendar ID for syncing client events');
            }
        });

        // Add index separately to avoid issues with column checks
        try {
            Schema::table('clients', function (Blueprint $table) {
                $table->index(['email_reminder_24h', 'email_reminder_2h'], 'idx_client_reminder_prefs');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        Schema::table('staff_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('staff_profiles', 'email_reminder_24h')) {
                $table->boolean('email_reminder_24h')->default(true)
                    ->comment('Receive 24h reminder emails for upcoming sessions');
            }
            if (!Schema::hasColumn('staff_profiles', 'email_reminder_2h')) {
                $table->boolean('email_reminder_2h')->default(false)
                    ->comment('Receive 2h reminder emails for upcoming sessions');
            }
        });

        // Add index separately
        try {
            Schema::table('staff_profiles', function (Blueprint $table) {
                $table->index(['email_reminder_24h', 'email_reminder_2h'], 'idx_staff_reminder_prefs');
            });
        } catch (\Exception $e) {
            // Index already exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_client_reminder_prefs');
            } catch (\Exception $e) {}

            $columns = ['email_reminder_24h', 'email_reminder_2h', 'gcal_sync_enabled', 'gcal_calendar_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_staff_reminder_prefs');
            } catch (\Exception $e) {}

            $columns = ['email_reminder_24h', 'email_reminder_2h'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('staff_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
