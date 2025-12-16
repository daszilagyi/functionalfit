<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove the foreign key constraint from event_additional_clients.client_id
     * to allow negative IDs for technical guests.
     */
    public function up(): void
    {
        Schema::table('event_additional_clients', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['client_id']);

            // Keep the column as bigInteger, but without foreign key constraint
            // This allows both positive IDs (real clients) and negative IDs (technical guests)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_additional_clients', function (Blueprint $table) {
            // Re-add the foreign key constraint
            $table->foreign('client_id')
                ->references('id')
                ->on('clients')
                ->onDelete('cascade');
        });
    }
};
