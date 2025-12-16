# Reports Module - Schema Documentation

## Executive Summary

The Reports Module provides a robust, performant, and audit-compliant database architecture for financial and operational reporting in the FunctionalFit calendar system. It addresses the critical business requirement of **immutable historical pricing** while enabling flexible, real-time analytics.

### Key Components
1. **event_financials** table - Immutable financial snapshots
2. **v_reportline** view - Unified event view (INDIVIDUAL + GROUP)
3. **Report-optimized indexes** - Performance enhancements

### Design Principles
- **Data Integrity**: Immutable snapshots prevent historical data corruption
- **3NF Compliance**: Normalized base schema with controlled denormalization
- **Performance**: Strategic indexes for time-range queries
- **Audit Trail**: Complete traceability of pricing sources

---

## 1. event_financials Table

### Purpose
Capture pricing and event context at the moment of occurrence, creating an immutable financial record that remains accurate even when pricing rules change.

### Business Problem Solved
**Without event_financials:**
- Historical reports recalculate prices using current rules
- Past month's revenue changes when pricing is updated
- Audit trail is lost (can't prove what client actually paid)
- Disputes cannot be resolved with historical data

**With event_financials:**
- Financial data is "locked in" at event time
- Historical reports always show accurate past prices
- Complete audit trail with price_source tracking
- Regulatory compliance (GDPR, financial audits)

### Column Reference

#### Event Reference (Polymorphic)
| Column | Type | Description | Business Rule |
|--------|------|-------------|---------------|
| `source_type` | ENUM('INDIVIDUAL', 'GROUP') | Discriminator for event type | INDIVIDUAL = 1:1 sessions, GROUP = class occurrences |
| `event_id` | BIGINT NULL | FK to events | Required if source_type='INDIVIDUAL' |
| `class_occurrence_id` | BIGINT NULL | FK to class_occurrences | Required if source_type='GROUP' |
| `class_registration_id` | BIGINT NULL | FK to class_registrations | Set for GROUP events (tracks which registration) |

**CHECK Constraint**: Exactly ONE of event_id or class_occurrence_id must be set based on source_type.

#### Participant References
| Column | Type | Description | Business Rule |
|--------|------|-------------|---------------|
| `client_id` | BIGINT | FK to clients | Cannot be NULL (every financial record has a payer) |
| `client_email` | VARCHAR(255) | Redundant email | Denormalized for fast indexed lookup without JOIN |
| `trainer_id` | BIGINT | FK to staff_profiles | Cannot be NULL (every session has an instructor) |

**Denormalization Rationale**: `client_email` is duplicated to enable indexed email-based queries without joining clients -> users. This violates 3NF but is justified for reporting performance (common filter in financial exports).

#### Service Classification
| Column | Type | Description | Business Rule |
|--------|------|-------------|---------------|
| `service_type_id` | BIGINT NULL | FK to service_types | Set for INDIVIDUAL events (e.g., GYOGYTORNA, PT) |
| `class_template_id` | BIGINT NULL | FK to class_templates | Set for GROUP events (e.g., Pilates, Jóga) |

#### Captured Pricing (IMMUTABLE)
| Column | Type | Description | Business Rule |
|--------|------|-------------|---------------|
| `entry_fee_brutto` | INT UNSIGNED | Client entry fee in HUF | **IMMUTABLE** - Never recalculate |
| `trainer_fee_brutto` | INT UNSIGNED | Trainer compensation in HUF | **IMMUTABLE** - Never recalculate |
| `currency` | VARCHAR(3) | ISO 4217 code | Default 'HUF', supports EUR, USD for future |
| `price_source` | VARCHAR(64) | Traceability identifier | Values: 'client_price_code', 'service_type_default', 'class_pricing_default' |

**Critical Business Rule**: Once captured, pricing columns CANNOT be updated. If incorrect, soft delete (set deleted_at) and create new record.

#### Event Context
| Column | Type | Description | Business Rule |
|--------|------|-------------|---------------|
| `occurred_at` | TIMESTAMP | Event start time | Europe/Budapest timezone |
| `duration_minutes` | INT UNSIGNED | Event duration | Calculated from starts_at/ends_at at capture time |
| `room_id` | BIGINT NULL | FK to rooms | Can be NULL for off-site events |
| `site` | ENUM('SASAD', 'TB', 'ÚJBUDA') | Location | Denormalized from room for fast filtering |
| `attendance_status` | ENUM | attended, no_show, cancelled, late_cancel | Determines if revenue should be counted |

**Denormalization Rationale**: `site` is duplicated from rooms table to enable site-based aggregations without JOIN. Common reporting dimension.

#### Snapshot Metadata
| Column | Type | Description | Business Rule |
|--------|------|-------------|---------------|
| `captured_at` | TIMESTAMP | When snapshot was created | System-generated, cannot be modified |
| `captured_by` | BIGINT NULL | FK to users | User who triggered capture (e.g., marked attendance) |

### Indexes

All indexes follow the pattern: `(filter_column, time_column, soft_delete_column)` to enable efficient covering index scans for time-range reports.

| Index Name | Columns | Use Case |
|------------|---------|----------|
| `idx_financials_trainer_time` | (trainer_id, occurred_at, deleted_at) | Trainer revenue reports |
| `idx_financials_client_time` | (client_id, occurred_at, deleted_at) | Client history, billing statements |
| `idx_financials_room_time` | (room_id, occurred_at, deleted_at) | Room utilization reports |
| `idx_financials_service_type_time` | (service_type_id, occurred_at, deleted_at) | Service category analytics |
| `idx_financials_site_time` | (site, occurred_at, deleted_at) | Multi-site comparisons |
| `idx_financials_attendance` | (attendance_status, occurred_at, deleted_at) | Attendance rate analytics |
| `idx_financials_source_type` | (source_type, occurred_at, deleted_at) | INDIVIDUAL vs GROUP comparison |
| `idx_financials_client_email` | (client_email, occurred_at) | Email-based invoice generation |

**Index Bloat Prevention**: All indexes include `deleted_at` for soft delete filtering. Consider OPTIMIZE TABLE quarterly.

### Foreign Key Behavior

| FK Constraint | ON DELETE | Rationale |
|---------------|-----------|-----------|
| event_id | CASCADE | If event deleted, its financial records are invalid |
| class_occurrence_id | CASCADE | If class deleted, its financial records are invalid |
| class_registration_id | SET NULL | Registration can be deleted, financial record remains |
| client_id, trainer_id | RESTRICT | Cannot delete client/staff with financial records |
| service_type_id, class_template_id | SET NULL | Config deletion shouldn't cascade to financials |
| room_id | SET NULL | Room deletion shouldn't cascade to financials |
| captured_by | SET NULL | User deletion shouldn't affect audit records |

### Data Capture Triggers

**Automatic Capture Events:**
1. Event attendance marked as 'attended' or 'no_show'
2. Event status changed to 'completed'
3. Class registration status changed to 'attended' or 'no_show'
4. Manual trigger via admin command: `php artisan reports:capture-financials`

**Implementation**: Application-level event listeners (e.g., Laravel Observers) rather than database triggers for:
- Better testability
- Easier debugging
- Framework integration (auth, logging)

### Data Integrity Rules

**Validation Checklist** (enforce in application layer):
- [ ] source_type matches event_id/class_occurrence_id exclusivity
- [ ] entry_fee_brutto and trainer_fee_brutto are non-negative
- [ ] occurred_at is not in the future
- [ ] price_source is traceable to actual pricing record
- [ ] If attendance_status='attended', pricing must be > 0 (unless free session)

---

## 2. v_reportline View

### Purpose
Provide a unified, queryable interface for both INDIVIDUAL (1:1) and GROUP (class) events, eliminating the need for application-level data merging.

### Architecture Decision: VIEW vs TABLE

**Chosen Approach**: Database VIEW (not materialized)

**Rationale**:
| Criterion | VIEW | Materialized Table | Winner |
|-----------|------|-------------------|--------|
| Real-time accuracy | Always current | Stale until refresh | VIEW |
| Storage cost | Zero (virtual) | Duplicates base data | VIEW |
| Query performance | Depends on base indexes | Fast (pre-aggregated) | MATERIALIZED |
| Maintenance complexity | Low (auto-updated) | High (ETL scheduling) | VIEW |
| Use case fit | Recent reports, dashboards | Historical analytics | VIEW |

**Performance Mitigation**: Base table indexes ensure acceptable VIEW query performance for typical reporting periods (1-3 months). For multi-year analytics, use event_financials snapshot table instead.

### View Structure

**Three UNION Branches**:
1. **INDIVIDUAL Events - Main Client**: events table (events.client_id)
2. **INDIVIDUAL Events - Additional Clients**: event_additional_clients pivot
3. **GROUP Classes**: class_occurrences + class_registrations

### Column Mapping

#### Source Identification
| Column | Type | Description | Values |
|--------|------|-------------|--------|
| `source` | VARCHAR | Event category | 'INDIVIDUAL' or 'GROUP' |
| `source_id` | VARCHAR | Composite identifier | event.id, "event_X_client_Y", "class_X_reg_Y" |
| `event_id` | BIGINT | FK to events | NULL for GROUP |
| `class_occurrence_id` | BIGINT | FK to class_occurrences | NULL for INDIVIDUAL |
| `class_registration_id` | BIGINT | FK to class_registrations | NULL for INDIVIDUAL |

**Business Logic**: `source_id` is a string to handle mixed types (pure ID for main client, composite for additional/group).

#### Participant Data
| Column | Type | Source | Description |
|--------|------|--------|-------------|
| `client_id` | BIGINT | events.client_id / eac.client_id / cr.client_id | Participant |
| `client_email` | VARCHAR | users.email via clients | Denormalized from user |
| `client_name` | VARCHAR | clients.full_name | Display name |
| `trainer_id` | BIGINT | events.staff_id / co.trainer_id | Instructor |
| `trainer_name` | VARCHAR | users.name via staff_profiles | Display name |

#### Service Classification
| Column | Type | INDIVIDUAL Source | GROUP Source |
|--------|------|------------------|--------------|
| `service_type_id` | BIGINT | events.service_type_id | NULL |
| `service_type_name` | VARCHAR | service_types.name | NULL |
| `class_template_id` | BIGINT | NULL | class_occurrences.template_id |
| `class_name` | VARCHAR | NULL | class_templates.name |

**Query Optimization**: Filter by `source` + type_id to avoid full table scans:
```sql
-- Good: Uses source filter first
WHERE source = 'INDIVIDUAL' AND service_type_id = 1

-- Bad: Scans all events
WHERE service_type_id = 1
```

#### Location & Timing
| Column | Type | Description | Calculation |
|--------|------|-------------|-------------|
| `room_id` | BIGINT | FK to rooms | Direct from events/class_occurrences |
| `room_name` | VARCHAR | rooms.name | Denormalized for convenience |
| `site` | ENUM | rooms.site | SASAD, TB, ÚJBUDA |
| `occurred_at` | TIMESTAMP | Event start time | events.starts_at / co.starts_at |
| `duration_minutes` | INT | Duration | TIMESTAMPDIFF(MINUTE, starts_at, ends_at) |
| `hours` | DECIMAL(10,2) | Hours (decimal) | duration_minutes / 60.0 |

#### Pricing
| Column | Type | INDIVIDUAL Source | GROUP Source |
|--------|------|------------------|--------------|
| `entry_fee_brutto` | INT | events.entry_fee_brutto / eac.entry_fee_brutto | Subquery to class_pricing_defaults |
| `trainer_fee_brutto` | INT | events.trainer_fee_brutto / eac.trainer_fee_brutto | Subquery to class_pricing_defaults |
| `currency` | VARCHAR | events.currency / eac.currency | 'HUF' |
| `price_source` | VARCHAR | events.price_source / eac.price_source | 'class_pricing_default' |

**GROUP Pricing Subquery Logic**:
```sql
-- Fetches pricing valid at occurrence time
SELECT cpd.entry_fee_brutto
FROM class_pricing_defaults cpd
WHERE cpd.class_template_id = co.template_id
  AND cpd.valid_from <= co.starts_at
  AND (cpd.valid_until IS NULL OR cpd.valid_until >= co.starts_at)
  AND cpd.is_active = 1
  AND cpd.deleted_at IS NULL
ORDER BY cpd.valid_from DESC
LIMIT 1
```

**Performance Note**: This correlated subquery runs per GROUP row. For large datasets, consider pre-capturing via event_financials.

#### Status Fields
| Column | Type | Description | Values |
|--------|------|-------------|--------|
| `status` | VARCHAR | Original status | scheduled, completed, cancelled |
| `attendance_status` | VARCHAR | Attendance outcome | attended, no_show, cancelled |
| `unified_status` | VARCHAR | Normalized status | attended, no_show, cancelled, scheduled |

**Unified Status Logic**:
```sql
CASE
    WHEN attendance_status = 'attended' THEN 'attended'
    WHEN attendance_status = 'no_show' THEN 'no_show'
    WHEN status = 'cancelled' OR registration_status = 'cancelled' THEN 'cancelled'
    ELSE 'scheduled'
END
```

### Query Performance Characteristics

**Fast Queries** (utilize base indexes):
- Recent time ranges: `occurred_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)`
- Single trainer: `trainer_id = ? AND occurred_at BETWEEN ? AND ?`
- Single client: `client_id = ? AND occurred_at BETWEEN ? AND ?`
- Single site: `site = ? AND occurred_at BETWEEN ? AND ?`

**Slow Queries** (full table scans):
- Aggregations without time filter: `COUNT(*) GROUP BY trainer_id` (no date range)
- Multi-year reports: `occurred_at >= '2020-01-01'` (consider event_financials)
- Complex JOIN chains: Additional JOINs beyond view's LEFT JOINs

**Optimization Strategies**:
1. **Short time windows**: Limit to 1-3 months
2. **Pagination**: Use LIMIT + OFFSET for large result sets
3. **Index hints**: `USE INDEX (idx_collision_staff)` for specific queries
4. **Fallback to event_financials**: For historical data (>3 months ago)

---

## 3. Enhanced Indexes on Existing Tables

### events Table

**New Index**: `idx_events_service_type_time`
```sql
INDEX (service_type_id, starts_at, ends_at, deleted_at)
```

**Use Case**: Service type revenue reports
```sql
SELECT service_type_id, COUNT(*), SUM(entry_fee_brutto)
FROM events
WHERE service_type_id IN (1,2,3)
  AND starts_at >= '2025-11-01'
  AND starts_at < '2025-12-01'
  AND deleted_at IS NULL
GROUP BY service_type_id;
```

**Before Index**: Full table scan + filter
**After Index**: Index range scan (10-100x faster)

### class_registrations Table

**New Index**: `idx_class_registrations_client_time`
```sql
INDEX (client_id, booked_at, deleted_at)
```

**Use Case**: Client booking history
```sql
SELECT client_id, COUNT(*) as bookings
FROM class_registrations
WHERE client_id = ?
  AND booked_at >= '2025-01-01'
  AND deleted_at IS NULL;
```

**Before Index**: Full table scan + filter (slow for large registration history)
**After Index**: Direct client range scan

### event_additional_clients Table

**New Index**: `idx_eac_attendance`
```sql
INDEX (attendance_status)
```

**Use Case**: Multi-client attendance filtering
```sql
SELECT event_id, COUNT(*)
FROM event_additional_clients
WHERE attendance_status = 'attended'
GROUP BY event_id;
```

**Rationale**: `attendance_status` is frequently used in WHERE and GROUP BY for multi-client event analytics.

---

## 4. Data Normalization Analysis

### Normalized Data (3NF Compliant)

| Entity | Normalization Level | Justification |
|--------|---------------------|---------------|
| events | 3NF | No transitive dependencies |
| class_occurrences | 3NF | All non-key columns depend only on PK |
| class_registrations | 3NF | Pure many-to-many resolution |
| service_types | 3NF | Lookup table, fully normalized |
| client_price_codes | 3NF | Valid price history via date range |

### Controlled Denormalization (Performance Trade-offs)

#### event_financials.client_email
- **Violates**: 2NF (email depends on client_id, not event_financials PK)
- **Justification**: Avoids 2-hop JOIN (event_financials -> clients -> users) for email-based queries
- **Maintenance**: Updated via application logic when client email changes
- **Trade-off**: Acceptable because email changes are rare, and reports filter by email frequently

#### event_financials.site
- **Violates**: 3NF (site depends on room_id, transitive dependency)
- **Justification**: Site is a primary reporting dimension; JOIN to rooms adds overhead
- **Maintenance**: Updated when room.site changes (rare event)
- **Trade-off**: 100% worth it for multi-site reports

#### v_reportline (entire view)
- **Violates**: N/A (virtual, not stored)
- **Justification**: Aggregates multiple sources; cannot be normalized as single entity
- **Maintenance**: View definition updates when base schema changes
- **Trade-off**: Zero storage cost, high query flexibility

### Denormalization Decision Matrix

| Scenario | Denormalize? | Reason |
|----------|-------------|--------|
| High read:write ratio (>100:1) | YES | Read performance > write complexity |
| Frequently joined dimension (>80% of queries) | YES | Eliminate JOIN overhead |
| Volatile data (changes hourly) | NO | Sync maintenance burden too high |
| Regulatory audit trail needed | MAYBE | Consider immutable snapshots instead |
| Multi-tenant queries with isolation | NO | Denormalization breaks security boundaries |

---

## 5. Query Patterns & Performance

### Pattern 1: Trainer Monthly Revenue
```sql
SELECT
    trainer_id,
    trainer_name,
    COUNT(*) AS total_sessions,
    SUM(CASE WHEN unified_status = 'attended' THEN 1 ELSE 0 END) AS attended_sessions,
    SUM(CASE WHEN unified_status = 'attended' THEN trainer_fee_brutto ELSE 0 END) AS revenue_huf
FROM v_reportline
WHERE trainer_id = ?
  AND occurred_at >= '2025-11-01'
  AND occurred_at < '2025-12-01'
  AND deleted_at IS NULL
GROUP BY trainer_id, trainer_name;
```

**Index Used**: `idx_collision_staff` on events + `idx_class_time_range` on class_occurrences
**Performance**: O(log n) index seek + sequential scan of month's data (~100-500 rows)
**Expected Time**: <50ms for 1 month, <200ms for 1 year

### Pattern 2: Client Billing Statement
```sql
SELECT
    occurred_at,
    service_type_name,
    class_name,
    duration_minutes,
    entry_fee_brutto,
    unified_status
FROM v_reportline
WHERE client_id = ?
  AND occurred_at >= '2025-01-01'
  AND occurred_at < '2026-01-01'
  AND deleted_at IS NULL
ORDER BY occurred_at DESC;
```

**Index Used**: `idx_class_registrations_client_time` on class_registrations (for GROUP)
**Performance**: O(log n) index seek + scan of client's events
**Expected Time**: <100ms for 1 year of data (~50-200 rows per client)

### Pattern 3: Site Utilization Report
```sql
SELECT
    site,
    room_name,
    COUNT(*) AS total_sessions,
    SUM(duration_minutes) AS total_minutes,
    SUM(CASE WHEN unified_status = 'attended' THEN 1 ELSE 0 END) AS attended
FROM v_reportline
WHERE site = 'SASAD'
  AND occurred_at >= '2025-11-01'
  AND occurred_at < '2025-12-01'
  AND deleted_at IS NULL
GROUP BY site, room_name
ORDER BY total_minutes DESC;
```

**Index Used**: `idx_collision_room` on events + class_occurrences
**Performance**: Full index scan of site's events (filtered by room)
**Expected Time**: <150ms for 1 month per site

### Pattern 4: Service Type Revenue Breakdown (INDIVIDUAL only)
```sql
SELECT
    service_type_name,
    COUNT(*) AS sessions,
    SUM(CASE WHEN unified_status = 'attended' THEN entry_fee_brutto ELSE 0 END) AS client_revenue,
    SUM(CASE WHEN unified_status = 'attended' THEN trainer_fee_brutto ELSE 0 END) AS trainer_cost,
    SUM(CASE WHEN unified_status = 'attended' THEN (entry_fee_brutto - trainer_fee_brutto) ELSE 0 END) AS gross_profit
FROM v_reportline
WHERE source = 'INDIVIDUAL'
  AND occurred_at >= '2025-11-01'
  AND occurred_at < '2025-12-01'
  AND deleted_at IS NULL
GROUP BY service_type_name
ORDER BY gross_profit DESC;
```

**Index Used**: `idx_events_service_type_time` (NEW)
**Performance**: Index range scan of service type + time
**Expected Time**: <80ms for 1 month

### Performance Degradation Scenarios

| Scenario | Symptom | Mitigation |
|----------|---------|-----------|
| Query spans >1 year | Slow (>5s) | Use event_financials table |
| No time filter | Full table scan | Enforce date range in app |
| Complex subqueries in SELECT | Nested loops | Move to CTE or temp table |
| Aggregation without indexes | Sort + group filesort | Add covering index |
| High cardinality GROUP BY | Large temp table | Paginate or pre-aggregate |

---

## 6. Data Migration & Backfill Strategy

### Initial Setup (Zero Financial Data)
1. Run migrations in order (see Migration Order section)
2. No backfill needed if no historical events exist

### Backfill Historical Events (Recommended)
```bash
# Capture financials for all completed events
php artisan reports:capture-financials --from=2024-01-01 --to=2025-12-31 --dry-run
php artisan reports:capture-financials --from=2024-01-01 --to=2025-12-31
```

**Strategy**:
- Process in monthly batches to avoid memory issues
- Use `--dry-run` to validate data before commit
- Log any events with missing pricing data
- Create manual snapshots for events with NULL pricing

### Handling Missing Pricing Data
**Problem**: Historical events may lack pricing if:
- Created before pricing system implemented
- Manual events without service_type_id
- Deleted pricing rules

**Solution**:
```sql
-- Identify events with missing pricing
SELECT id, starts_at, client_id, service_type_id
FROM events
WHERE entry_fee_brutto IS NULL
  AND status = 'completed'
  AND deleted_at IS NULL;

-- Apply default pricing retroactively (business decision)
UPDATE events
SET entry_fee_brutto = 8000, trainer_fee_brutto = 6000, price_source = 'manual_backfill'
WHERE entry_fee_brutto IS NULL
  AND service_type_id = 1; -- GYOGYTORNA
```

**Warning**: Document all manual backfills in event_financials.notes or separate audit table.

---

## 7. Maintenance & Operations

### Regular Maintenance Tasks

#### Weekly
- Monitor event_financials growth rate
- Check for orphaned financial records (deleted_at IS NOT NULL)
- Validate pricing discrepancies (compare events vs event_financials)

#### Monthly
- Archive event_financials older than 2 years to cold storage
- Run OPTIMIZE TABLE on event_financials if deleted_at rows >20%
- Review slow query log for VIEW performance issues

#### Quarterly
- Audit pricing source distribution (ensure rules are applied correctly)
- Compare v_reportline vs event_financials for data consistency
- Update indexes if cardinality changes significantly

### Performance Monitoring

**Key Metrics**:
- Average query time for v_reportline: Target <200ms
- event_financials table size: ~500 bytes/row average
- Index size ratio: Indexes should be <50% of table size
- Cache hit ratio: >95% for frequently accessed date ranges

**MySQL Query Analysis**:
```sql
-- Find slow queries on v_reportline
SELECT * FROM mysql.slow_log
WHERE sql_text LIKE '%v_reportline%'
ORDER BY query_time DESC
LIMIT 10;

-- Check index usage
SHOW INDEX FROM event_financials;
ANALYZE TABLE event_financials;
```

### Troubleshooting Guide

#### Problem: VIEW queries are slow (>1s)
**Diagnosis**:
```sql
EXPLAIN SELECT * FROM v_reportline
WHERE occurred_at >= '2025-01-01' LIMIT 10;
```

**Solutions**:
1. Check for missing indexes on base tables
2. Reduce query time range
3. Use event_financials for historical data
4. Consider materialized view (requires MySQL 8.0.13+)

#### Problem: event_financials not capturing data
**Diagnosis**:
- Check application logs for event listener errors
- Verify attendance_status is being set correctly
- Ensure pricing columns are populated before capture

**Solutions**:
1. Manually trigger capture: `php artisan reports:capture-financials --event-id=123`
2. Check for missing FKs (client_id, trainer_id must exist)
3. Validate pricing rules are active and valid

#### Problem: Pricing discrepancies between VIEW and snapshot
**Diagnosis**:
```sql
-- Compare live view vs snapshot
SELECT
    ef.event_id,
    ef.entry_fee_brutto AS snapshot_fee,
    vr.entry_fee_brutto AS current_fee
FROM event_financials ef
JOIN v_reportline vr ON ef.event_id = vr.event_id
WHERE ef.entry_fee_brutto != vr.entry_fee_brutto;
```

**Root Causes**:
- Pricing rules changed after snapshot was captured (expected behavior)
- Data corruption or manual UPDATE on events table
- Bug in pricing calculation logic

**Resolution**:
- If pricing rules changed: No action (snapshot is correct)
- If data corruption: Soft delete incorrect snapshot, recapture
- If logic bug: Fix code, recapture affected period

---

## 8. Security & Compliance

### Data Privacy (GDPR)

**Personal Data in event_financials**:
- `client_email`: Pseudonymized identifier, not directly identifiable alone
- `client_id`: FK to clients table (contains PII)

**Right to Erasure**:
```sql
-- Anonymize financial records when client is deleted
UPDATE event_financials
SET client_email = CONCAT('deleted_', id, '@example.com')
WHERE client_id = ?;

-- Soft delete client record
UPDATE clients SET deleted_at = NOW() WHERE id = ?;
```

**Data Export** (Right to Portability):
```sql
-- Export client's complete financial history
SELECT
    occurred_at,
    service_type_name,
    class_name,
    entry_fee_brutto,
    trainer_fee_brutto,
    attendance_status
FROM event_financials
WHERE client_id = ?
  AND deleted_at IS NULL
ORDER BY occurred_at DESC;
```

### Audit Trail Requirements

**Who changed pricing?**
```sql
SELECT
    captured_by,
    users.name AS captured_by_name,
    captured_at,
    COUNT(*) AS records_captured
FROM event_financials
JOIN users ON captured_by = users.id
WHERE captured_at >= '2025-11-01'
GROUP BY captured_by, users.name, DATE(captured_at);
```

**Price source audit**:
```sql
-- Distribution of pricing sources
SELECT
    price_source,
    COUNT(*) AS count,
    SUM(entry_fee_brutto) AS total_revenue
FROM event_financials
WHERE occurred_at >= '2025-01-01'
  AND deleted_at IS NULL
GROUP BY price_source;
```

### Access Control

**Row-Level Security** (implement in application):
- Trainers: Can view only their own event_financials (trainer_id = current_user)
- Clients: Can view only their own records (client_id = current_user)
- Admins: Full access
- Accountants: Read-only access to event_financials

**Sensitive Columns**:
- `entry_fee_brutto`, `trainer_fee_brutto`: Restrict to admin/accounting roles
- `price_source`: Audit purposes only (admin/accounting)

---

## 9. Testing & Validation

### Pre-Deployment Checklist

- [ ] All migrations run without errors
- [ ] CHECK constraint on event_financials enforces source exclusivity
- [ ] Foreign keys cascade correctly (test with DELETE statements)
- [ ] Indexes exist on all expected columns (verify with SHOW INDEX)
- [ ] v_reportline view returns data for both INDIVIDUAL and GROUP
- [ ] Soft delete filtering works (deleted_at IS NULL)
- [ ] Pricing subquery in GROUP branch returns correct values
- [ ] No orphaned financial records (all FKs resolve)

### Test Data Setup

```sql
-- Insert test service type
INSERT INTO service_types (code, name, default_entry_fee_brutto, default_trainer_fee_brutto, is_active)
VALUES ('TEST_PT', 'Test Personal Training', 10000, 7000, 1);

-- Insert test event
INSERT INTO events (type, status, staff_id, client_id, room_id, service_type_id, starts_at, ends_at, entry_fee_brutto, trainer_fee_brutto, price_source)
VALUES ('INDIVIDUAL', 'completed', 1, 1, 1, LAST_INSERT_ID(), '2025-12-01 10:00:00', '2025-12-01 11:00:00', 10000, 7000, 'service_type_default');

-- Capture financial snapshot
INSERT INTO event_financials (source_type, event_id, client_id, client_email, trainer_id, service_type_id, entry_fee_brutto, trainer_fee_brutto, occurred_at, duration_minutes, room_id, site, attendance_status, captured_at)
SELECT 'INDIVIDUAL', id, client_id, 'test@example.com', staff_id, service_type_id, entry_fee_brutto, trainer_fee_brutto, starts_at, TIMESTAMPDIFF(MINUTE, starts_at, ends_at), room_id, (SELECT site FROM rooms WHERE id = room_id), 'attended', NOW()
FROM events WHERE id = LAST_INSERT_ID();

-- Verify in v_reportline
SELECT * FROM v_reportline WHERE event_id = LAST_INSERT_ID();
```

### Unit Test Scenarios

1. **event_financials Immutability**:
   - Insert snapshot
   - Attempt UPDATE on pricing columns
   - Assert: UPDATE fails or is logged as violation

2. **VIEW Consistency**:
   - Insert INDIVIDUAL event
   - Insert GROUP class + registration
   - Query v_reportline
   - Assert: Both records appear with correct source

3. **Pricing Calculation**:
   - Create class_pricing_defaults with valid_from/valid_until
   - Create class_occurrence within validity period
   - Query v_reportline
   - Assert: Correct pricing is applied

4. **Soft Delete Filtering**:
   - Insert event, mark deleted_at
   - Query v_reportline
   - Assert: Record does not appear

---

## 10. Future Enhancements

### Short-Term (1-3 months)
- [ ] Automated nightly capture job for completed events
- [ ] Dashboard widgets using v_reportline (revenue, attendance)
- [ ] Email reports for trainers (monthly summary from event_financials)

### Medium-Term (3-6 months)
- [ ] Materialized view for v_reportline (if performance degrades)
- [ ] Pre-aggregated monthly summaries (trainer_monthly_revenue table)
- [ ] Export functionality (CSV, Excel) directly from event_financials

### Long-Term (6-12 months)
- [ ] Time-series analytics (trend analysis, seasonality detection)
- [ ] Predictive models (revenue forecasting, demand prediction)
- [ ] Integration with external accounting systems (invoice sync)
- [ ] Multi-currency support (EUR, USD alongside HUF)

### Database Features to Monitor

**MySQL 8.0.29+ / MariaDB 10.11+**:
- Invisible indexes (test index before creating)
- Descending indexes (for DESC ORDER BY optimization)
- Multi-valued indexes (for JSON arrays)

**MariaDB 10.5+ Specific**:
- System-versioned tables (automatic temporal data)
- Instant ALTER TABLE (faster schema changes)

---

## 11. Known Limitations & Trade-offs

### Limitation 1: VIEW Performance for Large Datasets
**Issue**: v_reportline can be slow for queries spanning >2 years
**Mitigation**: Use event_financials for historical data
**Future**: Implement materialized view with hourly refresh

### Limitation 2: GROUP Pricing Subquery
**Issue**: Correlated subquery runs per row in GROUP branch
**Mitigation**: Pre-capture via event_financials when class completes
**Future**: Denormalize pricing into class_registrations (controlled)

### Limitation 3: No Database-Level Immutability Enforcement
**Issue**: MySQL lacks IMMUTABLE constraint; event_financials can technically be UPDATEd
**Mitigation**: Application-level validation, audit logging
**Future**: Implement database triggers to prevent UPDATEs (if strict compliance needed)

### Limitation 4: Denormalized Data Consistency
**Issue**: event_financials.client_email can become stale if email changes
**Mitigation**: Application event listener to update email in snapshots
**Trade-off**: Acceptable because snapshots are for reporting, not real-time identity

### Limitation 5: Soft Delete Bloat
**Issue**: deleted_at = non-NULL rows accumulate, increasing index size
**Mitigation**: Quarterly OPTIMIZE TABLE + archival to cold storage
**Future**: Automated cleanup job to HARD DELETE after 5 years

---

## 12. Glossary

| Term | Definition |
|------|------------|
| **Immutable Snapshot** | Database record that cannot be updated after creation; only soft-deleted if incorrect |
| **3NF (Third Normal Form)** | Database normalization level where all columns depend only on primary key (no transitive dependencies) |
| **Controlled Denormalization** | Intentional violation of 3NF for performance, with documented justification |
| **Covering Index** | Index that includes all columns needed for a query, avoiding table lookups |
| **Correlated Subquery** | Subquery that references columns from outer query, executed per row |
| **Soft Delete** | Marking record as deleted via deleted_at timestamp instead of physical deletion |
| **Polymorphic Relation** | FK that references multiple tables via discriminator column (source_type) |
| **Audit Trail** | Complete history of changes including who, when, and what was changed |

---

## 13. Contact & Support

**Database Architect**: Database Architect Agent for FunctionalFit
**Documentation Version**: 1.0
**Last Updated**: 2025-12-12

**For Questions**:
- Schema design: Review this document + ER diagram
- Performance issues: Check Query Patterns section + MySQL slow log
- Data integrity: Validate with Testing & Validation checklist
- Compliance: See Security & Compliance section
