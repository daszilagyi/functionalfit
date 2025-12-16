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
            // Email reminder preferences
            $table->boolean('email_reminder_24h')->default(true)->after('gdpr_consent_at')
                ->comment('Receive 24h reminder emails for upcoming classes and 1:1 sessions');
            $table->boolean('email_reminder_2h')->default(false)->after('email_reminder_24h')
                ->comment('Receive 2h reminder emails for upcoming classes and 1:1 sessions');

            // Google Calendar integration preference
            $table->boolean('gcal_sync_enabled')->default(false)->after('email_reminder_2h')
                ->comment('Sync bookings to personal Google Calendar');
            $table->string('gcal_calendar_id')->nullable()->after('gcal_sync_enabled')
                ->comment('Google Calendar ID for syncing client events');

            // Index for reminder queries
            $table->index(['email_reminder_24h', 'email_reminder_2h'], 'idx_client_reminder_prefs');
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            // Email reminder preferences
            $table->boolean('email_reminder_24h')->default(true)->after('is_active')
                ->comment('Receive 24h reminder emails for upcoming sessions');
            $table->boolean('email_reminder_2h')->default(false)->after('email_reminder_24h')
                ->comment('Receive 2h reminder emails for upcoming sessions');

            // Google Calendar integration is already handled by GoogleCalendarService
            // Staff GCal sync is managed at the organization level, not per-staff preference

            // Index for reminder queries
            $table->index(['email_reminder_24h', 'email_reminder_2h'], 'idx_staff_reminder_prefs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_client_reminder_prefs');
            $table->dropColumn([
                'email_reminder_24h',
                'email_reminder_2h',
                'gcal_sync_enabled',
                'gcal_calendar_id',
            ]);
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_staff_reminder_prefs');
            $table->dropColumn([
                'email_reminder_24h',
                'email_reminder_2h',
            ]);
        });
    }
};
