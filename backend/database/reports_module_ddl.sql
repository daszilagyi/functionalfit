-- ==================================================================================
-- FUNCTIONALFIT REPORTS MODULE - DATABASE SCHEMA
-- ==================================================================================
-- Version: 1.0
-- Database: MySQL 8.0+ / MariaDB 10.5+
-- Engine: InnoDB (ACID compliance, FK support)
-- Character Set: utf8mb4_unicode_ci
-- Timezone: Europe/Budapest
-- ==================================================================================

-- ==================================================================================
-- 1. EVENT_FINANCIALS - IMMUTABLE PRICING SNAPSHOT TABLE
-- ==================================================================================
-- Purpose: Capture pricing at the moment of event occurrence to prevent
-- historical data corruption when pricing rules change.
--
-- Business Rule: Financial data must remain immutable once captured.
-- Prices are "locked in" at event time and never recalculated retroactively.
--
-- Data Sources:
-- - events.entry_fee_brutto, trainer_fee_brutto (for 1:1 individual sessions)
-- - event_additional_clients.entry_fee_brutto, trainer_fee_brutto (for multi-client 1:1)
-- - class_pricing_defaults via class_registrations (for group classes)
-- ==================================================================================

CREATE TABLE IF NOT EXISTS `event_financials` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Event Reference (Polymorphic: either event_id OR class_occurrence_id)
    `source_type` ENUM('INDIVIDUAL', 'GROUP') NOT NULL COMMENT 'INDIVIDUAL=events, GROUP=class_occurrences',
    `event_id` BIGINT UNSIGNED NULL COMMENT 'FK to events (for 1:1 sessions)',
    `class_occurrence_id` BIGINT UNSIGNED NULL COMMENT 'FK to class_occurrences (for group classes)',
    `class_registration_id` BIGINT UNSIGNED NULL COMMENT 'FK to class_registrations (for GROUP)',

    -- Client/Trainer References
    `client_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to clients',
    `client_email` VARCHAR(255) NOT NULL COMMENT 'Redundant for fast indexed lookup',
    `trainer_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK to staff_profiles (instructor)',

    -- Service Classification
    `service_type_id` BIGINT UNSIGNED NULL COMMENT 'FK to service_types (for INDIVIDUAL)',
    `class_template_id` BIGINT UNSIGNED NULL COMMENT 'FK to class_templates (for GROUP)',

    -- Captured Pricing (Immutable)
    `entry_fee_brutto` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Client entry fee in HUF',
    `trainer_fee_brutto` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Trainer fee in HUF',
    `currency` VARCHAR(3) NOT NULL DEFAULT 'HUF',

    -- Price Source Traceability
    `price_source` VARCHAR(64) NULL COMMENT 'Origin: client_price_code, service_type_default, class_pricing_default',

    -- Event Context
    `occurred_at` TIMESTAMP NOT NULL COMMENT 'Event start time (Europe/Budapest)',
    `duration_minutes` INT UNSIGNED NOT NULL COMMENT 'Event duration',
    `room_id` BIGINT UNSIGNED NULL COMMENT 'FK to rooms',
    `site` ENUM('SASAD', 'TB', 'ÚJBUDA') NULL COMMENT 'Redundant site from room',

    -- Attendance Status (for reporting)
    `attendance_status` ENUM('attended', 'no_show', 'cancelled', 'late_cancel') NULL,

    -- Snapshot Metadata
    `captured_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When snapshot was created',
    `captured_by` BIGINT UNSIGNED NULL COMMENT 'FK to users (who triggered snapshot)',

    -- Audit Fields
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,

    PRIMARY KEY (`id`),

    -- Foreign Keys
    CONSTRAINT `fk_event_financials_event`
        FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_event_financials_class_occurrence`
        FOREIGN KEY (`class_occurrence_id`) REFERENCES `class_occurrences` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_event_financials_class_registration`
        FOREIGN KEY (`class_registration_id`) REFERENCES `class_registrations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_event_financials_client`
        FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_event_financials_trainer`
        FOREIGN KEY (`trainer_id`) REFERENCES `staff_profiles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_event_financials_service_type`
        FOREIGN KEY (`service_type_id`) REFERENCES `service_types` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_event_financials_class_template`
        FOREIGN KEY (`class_template_id`) REFERENCES `class_templates` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_event_financials_room`
        FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_event_financials_captured_by`
        FOREIGN KEY (`captured_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,

    -- Business Rule Constraints
    CONSTRAINT `chk_event_financials_source_exclusivity`
        CHECK (
            (source_type = 'INDIVIDUAL' AND event_id IS NOT NULL AND class_occurrence_id IS NULL) OR
            (source_type = 'GROUP' AND event_id IS NULL AND class_occurrence_id IS NOT NULL)
        ),

    -- Report Optimization Indexes
    INDEX `idx_financials_trainer_time` (`trainer_id`, `occurred_at`, `deleted_at`),
    INDEX `idx_financials_client_time` (`client_id`, `occurred_at`, `deleted_at`),
    INDEX `idx_financials_room_time` (`room_id`, `occurred_at`, `deleted_at`),
    INDEX `idx_financials_service_type_time` (`service_type_id`, `occurred_at`, `deleted_at`),
    INDEX `idx_financials_site_time` (`site`, `occurred_at`, `deleted_at`),
    INDEX `idx_financials_attendance` (`attendance_status`, `occurred_at`, `deleted_at`),
    INDEX `idx_financials_source_type` (`source_type`, `occurred_at`, `deleted_at`),
    INDEX `idx_financials_client_email` (`client_email`, `occurred_at`),
    INDEX `idx_financials_captured_at` (`captured_at`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Immutable financial snapshot for historical reporting. Prevents price changes from corrupting past data.';


-- ==================================================================================
-- 2. REPORTLINE VIEW - UNIFIED EVENT VIEW
-- ==================================================================================
-- Purpose: Combine INDIVIDUAL (1:1) and GROUP events into a single queryable view
-- for reporting purposes.
--
-- Design Decision: Using VIEW instead of materialized table to:
-- 1. Ensure real-time data accuracy
-- 2. Avoid data duplication and sync issues
-- 3. Simplify maintenance (no ETL process needed)
--
-- Performance: Indexes on base tables (events, class_occurrences, etc.) ensure
-- acceptable query performance. If performance degrades, consider materialized view
-- or reporting-specific denormalized table.
-- ==================================================================================

CREATE OR REPLACE VIEW `v_reportline` AS
-- INDIVIDUAL EVENTS (1:1 sessions including multi-client)
SELECT
    'INDIVIDUAL' AS source,
    e.id AS source_id,
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
    ct.name AS class_name,

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
  AND cr.deleted_at IS NULL;


-- ==================================================================================
-- 3. REPORT OPTIMIZATION INDEXES ON EXISTING TABLES
-- ==================================================================================
-- These indexes improve performance for common reporting queries.
-- Some may already exist - run conditionally in migration.
-- ==================================================================================

-- Events table indexes (if not already present)
-- Already exist: idx_collision_staff (staff_id, starts_at, ends_at, deleted_at)

-- Additional indexes for service_type filtering
CREATE INDEX IF NOT EXISTS `idx_events_service_type_time`
    ON `events` (`service_type_id`, `starts_at`, `ends_at`, `deleted_at`);

-- Class occurrences - trainer + time (already exists as idx_class_time_range)
-- No additional index needed

-- Class registrations - additional index for client history
CREATE INDEX IF NOT EXISTS `idx_class_registrations_client_time`
    ON `class_registrations` (`client_id`, `booked_at`, `deleted_at`);


-- ==================================================================================
-- 4. SEED DATA (Optional - for testing)
-- ==================================================================================
-- Service types should already exist from previous migrations
-- This is placeholder for reference

-- Example: Ensure service types exist
-- INSERT INTO service_types (code, name, default_entry_fee_brutto, default_trainer_fee_brutto, is_active, created_at, updated_at)
-- VALUES
--     ('GYOGYTORNA', 'Gyógytorna', 8000, 6000, 1, NOW(), NOW()),
--     ('PT', 'Personal Training', 12000, 8000, 1, NOW(), NOW()),
--     ('MASSZAZS', 'Masszázs', 10000, 7000, 1, NOW(), NOW())
-- ON DUPLICATE KEY UPDATE updated_at = NOW();


-- ==================================================================================
-- 5. EXAMPLE QUERIES FOR TESTING
-- ==================================================================================

-- Example 1: Trainer revenue report for November 2025
/*
SELECT
    trainer_id,
    trainer_name,
    COUNT(*) AS total_sessions,
    SUM(CASE WHEN unified_status = 'attended' THEN 1 ELSE 0 END) AS attended_sessions,
    SUM(CASE WHEN unified_status = 'attended' THEN trainer_fee_brutto ELSE 0 END) AS total_revenue_huf,
    SUM(hours) AS total_hours
FROM v_reportline
WHERE occurred_at >= '2025-11-01'
  AND occurred_at < '2025-12-01'
  AND deleted_at IS NULL
GROUP BY trainer_id, trainer_name
ORDER BY total_revenue_huf DESC;
*/

-- Example 2: Client attendance history
/*
SELECT
    client_id,
    client_name,
    client_email,
    source,
    service_type_name,
    class_name,
    occurred_at,
    duration_minutes,
    entry_fee_brutto,
    unified_status
FROM v_reportline
WHERE client_id = 1
  AND occurred_at >= '2025-01-01'
  AND deleted_at IS NULL
ORDER BY occurred_at DESC;
*/

-- Example 3: Room utilization by site
/*
SELECT
    site,
    room_name,
    COUNT(*) AS total_sessions,
    SUM(duration_minutes) AS total_minutes,
    SUM(hours) AS total_hours,
    SUM(CASE WHEN unified_status = 'attended' THEN 1 ELSE 0 END) AS attended_sessions
FROM v_reportline
WHERE occurred_at >= '2025-11-01'
  AND occurred_at < '2025-12-01'
  AND site IS NOT NULL
  AND deleted_at IS NULL
GROUP BY site, room_name
ORDER BY site, total_hours DESC;
*/

-- Example 4: Service type revenue breakdown
/*
SELECT
    service_type_name,
    COUNT(*) AS total_sessions,
    SUM(CASE WHEN unified_status = 'attended' THEN 1 ELSE 0 END) AS attended_sessions,
    SUM(CASE WHEN unified_status = 'attended' THEN entry_fee_brutto ELSE 0 END) AS client_revenue_huf,
    SUM(CASE WHEN unified_status = 'attended' THEN trainer_fee_brutto ELSE 0 END) AS trainer_cost_huf,
    SUM(CASE WHEN unified_status = 'attended' THEN (entry_fee_brutto - trainer_fee_brutto) ELSE 0 END) AS gross_profit_huf
FROM v_reportline
WHERE source = 'INDIVIDUAL'
  AND occurred_at >= '2025-11-01'
  AND occurred_at < '2025-12-01'
  AND deleted_at IS NULL
GROUP BY service_type_name
ORDER BY gross_profit_huf DESC;
*/
