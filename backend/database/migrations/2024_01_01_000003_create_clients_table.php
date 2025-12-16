<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Clients table: Client records that may or may not have user accounts.
     * user_id is nullable to allow manual client creation without login credentials.
     */
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();

            // Optional link to user account
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('restrict');

            // Client information
            $table->string('full_name');
            $table->date('date_of_joining')->nullable();
            $table->text('notes')->nullable()->comment('Encrypted PII field');

            // GDPR compliance
            $table->timestamp('gdpr_consent_at')->nullable();

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['user_id', 'deleted_at']);
            $table->index('full_name');
            $table->index('date_of_joining');
            $table->index('gdpr_consent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
