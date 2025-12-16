<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->string('specialization')->nullable()->after('bio');
            $table->decimal('default_hourly_rate', 10, 2)->nullable()->after('specialization');
            $table->boolean('is_available_for_booking')->default(true)->after('default_hourly_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn(['specialization', 'default_hourly_rate', 'is_available_for_booking']);
        });
    }
};
