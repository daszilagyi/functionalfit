<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Settlements: Settlement headers for trainer payments.
     * Aggregates trainer fees and entry fees for a specific period.
     *
     * Workflow:
     * 1. draft: Initial preview/generation, can be edited
     * 2. finalized: Approved for payment, locked from edits
     * 3. paid: Payment completed
     */
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();

            // Trainer relationship
            $table->foreignId('trainer_id')->constrained('users')->onDelete('restrict')
                ->comment('References users.id for the trainer/staff member');

            // Settlement period
            $table->date('period_start');
            $table->date('period_end');

            // Financial totals (stored as integers in HUF, no decimals)
            $table->integer('total_trainer_fee')->unsigned()->comment('Total trainer fees in HUF for the period');
            $table->integer('total_entry_fee')->unsigned()->comment('Total client entry fees in HUF for the period');

            // Status workflow
            $table->enum('status', ['draft', 'finalized', 'paid'])->default('draft');

            // Additional information
            $table->text('notes')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for settlement queries
            $table->index(['trainer_id', 'period_start', 'period_end'], 'idx_trainer_period');
            $table->index(['status', 'period_start'], 'idx_status_period');
            $table->index('period_start');
            $table->index('period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
