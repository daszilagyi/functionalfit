<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Class templates: Recurring group class definitions.
     * Uses RFC 5545 RRULE format for weekly recurrence patterns.
     * Example: "FREQ=WEEKLY;BYDAY=MO,WE,FR" for Monday, Wednesday, Friday classes.
     */
    public function up(): void
    {
        Schema::create('class_templates', function (Blueprint $table) {
            $table->id();

            // Class information
            $table->string('title');
            $table->text('description')->nullable();

            // Relationships
            $table->foreignId('trainer_id')->constrained('staff_profiles')->onDelete('restrict');
            $table->foreignId('room_id')->constrained('rooms')->onDelete('restrict');

            // Scheduling
            $table->string('weekly_rrule')->comment('RFC 5545 RRULE format');
            $table->integer('duration_min')->unsigned()->comment('Duration in minutes');

            // Capacity and metadata
            $table->integer('capacity')->unsigned();
            $table->json('tags')->nullable()->comment('Categories: yoga, beginner, cardio, etc.');

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['trainer_id', 'deleted_at']);
            $table->index(['room_id', 'deleted_at']);
            $table->index('status');
            $table->index('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_templates');
    }
};
