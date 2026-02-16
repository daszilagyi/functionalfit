<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Staff Price Codes table: Staff-specific pricing per service type.
     * Allows staff members to participate in training sessions as clients
     * with their own pricing structure per service type.
     * Supports price history via valid_from/valid_until date ranges.
     *
     * Auto-generated on staff profile creation with default values from service_types.
     */
    public function up(): void
    {
        Schema::create('staff_price_codes', function (Blueprint $table) {
            $table->id();

            // Staff profile relationship
            $table->foreignId('staff_profile_id')->constrained('staff_profiles')->onDelete('cascade');

            // Redundant email for fast lookup (indexed)
            $table->string('staff_email', 255)->comment('Redundant email for indexed lookup');

            // Service type relationship
            $table->foreignId('service_type_id')->constrained('service_types')->onDelete('restrict');

            // Optional price code identifier
            $table->string('price_code', 64)->nullable()->comment('Optional price code reference');

            // Pricing (stored as integers in HUF, no decimals)
            $table->unsignedInteger('entry_fee_brutto')->comment('Staff entry fee in HUF');
            $table->unsignedInteger('trainer_fee_brutto')->comment('Trainer fee in HUF');
            $table->string('currency', 3)->default('HUF');

            // Validity period (for price history)
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes for price resolution
            $table->index(['staff_email', 'service_type_id', 'is_active'], 'idx_staff_price_codes_lookup');
            $table->index(['staff_profile_id', 'service_type_id'], 'idx_staff_price_codes_staff');
            $table->index(['valid_from', 'valid_until'], 'idx_staff_price_codes_validity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_price_codes');
    }
};
