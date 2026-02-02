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
        // Set default company name if not already set
        $existingCompanyName = DB::table('settings')->where('key', 'email_company_name')->first();

        if (!$existingCompanyName) {
            DB::table('settings')->insert([
                'key' => 'email_company_name',
                'value' => json_encode('FunctionalFit Egeszsegkozpont'),
                'updated_at' => now(),
            ]);
        }

        // Set default support email if not already set
        $existingSupportEmail = DB::table('settings')->where('key', 'email_support_email')->first();

        if (!$existingSupportEmail) {
            DB::table('settings')->insert([
                'key' => 'email_support_email',
                'value' => json_encode('support@functionalfit.hu'),
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
