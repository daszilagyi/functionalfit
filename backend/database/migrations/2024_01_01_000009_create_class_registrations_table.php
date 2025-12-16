<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Class registrations: Client bookings for class occurrences.
     * Status: booked, waitlist, cancelled, no_show, attended
     *
     * Unique constraint: One client can only have one active registration per occurrence.
     */
    public function up(): void
    {
        Schema::create('class_registrations', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('occurrence_id')->constrained('class_occurrences')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('restrict');

            // Status tracking
            $table->enum('status', ['booked', 'waitlist', 'cancelled', 'no_show', 'attended'])->default('booked');

            // Timestamps for lifecycle
            $table->timestamp('booked_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();

            // Pass credit tracking
            $table->integer('credits_used')->default(0);

            // Check-in tracking
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->onDelete('set null');

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // CRITICAL: Prevent duplicate active bookings
            // Note: Laravel doesn't support partial unique indexes in migrations directly,
            // so this must be enforced at application level or via raw SQL
            $table->index(['occurrence_id', 'client_id', 'deleted_at'], 'idx_unique_booking');

            // Reporting and query optimization
            $table->index(['occurrence_id', 'status', 'deleted_at'], 'idx_reporting');
            $table->index(['client_id', 'created_at']);
            $table->index('status');
            $table->index('checked_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_registrations');
    }
};
