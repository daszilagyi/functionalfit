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
     * This migration:
     * 1. Adds a guest_index column to uniquely identify each guest in multi-guest scenarios
     * 2. Expands existing records with quantity > 1 into separate rows
     * 3. Updates the primary key to include guest_index
     */
    public function up(): void
    {
        // Step 1: Add guest_index column with default 0 (if it doesn't exist)
        if (!Schema::hasColumn('event_additional_clients', 'guest_index')) {
            Schema::table('event_additional_clients', function (Blueprint $table) {
                $table->unsignedInteger('guest_index')->default(0)->after('client_id');
            });
        }

        // Step 2: Expand records where quantity > 1 into separate rows
        $recordsToExpand = DB::table('event_additional_clients')
            ->where('quantity', '>', 1)
            ->get();

        foreach ($recordsToExpand as $record) {
            // Create additional rows for quantity > 1
            for ($i = 1; $i < $record->quantity; $i++) {
                DB::table('event_additional_clients')->insert([
                    'event_id' => $record->event_id,
                    'client_id' => $record->client_id,
                    'guest_index' => $i,
                    'quantity' => 1,
                    'attendance_status' => $record->attendance_status,
                    'checked_in_at' => $record->checked_in_at,
                    'created_at' => $record->created_at,
                    'updated_at' => now(),
                ]);
            }

            // Update the original record to have quantity = 1 and guest_index = 0
            DB::table('event_additional_clients')
                ->where('event_id', $record->event_id)
                ->where('client_id', $record->client_id)
                ->where('guest_index', 0)
                ->update(['quantity' => 1]);
        }

        // Step 3: Add unique constraint with guest_index (if it doesn't exist)
        // Check if index already exists before adding (MySQL/MariaDB compatible)
        $indexExists = collect(DB::select("SHOW INDEX FROM event_additional_clients WHERE Key_name = 'event_client_guest_unique'"))->isNotEmpty();

        if (!$indexExists) {
            Schema::table('event_additional_clients', function (Blueprint $table) {
                $table->unique(['event_id', 'client_id', 'guest_index'], 'event_client_guest_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_additional_clients', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('event_client_guest_unique');
        });

        // Consolidate rows back into quantity
        $records = DB::table('event_additional_clients')
            ->select('event_id', 'client_id', DB::raw('COUNT(*) as total_count'), DB::raw('MIN(id) as min_id'))
            ->groupBy('event_id', 'client_id')
            ->having('total_count', '>', 1)
            ->get();

        foreach ($records as $record) {
            // Update the first record with total quantity
            DB::table('event_additional_clients')
                ->where('id', $record->min_id)
                ->update(['quantity' => $record->total_count, 'guest_index' => 0]);

            // Delete duplicate rows
            DB::table('event_additional_clients')
                ->where('event_id', $record->event_id)
                ->where('client_id', $record->client_id)
                ->where('id', '!=', $record->min_id)
                ->delete();
        }

        Schema::table('event_additional_clients', function (Blueprint $table) {
            $table->dropColumn('guest_index');
        });
    }
};
