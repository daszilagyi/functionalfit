<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set default debug email address if not already set
        $existing = DB::table('settings')->where('key', 'debug_email_address')->first();

        if (!$existing) {
            DB::table('settings')->insert([
                'key' => 'debug_email_address',
                'value' => json_encode('daszilagyi@gmail.com'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Enable debug email if not set
        $existingEnabled = DB::table('settings')->where('key', 'debug_email_enabled')->first();

        if (!$existingEnabled) {
            DB::table('settings')->insert([
                'key' => 'debug_email_enabled',
                'value' => json_encode(true),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't remove settings on rollback - they may have been modified by users
    }
};
