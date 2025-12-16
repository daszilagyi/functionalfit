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
     * Adds payment_status to track whether a booking was paid via pass credit
     * or is unpaid (added to client's unpaid_balance).
     */
    public function up(): void
    {
        Schema::table('class_registrations', function (Blueprint $table) {
            $table->enum('payment_status', ['paid', 'unpaid', 'pending'])
                ->default('paid')
                ->after('credits_used')
                ->comment('paid = used pass credit, unpaid = added to unpaid_balance, pending = waitlist');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_registrations', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
