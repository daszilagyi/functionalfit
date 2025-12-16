<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make trainer_id, room_id, and weekly_rrule nullable to allow
     * creating class templates without assigning them to a specific
     * trainer, room, or schedule upfront.
     */
    public function up(): void
    {
        // SQLite doesn't support altering columns, so we need to recreate the table
        if (DB::getDriverName() === 'sqlite') {
            // Create a temporary table with the new schema
            Schema::create('class_templates_new', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('trainer_id')->nullable();
                $table->unsignedBigInteger('room_id')->nullable();
                $table->string('weekly_rrule')->nullable()->comment('RFC 5545 RRULE format');
                $table->integer('duration_min')->unsigned()->comment('Duration in minutes');
                $table->integer('capacity')->unsigned();
                $table->integer('credits_required')->unsigned()->default(1);
                $table->decimal('base_price_huf', 10, 2)->default(1000)->comment('Base price in HUF when booking without an active pass');
                $table->json('tags')->nullable()->comment('Categories: yoga, beginner, cardio, etc.');
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                $table->softDeletes();
            });

            // Copy data from old table to new table
            DB::statement('INSERT INTO class_templates_new (id, title, description, trainer_id, room_id, weekly_rrule, duration_min, capacity, credits_required, base_price_huf, tags, status, created_at, updated_at, deleted_at) SELECT id, title, description, trainer_id, room_id, weekly_rrule, duration_min, capacity, credits_required, base_price_huf, tags, status, created_at, updated_at, deleted_at FROM class_templates');

            // Drop old table and rename new one
            Schema::drop('class_templates');
            Schema::rename('class_templates_new', 'class_templates');

            // Re-add indexes
            Schema::table('class_templates', function (Blueprint $table) {
                $table->index(['trainer_id', 'deleted_at']);
                $table->index(['room_id', 'deleted_at']);
                $table->index('status');
                $table->index('title');
            });
        } else {
            // For MySQL/PostgreSQL, we can alter columns directly
            Schema::table('class_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('trainer_id')->nullable()->change();
                $table->unsignedBigInteger('room_id')->nullable()->change();
                $table->string('weekly_rrule')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This migration is not fully reversible for SQLite
        // because we can't easily make columns NOT NULL with existing NULL values
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('class_templates', function (Blueprint $table) {
                $table->foreignId('trainer_id')->constrained('staff_profiles')->onDelete('restrict')->change();
                $table->foreignId('room_id')->constrained('rooms')->onDelete('restrict')->change();
                $table->string('weekly_rrule')->change();
            });
        }
    }
};
