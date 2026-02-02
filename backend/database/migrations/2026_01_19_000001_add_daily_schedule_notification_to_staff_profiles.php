<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds daily schedule notification preference to staff profiles.
     * When enabled, the trainer will receive a daily email with their schedule.
     */
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->boolean('daily_schedule_notification')
                ->default(false)
                ->after('email_reminder_2h')
                ->comment('Send daily schedule email to trainer');
        });

        // Add index for querying staff with notifications enabled
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->index('daily_schedule_notification', 'idx_staff_daily_notification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_staff_daily_notification');
            $table->dropColumn('daily_schedule_notification');
        });
    }
};
