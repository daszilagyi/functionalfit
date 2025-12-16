<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Class occurrences: Individual instances of group classes.
     * Can be generated from templates or created as one-off classes.
     * template_id is nullable to allow standalone classes.
     *
     * Critical indexes for collision detection:
     * - (room_id, starts_at, ends_at)
     */
    public function up(): void
    {
        Schema::create('class_occurrences', function (Blueprint $table) {
            $table->id();

            // Relationship to template (nullable for one-off classes)
            $table->foreignId('template_id')->nullable()->constrained('class_templates')->onDelete('set null');

            // Scheduling
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            // Relationships
            $table->foreignId('room_id')->constrained('rooms')->onDelete('restrict');
            $table->foreignId('trainer_id')->constrained('staff_profiles')->onDelete('restrict');

            // Capacity (can override template)
            $table->integer('capacity')->unsigned();

            // Status
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');

            // External sync
            $table->string('google_event_id')->nullable()->unique();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // CRITICAL: Collision detection indexes
            $table->index(['room_id', 'starts_at', 'ends_at', 'deleted_at'], 'idx_class_collision_room');

            // Time-range query optimization
            $table->index(['starts_at', 'ends_at', 'deleted_at'], 'idx_class_time_range');

            // Common query patterns
            $table->index(['template_id', 'starts_at']);
            $table->index(['trainer_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_occurrences');
    }
};
