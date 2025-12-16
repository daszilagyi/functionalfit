<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Passes: Client credit/membership passes for booking classes.
     * Source: woocommerce, stripe, manual
     * Status: active, expired, depleted, suspended
     *
     * CHECK CONSTRAINT: credits_left >= 0
     */
    public function up(): void
    {
        Schema::create('passes', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->foreignId('client_id')->constrained('clients')->onDelete('restrict');

            // Pass type and credits
            $table->string('type')->comment('5_session, 10_session, monthly_unlimited, etc.');
            $table->integer('total_credits')->unsigned();
            $table->integer('credits_left')->unsigned();

            // Validity period
            $table->date('valid_from');
            $table->date('valid_until');

            // Source and status
            $table->enum('source', ['woocommerce', 'stripe', 'manual'])->default('manual');
            $table->enum('status', ['active', 'expired', 'depleted', 'suspended'])->default('active');

            // External reference
            $table->string('external_order_id')->nullable()->comment('WooCommerce order ID or Stripe payment intent ID');

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['client_id', 'status', 'valid_until', 'deleted_at'], 'idx_active_passes');
            $table->index(['external_order_id', 'deleted_at']);
            $table->index('type');
        });

        // Add CHECK constraint for credits_left >= 0
        // MySQL 8.0.16+ and MariaDB 10.2.1+ support CHECK constraints
        // SQLite does not support ALTER TABLE ADD CONSTRAINT, handle in application logic
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE passes ADD CONSTRAINT chk_credits_non_negative CHECK (credits_left >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passes');
    }
};
