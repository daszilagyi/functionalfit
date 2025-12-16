<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Staff profiles: Extended information for staff members.
     * One-to-one relationship with users table.
     */
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();

            // Foreign key to users
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Profile information
            $table->text('bio')->nullable();
            $table->text('skills')->nullable();
            $table->enum('default_site', ['SASAD', 'TB', 'ÚJBUDA'])->nullable();
            $table->boolean('visibility')->default(true)->comment('Show in client-facing selections');

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['user_id', 'deleted_at']);
            $table->index('default_site');
            $table->index('visibility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
