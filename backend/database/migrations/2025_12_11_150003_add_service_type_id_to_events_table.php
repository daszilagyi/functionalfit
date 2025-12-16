<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add service_type_id to events table.
     * This allows events to be categorized by service type for pricing resolution.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('service_type_id')
                ->nullable()
                ->after('pricing_id')
                ->constrained('service_types')
                ->nullOnDelete();

            $table->index('service_type_id', 'idx_events_service_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['service_type_id']);
            $table->dropIndex('idx_events_service_type');
            $table->dropColumn('service_type_id');
        });
    }
};
