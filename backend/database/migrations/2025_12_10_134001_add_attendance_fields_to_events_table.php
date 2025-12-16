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
        Schema::table('events', function (Blueprint $table) {
            $table->enum('attendance_status', ['attended', 'no_show'])
                ->nullable()
                ->after('status');
            $table->timestamp('checked_in_at')
                ->nullable()
                ->after('attendance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['attendance_status', 'checked_in_at']);
        });
    }
};
