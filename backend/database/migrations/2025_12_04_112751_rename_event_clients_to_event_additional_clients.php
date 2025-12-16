<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates or renames event_additional_clients pivot table for supporting
     * multiple guests per event. The main client is stored in events.client_id,
     * and additional participants are stored in this pivot table.
     *
     * Also adds the technical_guest_client_id setting if missing.
     */
    public function up(): void
    {
        // Check if old table name exists (from previous migration attempts)
        if (Schema::hasTable('event_clients')) {
            // Rename the table
            Schema::rename('event_clients', 'event_additional_clients');
        } elseif (!Schema::hasTable('event_additional_clients')) {
            // Create the table if it doesn't exist
            Schema::create('event_additional_clients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
                $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
                $table->timestamps();

                // Prevent duplicate entries
                $table->unique(['event_id', 'client_id']);

                // Add indexes for performance
                $table->index('event_id');
                $table->index('client_id');
            });
        }

        // Add technical guest setting if it doesn't exist
        $existingSetting = DB::table('settings')
            ->where('key', 'technical_guest_client_id')
            ->exists();

        if (!$existingSetting) {
            // Find the technical guest client
            $technicalGuest = DB::table('clients')
                ->where('full_name', 'Technikai Vendég')
                ->whereNull('user_id')
                ->first();

            if ($technicalGuest) {
                DB::table('settings')->insert([
                    'key' => 'technical_guest_client_id',
                    'value' => json_encode($technicalGuest->id),
                    'description' => 'ID of the special "Technical Guest" client used for unknown/walk-in participants',
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove technical guest setting
        DB::table('settings')
            ->where('key', 'technical_guest_client_id')
            ->delete();

        // Drop the table
        Schema::dropIfExists('event_additional_clients');
    }
};
