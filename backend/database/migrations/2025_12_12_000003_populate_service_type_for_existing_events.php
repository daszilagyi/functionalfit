<?php

declare(strict_types=1);

use App\Models\ServiceType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find or create PT service type
        $ptServiceType = ServiceType::where('code', 'PT')->first();

        if (!$ptServiceType) {
            $ptServiceType = ServiceType::create([
                'code' => 'PT',
                'name' => 'Személyi edzés',
                'description' => 'Personal training - alapértelmezett szolgáltatás típus',
                'default_entry_fee_brutto' => 15000,
                'default_trainer_fee_brutto' => 10000,
                'is_active' => true,
            ]);
        }

        // Update all INDIVIDUAL events with NULL service_type_id
        DB::table('events')
            ->where('type', 'INDIVIDUAL')
            ->whereNull('service_type_id')
            ->update(['service_type_id' => $ptServiceType->id]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't revert the service_type_id assignments as it would break data integrity
        // The PT service type is also kept as it may be referenced by other records
    }
};
