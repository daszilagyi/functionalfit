<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Client class pricing: Client-specific pricing overrides.
     * Allows setting custom prices for individual clients on specific class types or occurrences.
     * Supports VIP pricing, discounts, promotions, etc.
     *
     * Rules:
     * - Either class_template_id OR class_occurrence_id must be set (not both)
     * - class_template_id: applies to all occurrences of that template
     * - class_occurrence_id: applies only to a specific occurrence (one-time override)
     */
    public function up(): void
    {
        Schema::create('client_class_pricing', function (Blueprint $table) {
            $table->id();

            // Client relationship
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');

            // Class relationship (either template-level or occurrence-specific)
            $table->foreignId('class_template_id')->nullable()->constrained('class_templates')->onDelete('cascade');
            $table->foreignId('class_occurrence_id')->nullable()->constrained('class_occurrences')->onDelete('cascade');

            // Pricing (stored as integers in HUF, no decimals)
            $table->integer('entry_fee_brutto')->unsigned()->comment('Custom client entry fee in HUF');
            $table->integer('trainer_fee_brutto')->unsigned()->comment('Custom trainer fee per client in HUF');
            $table->string('currency', 3)->default('HUF');

            // Validity period (for temporary promotions)
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();

            // Source tracking
            $table->enum('source', ['manual', 'import', 'promotion'])->default('manual');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for price resolution logic
            // Priority 1: client + occurrence (most specific)
            $table->index(['client_id', 'class_occurrence_id', 'deleted_at'], 'idx_client_occurrence');

            // Priority 2: client + template (general override)
            $table->index(['client_id', 'class_template_id', 'valid_from'], 'idx_client_template');

            // Query optimization
            $table->index(['valid_from', 'valid_until']);
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_class_pricing');
    }
};
