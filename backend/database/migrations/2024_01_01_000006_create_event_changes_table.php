<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Event changes: Immutable audit log for all event modifications.
     * Tracks who changed what and when for compliance and debugging.
     * Action: created, updated, deleted, moved, cancelled
     *
     * NO SOFT DELETES: Audit records are permanent.
     */
    public function up(): void
    {
        Schema::create('event_changes', function (Blueprint $table) {
            $table->id();

            // What was changed
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->enum('action', ['created', 'updated', 'deleted', 'moved', 'cancelled']);

            // Who made the change
            $table->foreignId('by_user_id')->constrained('users')->onDelete('restrict');

            // Change details (old/new values)
            $table->json('meta')->nullable()->comment('Stores old and new values for changed fields');

            // When it happened
            $table->timestamp('created_at');

            // Indexes
            $table->index(['event_id', 'created_at']);
            $table->index(['by_user_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_changes');
    }
};
