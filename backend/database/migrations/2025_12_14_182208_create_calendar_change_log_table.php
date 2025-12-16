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
     * Calendar Change Log: Complete audit trail for all calendar modifications.
     *
     * Tracks every create/update/delete operation on events and class occurrences.
     * Stores complete before/after snapshots in JSON for full traceability.
     *
     * Access Control:
     * - Admin: Can view all change logs
     * - Staff: Can view only their own changes (configurable)
     * - Client: No access
     *
     * Retention: 24 months recommended (configurable)
     */
    public function up(): void
    {
        Schema::create('calendar_change_log', function (Blueprint $table) {
            $table->id();

            // When and what action
            $table->timestamp('changed_at')->comment('UTC timestamp when the change occurred');
            $table->string('action', 64)->comment('EVENT_CREATED, EVENT_UPDATED, EVENT_DELETED');

            // What entity was changed
            $table->string('entity_type', 64)->default('event')->comment('event or class_occurrence');
            $table->unsignedBigInteger('entity_id')->comment('ID of the changed entity');

            // Who made the change
            $table->foreignId('actor_user_id')->constrained('users')->onDelete('restrict')->comment('User who performed the action');
            $table->string('actor_name')->nullable()->comment('Denormalized actor name for historical accuracy');
            $table->string('actor_role', 64)->nullable()->comment('Role at time of change: client, staff, admin');

            // Where the change happened
            $table->string('site', 64)->nullable()->comment('Site identifier: SASAD, HUVOS, etc.');
            $table->unsignedBigInteger('room_id')->nullable()->comment('Room ID if applicable');
            $table->string('room_name')->nullable()->comment('Denormalized room name for historical accuracy');

            // When was the event scheduled (for filtering)
            $table->timestamp('starts_at')->nullable()->comment('Event start time for filtering');
            $table->timestamp('ends_at')->nullable()->comment('Event end time for filtering');

            // Complete change snapshots
            $table->json('before_json')->nullable()->comment('Entity state before change (NULL for CREATE)');
            $table->json('after_json')->nullable()->comment('Entity state after change (NULL for DELETE)');
            $table->json('changed_fields')->nullable()->comment('Array of field names that changed (UPDATE only)');

            // Request metadata
            $table->string('ip_address', 64)->nullable()->comment('IP address of the request');
            $table->string('user_agent')->nullable()->comment('Browser user agent string');

            // Audit timestamp (immutable log - no updates)
            $table->timestamp('created_at')->nullable();

            // Performance indexes
            $table->index('changed_at', 'idx_ccl_changed_at');
            $table->index(['actor_user_id', 'changed_at'], 'idx_ccl_actor');
            $table->index(['room_id', 'changed_at'], 'idx_ccl_room');
            $table->index(['site', 'changed_at'], 'idx_ccl_site');
            $table->index(['starts_at', 'ends_at'], 'idx_ccl_event_time');
            $table->index('action', 'idx_ccl_action');
            $table->index(['entity_type', 'entity_id'], 'idx_ccl_entity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_change_log');
    }
};
