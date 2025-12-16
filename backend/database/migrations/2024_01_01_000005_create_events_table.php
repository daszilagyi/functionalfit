<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Events table: Individual 1:1 sessions and block times.
     * Type: INDIVIDUAL (client session), BLOCK (maintenance/closure)
     * Status: scheduled, completed, cancelled, no_show
     *
     * Critical indexes for collision detection:
     * - (room_id, starts_at, ends_at)
     * - (staff_id, starts_at, ends_at)
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            // Event classification
            $table->enum('type', ['INDIVIDUAL', 'BLOCK'])->default('INDIVIDUAL');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');

            // Relationships
            $table->foreignId('staff_id')->constrained('staff_profiles')->onDelete('restrict');
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('restrict');
            $table->foreignId('room_id')->constrained('rooms')->onDelete('restrict');

            // Scheduling
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');

            // External sync
            $table->string('google_event_id')->nullable()->unique();

            // Additional info
            $table->text('notes')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // CRITICAL: Collision detection indexes
            $table->index(['room_id', 'starts_at', 'ends_at', 'deleted_at'], 'idx_collision_room');
            $table->index(['staff_id', 'starts_at', 'ends_at', 'deleted_at'], 'idx_collision_staff');

            // Time-range query optimization
            $table->index(['starts_at', 'ends_at', 'deleted_at'], 'idx_time_range');

            // Status filtering
            $table->index(['status', 'starts_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
