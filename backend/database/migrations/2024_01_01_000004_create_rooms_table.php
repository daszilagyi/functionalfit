<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rooms table: Physical or virtual spaces for events and classes.
     * Sites: SASAD, TB (Tatabánya), ÚJBUDA
     * Each room may be mapped to a Google Calendar resource.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();

            // Location and identity
            $table->enum('site', ['SASAD', 'TB', 'ÚJBUDA']);
            $table->string('name');
            $table->string('google_calendar_id')->nullable()->unique();

            // Display properties
            $table->string('color', 7)->default('#3788D8')->comment('Hex color code for UI');
            $table->integer('capacity')->nullable()->unsigned()->comment('Max occupancy, nullable for flexible spaces');

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['site', 'deleted_at']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
