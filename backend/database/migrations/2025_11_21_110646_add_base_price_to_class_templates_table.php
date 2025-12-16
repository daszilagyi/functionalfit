<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Add base_price_huf field to set default price for classes booked without a pass
     */
    public function up(): void
    {
        Schema::table('class_templates', function (Blueprint $table) {
            $table->decimal('base_price_huf', 10, 2)->default(1000)->after('credits_required')
                ->comment('Base price in HUF when booking without an active pass');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_templates', function (Blueprint $table) {
            $table->dropColumn('base_price_huf');
        });
    }
};
