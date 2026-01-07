<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Create v_reportline view for unified event reporting.
     *
     * Purpose: Combine INDIVIDUAL (1:1 events) and GROUP (class occurrences)
     * into a single queryable view for simplified reporting.
     *
     * Design Decision: Using VIEW instead of materialized table:
     * - Pros: Real-time accuracy, no data duplication, simpler maintenance
     * - Cons: Query performance depends on base table indexes
     *
     * If performance becomes an issue, consider:
     * 1. Materialized view (if MySQL 8.0.13+ / MariaDB 10.5+)
     * 2. Denormalized reporting table with scheduled refresh
     * 3. Event sourcing pattern with pre-computed aggregates
     *
     * Columns:
     * - source: 'INDIVIDUAL' or 'GROUP'
     * - occurred_at: Event start timestamp
     * - trainer_id, client_id: Foreign keys for filtering
     * - entry_fee_brutto, trainer_fee_brutto: Pricing
     * - duration_minutes, hours: Time tracking
     * - unified_status: Normalized status (attended, no_show, cancelled, scheduled)
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_reportline');

        DB::statement("
            CREATE VIEW v_reportline AS
            -- INDIVIDUAL EVENTS (1:1 sessions - main client)
            SELECT
                'INDIVIDUAL' AS source,
                CAST(e.id AS CHAR) AS source_id,
                e.id AS event_id,
                NULL AS class_occurrence_id,
                NULL AS class_registration_id,

                -- Main client from events table
                e.client_id,
                COALESCE(u.email, 'unknown@example.com') AS client_email,
                c.full_name AS client_name,

                -- Trainer
                e.staff_id AS trainer_id,
                trainer_user.name AS trainer_name,

                -- Service classification
                e.service_type_id,
                st.name AS service_type_name,
                NULL AS class_template_id,
                NULL AS class_name,

                -- Location
                e.room_id,
                r.name AS room_name,
                r.site,

                -- Timing
                e.starts_at AS occurred_at,
                TIMESTAMPDIFF(MINUTE, e.starts_at, e.ends_at) AS duration_minutes,
                ROUND(TIMESTAMPDIFF(MINUTE, e.starts_at, e.ends_at) / 60.0, 2) AS hours,

                -- Pricing
                COALESCE(e.entry_fee_brutto, 0) AS entry_fee_brutto,
                COALESCE(e.trainer_fee_brutto, 0) AS trainer_fee_brutto,
                e.currency,
                e.price_source,

                -- Status
                e.status,
                e.attendance_status,
                CASE
                    WHEN e.attendance_status = 'attended' THEN 'attended'
                    WHEN e.attendance_status = 'no_show' THEN 'no_show'
                    WHEN e.status = 'cancelled' THEN 'cancelled'
                    ELSE 'scheduled'
                END AS unified_status,

                -- Metadata
                e.created_at,
                e.updated_at,
                e.deleted_at

            FROM events e
            LEFT JOIN clients c ON e.client_id = c.id
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN staff_profiles sp ON e.staff_id = sp.id
            LEFT JOIN users trainer_user ON sp.user_id = trainer_user.id
            LEFT JOIN service_types st ON e.service_type_id = st.id
            LEFT JOIN rooms r ON e.room_id = r.id
            WHERE e.type = 'INDIVIDUAL'
              AND e.deleted_at IS NULL

            UNION ALL

            -- ADDITIONAL CLIENTS from multi-client events
            SELECT
                'INDIVIDUAL' AS source,
                CONCAT('event_', e.id, '_client_', eac.id) AS source_id,
                e.id AS event_id,
                NULL AS class_occurrence_id,
                NULL AS class_registration_id,

                -- Additional client
                eac.client_id,
                COALESCE(u2.email, 'unknown@example.com') AS client_email,
                c2.full_name AS client_name,

                -- Trainer
                e.staff_id AS trainer_id,
                trainer_user.name AS trainer_name,

                -- Service classification
                e.service_type_id,
                st.name AS service_type_name,
                NULL AS class_template_id,
                NULL AS class_name,

                -- Location
                e.room_id,
                r.name AS room_name,
                r.site,

                -- Timing
                e.starts_at AS occurred_at,
                TIMESTAMPDIFF(MINUTE, e.starts_at, e.ends_at) AS duration_minutes,
                ROUND(TIMESTAMPDIFF(MINUTE, e.starts_at, e.ends_at) / 60.0, 2) AS hours,

                -- Pricing (from additional client record)
                COALESCE(eac.entry_fee_brutto, 0) AS entry_fee_brutto,
                COALESCE(eac.trainer_fee_brutto, 0) AS trainer_fee_brutto,
                eac.currency,
                eac.price_source,

                -- Status
                e.status,
                eac.attendance_status,
                CASE
                    WHEN eac.attendance_status = 'attended' THEN 'attended'
                    WHEN eac.attendance_status = 'no_show' THEN 'no_show'
                    WHEN e.status = 'cancelled' THEN 'cancelled'
                    ELSE 'scheduled'
                END AS unified_status,

                -- Metadata
                e.created_at,
                e.updated_at,
                e.deleted_at

            FROM events e
            INNER JOIN event_additional_clients eac ON e.id = eac.event_id
            LEFT JOIN clients c2 ON eac.client_id = c2.id
            LEFT JOIN users u2 ON c2.user_id = u2.id
            LEFT JOIN staff_profiles sp ON e.staff_id = sp.id
            LEFT JOIN users trainer_user ON sp.user_id = trainer_user.id
            LEFT JOIN service_types st ON e.service_type_id = st.id
            LEFT JOIN rooms r ON e.room_id = r.id
            WHERE e.type = 'INDIVIDUAL'
              AND e.deleted_at IS NULL

            UNION ALL

            -- GROUP CLASSES (class_occurrences + registrations)
            SELECT
                'GROUP' AS source,
                CONCAT('class_', co.id, '_reg_', cr.id) AS source_id,
                NULL AS event_id,
                co.id AS class_occurrence_id,
                cr.id AS class_registration_id,

                -- Client
                cr.client_id,
                COALESCE(u3.email, 'unknown@example.com') AS client_email,
                c3.full_name AS client_name,

                -- Trainer
                co.trainer_id,
                trainer_user2.name AS trainer_name,

                -- Service classification
                NULL AS service_type_id,
                NULL AS service_type_name,
                co.template_id AS class_template_id,
                ct.title AS class_name,

                -- Location
                co.room_id,
                r2.name AS room_name,
                r2.site,

                -- Timing
                co.starts_at AS occurred_at,
                TIMESTAMPDIFF(MINUTE, co.starts_at, co.ends_at) AS duration_minutes,
                ROUND(TIMESTAMPDIFF(MINUTE, co.starts_at, co.ends_at) / 60.0, 2) AS hours,

                -- Pricing (from class_pricing_defaults via registration time)
                COALESCE(
                    (SELECT cpd.entry_fee_brutto
                     FROM class_pricing_defaults cpd
                     WHERE cpd.class_template_id = co.template_id
                       AND cpd.valid_from <= co.starts_at
                       AND (cpd.valid_until IS NULL OR cpd.valid_until >= co.starts_at)
                       AND cpd.is_active = 1
                       AND cpd.deleted_at IS NULL
                     ORDER BY cpd.valid_from DESC
                     LIMIT 1),
                    0
                ) AS entry_fee_brutto,
                COALESCE(
                    (SELECT cpd.trainer_fee_brutto
                     FROM class_pricing_defaults cpd
                     WHERE cpd.class_template_id = co.template_id
                       AND cpd.valid_from <= co.starts_at
                       AND (cpd.valid_until IS NULL OR cpd.valid_until >= co.starts_at)
                       AND cpd.is_active = 1
                       AND cpd.deleted_at IS NULL
                     ORDER BY cpd.valid_from DESC
                     LIMIT 1),
                    0
                ) AS trainer_fee_brutto,
                'HUF' AS currency,
                'class_pricing_default' AS price_source,

                -- Status
                co.status,
                CASE
                    WHEN cr.status = 'attended' THEN 'attended'
                    WHEN cr.status = 'no_show' THEN 'no_show'
                    WHEN cr.status = 'cancelled' OR co.status = 'cancelled' THEN 'cancelled'
                    ELSE 'scheduled'
                END AS attendance_status,
                CASE
                    WHEN cr.status = 'attended' THEN 'attended'
                    WHEN cr.status = 'no_show' THEN 'no_show'
                    WHEN cr.status = 'cancelled' OR co.status = 'cancelled' THEN 'cancelled'
                    ELSE 'scheduled'
                END AS unified_status,

                -- Metadata
                co.created_at,
                co.updated_at,
                co.deleted_at

            FROM class_occurrences co
            INNER JOIN class_registrations cr ON co.id = cr.occurrence_id
            LEFT JOIN class_templates ct ON co.template_id = ct.id
            LEFT JOIN clients c3 ON cr.client_id = c3.id
            LEFT JOIN users u3 ON c3.user_id = u3.id
            LEFT JOIN staff_profiles sp2 ON co.trainer_id = sp2.id
            LEFT JOIN users trainer_user2 ON sp2.user_id = trainer_user2.id
            LEFT JOIN rooms r2 ON co.room_id = r2.id
            WHERE co.deleted_at IS NULL
              AND cr.deleted_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_reportline');
    }
};
