<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sites table: Physical business locations (SASAD, TB, ÚJBUDA)
     * Each site can have multiple rooms and its own contact/operational details.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();

            // Basic information
            $table->string('name')->unique()->comment('Site name - SASAD, TB, ÚJBUDA');
            $table->string('slug')->unique()->comment('URL-safe identifier');

            // Contact and location
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();

            // Operational details
            $table->text('description')->nullable();
            $table->json('opening_hours')->nullable()->comment('JSON: {monday: {open: "08:00", close: "22:00"}, ...}');
            $table->boolean('is_active')->default(true);

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active', 'deleted_at']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
