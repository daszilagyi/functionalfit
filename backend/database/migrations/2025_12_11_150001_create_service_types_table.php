<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Service Types table: Configurable service categories.
     * Examples: GYOGYTORNA (physiotherapy), PT (personal training), MASSZAZS (massage)
     * Used for service-type-based pricing for 1:1 events.
     */
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();

            // Service type identification
            $table->string('code', 64)->unique()->comment('Unique code, e.g., GYOGYTORNA, PT, MASSZAZS');
            $table->string('name', 255)->comment('Display name, e.g., Gyógytorna');
            $table->text('description')->nullable();

            // Default pricing (used when no client-specific price is set)
            $table->unsignedInteger('default_entry_fee_brutto')->default(0)->comment('Default client entry fee in HUF');
            $table->unsignedInteger('default_trainer_fee_brutto')->default(0)->comment('Default trainer fee in HUF');

            // Status
            $table->boolean('is_active')->default(true);

            // Audit
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code', 'idx_service_types_code');
            $table->index('is_active', 'idx_service_types_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
