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
     * Event Financials: Immutable snapshot table for financial reporting.
     *
     * Purpose: Capture pricing data at event occurrence time to prevent
     * historical data corruption when pricing rules change. Once captured,
     * financial data should never be recalculated retroactively.
     *
     * Data Sources:
     * - INDIVIDUAL: events.entry_fee_brutto, trainer_fee_brutto
     * - INDIVIDUAL (multi-client): event_additional_clients pricing
     * - GROUP: class_pricing_defaults via class_registrations
     *
     * Trigger Points:
     * - When event attendance is marked (attended/no_show)
     * - When event is completed
     * - Manual snapshot trigger for historical data migration
     */
    public function up(): void
    {
        Schema::create('event_financials', function (Blueprint $table) {
            $table->id();

            // Event Reference (Polymorphic: either event_id OR class_occurrence_id)
            $table->enum('source_type', ['INDIVIDUAL', 'GROUP'])
                ->comment('INDIVIDUAL=events, GROUP=class_occurrences');
            $table->foreignId('event_id')->nullable()
                ->constrained('events')->onDelete('cascade')
                ->comment('FK to events (for 1:1 sessions)');
            $table->foreignId('class_occurrence_id')->nullable()
                ->constrained('class_occurrences')->onDelete('cascade')
                ->comment('FK to class_occurrences (for group classes)');
            $table->foreignId('class_registration_id')->nullable()
                ->constrained('class_registrations')->onDelete('set null')
                ->comment('FK to class_registrations (for GROUP)');

            // Client/Trainer References
            $table->foreignId('client_id')
                ->constrained('clients')->onDelete('restrict')
                ->comment('FK to clients');
            $table->string('client_email', 255)
                ->comment('Redundant for fast indexed lookup');
            $table->foreignId('trainer_id')
                ->constrained('staff_profiles')->onDelete('restrict')
                ->comment('FK to staff_profiles (instructor)');

            // Service Classification
            $table->foreignId('service_type_id')->nullable()
                ->constrained('service_types')->onDelete('set null')
                ->comment('FK to service_types (for INDIVIDUAL)');
            $table->foreignId('class_template_id')->nullable()
                ->constrained('class_templates')->onDelete('set null')
                ->comment('FK to class_templates (for GROUP)');

            // Captured Pricing (Immutable)
            $table->unsignedInteger('entry_fee_brutto')->default(0)
                ->comment('Client entry fee in HUF');
            $table->unsignedInteger('trainer_fee_brutto')->default(0)
                ->comment('Trainer fee in HUF');
            $table->string('currency', 3)->default('HUF');

            // Price Source Traceability
            $table->string('price_source', 64)->nullable()
                ->comment('Origin: client_price_code, service_type_default, class_pricing_default');

            // Event Context
            $table->timestamp('occurred_at')
                ->comment('Event start time (Europe/Budapest)');
            $table->unsignedInteger('duration_minutes')
                ->comment('Event duration');
            $table->foreignId('room_id')->nullable()
                ->constrained('rooms')->onDelete('set null')
                ->comment('FK to rooms');
            $table->enum('site', ['SASAD', 'TB', 'ÚJBUDA'])->nullable()
                ->comment('Redundant site from room');

            // Attendance Status (for reporting)
            $table->enum('attendance_status', ['attended', 'no_show', 'cancelled', 'late_cancel'])
                ->nullable();

            // Snapshot Metadata
            $table->timestamp('captured_at')->useCurrent()
                ->comment('When snapshot was created');
            $table->foreignId('captured_by')->nullable()
                ->constrained('users')->onDelete('set null')
                ->comment('FK to users (who triggered snapshot)');

            // Audit Fields
            $table->timestamps();
            $table->softDeletes();

            // Report Optimization Indexes
            $table->index(['trainer_id', 'occurred_at', 'deleted_at'], 'idx_financials_trainer_time');
            $table->index(['client_id', 'occurred_at', 'deleted_at'], 'idx_financials_client_time');
            $table->index(['room_id', 'occurred_at', 'deleted_at'], 'idx_financials_room_time');
            $table->index(['service_type_id', 'occurred_at', 'deleted_at'], 'idx_financials_service_type_time');
            $table->index(['site', 'occurred_at', 'deleted_at'], 'idx_financials_site_time');
            $table->index(['attendance_status', 'occurred_at', 'deleted_at'], 'idx_financials_attendance');
            $table->index(['source_type', 'occurred_at', 'deleted_at'], 'idx_financials_source_type');
            $table->index(['client_email', 'occurred_at'], 'idx_financials_client_email');
            $table->index('captured_at', 'idx_financials_captured_at');
        });

        // CHECK constraint for source exclusivity
        // Note: SQLite doesn't support ALTER TABLE ADD CONSTRAINT
        // The constraint is enforced at application level via model validation
        // For MySQL/MariaDB in production, uncomment and run separately:
        // ALTER TABLE event_financials ADD CONSTRAINT chk_event_financials_source_exclusivity
        // CHECK ((source_type = 'INDIVIDUAL' AND event_id IS NOT NULL AND class_occurrence_id IS NULL) OR
        //        (source_type = 'GROUP' AND event_id IS NULL AND class_occurrence_id IS NOT NULL))
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_financials');
    }
};
