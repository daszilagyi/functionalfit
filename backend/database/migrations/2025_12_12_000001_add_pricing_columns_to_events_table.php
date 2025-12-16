<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Pricing for main client (stored in client_id)
            $table->unsignedInteger('entry_fee_brutto')->nullable()->after('service_type_id');
            $table->unsignedInteger('trainer_fee_brutto')->nullable()->after('entry_fee_brutto');
            $table->string('currency', 3)->default('HUF')->after('trainer_fee_brutto');
            $table->string('price_source', 32)->nullable()->after('currency')
                ->comment('client_price_code or service_type_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['entry_fee_brutto', 'trainer_fee_brutto', 'currency', 'price_source']);
        });
    }
};
