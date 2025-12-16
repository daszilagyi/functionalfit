<?php

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
        Schema::table('passes', function (Blueprint $table) {
            $table->string('external_reference')->nullable()->unique()->after('external_order_id')
                ->comment('Idempotency key: woocommerce_order_X or stripe_session_X');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->dropColumn('external_reference');
        });
    }
};
