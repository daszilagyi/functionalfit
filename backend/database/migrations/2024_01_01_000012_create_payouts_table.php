<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Payouts: Generated compensation records for staff.
     * Contains calculated payout amounts based on billing rules.
     *
     * NO SOFT DELETES: Financial records are immutable.
     * Use void/adjustment records instead of deletion.
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->foreignId('staff_id')->constrained('staff_profiles')->onDelete('restrict');

            // Period covered
            $table->date('period_from');
            $table->date('period_to');

            // Calculated totals
            $table->decimal('hours_total', 8, 2)->comment('Total billable hours/sessions');
            $table->decimal('amount_total', 10, 2)->comment('Total compensation in HUF');

            // Detailed calculation
            $table->json('breakdown')->comment('Itemized calculation: rates, hours, adjustments');

            // Export tracking
            $table->timestamp('exported_at')->nullable()->comment('When sent to accounting system');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index(['staff_id', 'period_from'], 'idx_staff_payouts');
            $table->index('exported_at');
            $table->index(['period_from', 'period_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
