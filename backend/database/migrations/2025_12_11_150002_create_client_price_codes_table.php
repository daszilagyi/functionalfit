<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Client Price Codes table: Client-specific pricing per service type.
     * Each client can have different prices for different service types.
     * Supports price history via valid_from/valid_until date ranges.
     *
     * Auto-generated on client registration with default values from service_types.
     */
    public function up(): void
    {
        Schema::create('client_price_codes', function (Blueprint $table) {
            $table->id();

            // Client relationship
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');

            // Redundant email for fast lookup (indexed)
            $table->string('client_email', 255)->comment('Redundant email for indexed lookup');

            // Service type relationship
            $table->foreignId('service_type_id')->constrained('service_types')->onDelete('restrict');

            // Optional price code identifier
            $table->string('price_code', 64)->nullable()->comment('Optional price code reference');

            // Pricing (stored as integers in HUF, no decimals)
            $table->unsignedInteger('entry_fee_brutto')->comment('Client entry fee in HUF');
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
            $table->index(['client_email', 'service_type_id', 'is_active'], 'idx_client_price_codes_lookup');
            $table->index(['client_id', 'service_type_id'], 'idx_client_price_codes_client');
            $table->index(['valid_from', 'valid_until'], 'idx_client_price_codes_validity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_price_codes');
    }
};
