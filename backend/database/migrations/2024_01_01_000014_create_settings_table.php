<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Settings: Global configuration key-value store.
     * Uses JSON for complex configurations.
     *
     * Example keys:
     * - client_cancellation_policy_hours: 24
     * - staff_cancellation_policy_hours: 12
     * - credit_deduction_timing: "checkin" | "booking"
     * - staff_move_same_day_only: true
     * - gcal_sync_enabled: true
     * - notification_defaults: {...}
     *
     * NO SOFT DELETES: Settings are updated, not deleted.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary()->comment('Unique setting identifier');
            $table->json('value')->comment('Setting value (can be scalar, array, or object)');
            $table->text('description')->nullable()->comment('Human-readable description');
            $table->timestamp('updated_at');

            // No created_at since settings are typically seeded and then updated
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
