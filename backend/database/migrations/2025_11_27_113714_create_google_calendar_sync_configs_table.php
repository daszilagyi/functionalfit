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
        Schema::create('google_calendar_sync_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Configuration name for reference');
            $table->string('google_calendar_id')->comment('Google Calendar ID');
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete()->comment('Optional: sync to specific room');
            $table->boolean('sync_enabled')->default(true);
            $table->enum('sync_direction', ['import', 'export', 'both'])->default('both');
            $table->text('service_account_json')->nullable()->comment('Optional: dedicated service account credentials');
            $table->json('sync_options')->nullable()->comment('Additional sync configuration (filters, mappings, etc.)');
            $table->timestamp('last_import_at')->nullable();
            $table->timestamp('last_export_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sync_enabled', 'sync_direction']);
        });

        Schema::create('google_calendar_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_config_id')->nullable()->constrained('google_calendar_sync_configs')->nullOnDelete();
            $table->enum('operation', ['import', 'export']);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'cancelled']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('events_processed')->default(0);
            $table->integer('events_created')->default(0);
            $table->integer('events_updated')->default(0);
            $table->integer('events_skipped')->default(0);
            $table->integer('events_failed')->default(0);
            $table->integer('conflicts_detected')->default(0);
            $table->json('filters')->nullable()->comment('Date range, room filters applied');
            $table->json('conflicts')->nullable()->comment('List of conflicts requiring user resolution');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sync_config_id', 'operation']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_sync_logs');
        Schema::dropIfExists('google_calendar_sync_configs');
    }
};
