<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Users table: Master authentication and RBAC table.
     * Roles: client, staff, admin
     * Status: active, inactive, suspended
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // RBAC
            $table->enum('role', ['client', 'staff', 'admin'])->default('client');

            // Core identity
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');

            // Account status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamp('last_login_at')->nullable();

            // Laravel Sanctum tokens
            $table->rememberToken();

            // Audit fields
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['email', 'deleted_at']);
            $table->index(['role', 'status']);
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
