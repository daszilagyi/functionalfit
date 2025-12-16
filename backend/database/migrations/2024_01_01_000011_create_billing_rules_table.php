<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Billing rules: Staff compensation rate definitions.
     * Rate type: hourly, per_session, fixed_monthly
     * Applies to: INDIVIDUAL, GROUP, BOTH
     *
     * Effective date ranges allow rate history tracking.
     */
    public function up(): void
    {
        Schema::create('billing_rules', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->foreignId('staff_id')->constrained('staff_profiles')->onDelete('restrict');

            // Rate definition
            $table->enum('rate_type', ['hourly', 'per_session', 'fixed_monthly']);
            $table->decimal('rate_value', 8, 2)->comment('Amount in HUF or other currency');

            // Applicability
            $table->enum('applies_to', ['INDIVIDUAL', 'GROUP', 'BOTH'])->default('BOTH');

            // Effective period
            $table->date('effective_from');
            $table->date('effective_until')->nullable()->comment('Null means currently active');

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['staff_id', 'effective_from', 'deleted_at'], 'idx_staff_rates');
            $table->index(['effective_from', 'effective_until']);
            $table->index('applies_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_rules');
    }
};
