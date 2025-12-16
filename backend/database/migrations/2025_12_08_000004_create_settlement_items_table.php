<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Settlement items: Detailed line items for settlements.
     * One row per client attendance in the settlement period.
     * Links to the original class registration for full traceability.
     *
     * Status field mirrors class_registrations.status at the time of settlement generation:
     * - attended: Full entry fee + trainer fee counted
     * - no_show: Handled per business rules (see settings)
     * - cancelled: Handled per cancellation policy (late cancellation may still count)
     */
    public function up(): void
    {
        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();

            // Settlement relationship
            $table->foreignId('settlement_id')->constrained('settlements')->onDelete('cascade');

            // Class and client relationships
            $table->foreignId('class_occurrence_id')->constrained('class_occurrences')->onDelete('restrict');
            $table->foreignId('client_id')->constrained('clients')->onDelete('restrict');
            $table->foreignId('registration_id')->constrained('class_registrations')->onDelete('restrict')
                ->comment('Links to the original registration for full audit trail');

            // Pricing snapshot (stored as integers in HUF, no decimals)
            $table->integer('entry_fee_brutto')->unsigned()->comment('Entry fee for this attendance in HUF');
            $table->integer('trainer_fee_brutto')->unsigned()->comment('Trainer fee for this attendance in HUF');
            $table->string('currency', 3)->default('HUF');

            // Status snapshot at settlement time
            $table->enum('status', ['attended', 'no_show', 'cancelled'])
                ->comment('Registration status at settlement generation time');

            // Audit fields
            $table->timestamps();

            // Indexes for reporting and aggregation
            $table->index(['settlement_id', 'status'], 'idx_settlement_status');
            $table->index(['class_occurrence_id', 'status'], 'idx_occurrence_status');
            $table->index('client_id');
            $table->index('registration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_items');
    }
};
