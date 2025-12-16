<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Class pricing defaults: Default pricing for class templates.
     * Stores default entry fee and trainer fee for each class type.
     * Supports price history via valid_from/valid_until date ranges.
     */
    public function up(): void
    {
        Schema::create('class_pricing_defaults', function (Blueprint $table) {
            $table->id();

            // Relationship to class template
            $table->foreignId('class_template_id')->constrained('class_templates')->onDelete('restrict');

            // Pricing (stored as integers in HUF, no decimals)
            $table->integer('entry_fee_brutto')->unsigned()->comment('Client entry fee in HUF');
            $table->integer('trainer_fee_brutto')->unsigned()->comment('Trainer fee per client in HUF');
            $table->string('currency', 3)->default('HUF');

            // Validity period (for price history)
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for querying active prices
            $table->index(['class_template_id', 'valid_from', 'valid_until'], 'idx_template_validity');
            $table->index(['class_template_id', 'is_active', 'deleted_at'], 'idx_active_prices');
            $table->index('valid_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_pricing_defaults');
    }
};
