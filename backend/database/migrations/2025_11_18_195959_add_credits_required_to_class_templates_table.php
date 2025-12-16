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
        Schema::table('class_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('credits_required')->default(1)->after('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_templates', function (Blueprint $table) {
            $table->dropColumn('credits_required');
        });
    }
};
