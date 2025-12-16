<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a quantity column to event_additional_clients to allow the same
     * Technical Guest client to represent multiple unknown participants.
     *
     * For regular clients: quantity = 1 (default, enforced by UNIQUE constraint)
     * For Technical Guest: quantity >= 1 (can represent multiple unknown people)
     */
    public function up(): void
    {
        Schema::table('event_additional_clients', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_additional_clients', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
