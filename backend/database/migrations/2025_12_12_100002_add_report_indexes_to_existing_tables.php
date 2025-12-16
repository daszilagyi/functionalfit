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
     * Add report optimization indexes to existing tables.
     *
     * These indexes significantly improve performance for:
     * - Trainer revenue reports (trainer_id + time range)
     * - Service type analytics (service_type_id + time range)
     * - Client history queries (client_id + time range)
     *
     * Rationale: Reporting queries frequently filter by:
     * 1. Entity (trainer, service type, client)
     * 2. Time range (occurred_at, starts_at, booked_at)
     * 3. Soft delete status (deleted_at)
     *
     * Composite indexes covering these columns enable efficient range scans.
     */
    public function up(): void
    {
        // Events table: service_type_id filtering
        // (staff_id indexes already exist from idx_collision_staff)
        Schema::table('events', function (Blueprint $table) {
            // Skip if index already exists
            if (!$this->indexExists('events', 'idx_events_service_type_time')) {
                $table->index(
                    ['service_type_id', 'starts_at', 'ends_at', 'deleted_at'],
                    'idx_events_service_type_time'
                );
            }
        });

        // Class registrations: client history optimization
        Schema::table('class_registrations', function (Blueprint $table) {
            // Skip if index already exists
            if (!$this->indexExists('class_registrations', 'idx_class_registrations_client_time')) {
                $table->index(
                    ['client_id', 'booked_at', 'deleted_at'],
                    'idx_class_registrations_client_time'
                );
            }
        });

        // Event additional clients: reporting optimization
        Schema::table('event_additional_clients', function (Blueprint $table) {
            // Add attendance status index for filtering
            if (!$this->indexExists('event_additional_clients', 'idx_eac_attendance')) {
                $table->index('attendance_status', 'idx_eac_attendance');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if ($this->indexExists('events', 'idx_events_service_type_time')) {
                $table->dropIndex('idx_events_service_type_time');
            }
        });

        Schema::table('class_registrations', function (Blueprint $table) {
            if ($this->indexExists('class_registrations', 'idx_class_registrations_client_time')) {
                $table->dropIndex('idx_class_registrations_client_time');
            }
        });

        Schema::table('event_additional_clients', function (Blueprint $table) {
            if ($this->indexExists('event_additional_clients', 'idx_eac_attendance')) {
                $table->dropIndex('idx_eac_attendance');
            }
        });
    }

    /**
     * Check if an index exists on a table.
     * Supports both MySQL and SQLite.
     *
     * @param string $table
     * @param string $index
     * @return bool
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: query sqlite_master for indexes
            $indexes = $connection->select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $index]
            );
            return count($indexes) > 0;
        }

        // MySQL/MariaDB: use INFORMATION_SCHEMA
        $databaseName = $connection->getDatabaseName();
        $indexes = $connection->select(
            "SELECT INDEX_NAME
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?",
            [$databaseName, $table, $index]
        );

        return count($indexes) > 0;
    }
};
