<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds attendance tracking columns to event_additional_clients pivot table.
     * This allows tracking attendance for each additional guest in multi-guest events.
     */
    public function up(): void
    {
        Schema::table('event_additional_clients', function (Blueprint $table) {
            // Attendance status: attended, no_show, or null (not yet checked in)
            $table->string('attendance_status')->nullable()->after('quantity');
            // Timestamp when attendance was recorded
            $table->timestamp('checked_in_at')->nullable()->after('attendance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_additional_clients', function (Blueprint $table) {
            $table->dropColumn(['attendance_status', 'checked_in_at']);
        });
    }
};
