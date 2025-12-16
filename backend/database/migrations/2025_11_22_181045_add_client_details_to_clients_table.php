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
        Schema::table('clients', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('full_name');
            $table->string('emergency_contact_name')->nullable()->after('date_of_birth');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['date_of_birth', 'emergency_contact_name', 'emergency_contact_phone']);
        });
    }
};
