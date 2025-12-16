# Reports Module - Entity Relationship Diagram

## Mermaid ER Diagram

```mermaid
erDiagram
    %% ========================================
    %% CORE ENTITIES (Existing)
    %% ========================================

    users ||--o{ staff_profiles : "has"
    users ||--o{ clients : "has"

    staff_profiles ||--o{ events : "instructs"
    staff_profiles ||--o{ class_occurrences : "instructs"
    staff_profiles ||--o{ event_financials : "earns_from"

    clients ||--o{ events : "attends"
    clients ||--o{ event_additional_clients : "attends_as_guest"
    clients ||--o{ class_registrations : "registers_for"
    clients ||--o{ event_financials : "pays_for"

    rooms ||--o{ events : "hosts"
    rooms ||--o{ class_occurrences : "hosts"
    rooms ||--o{ event_financials : "hosts_financially"

    service_types ||--o{ events : "categorizes"
    service_types ||--o{ event_financials : "categorizes"
    service_types ||--o{ client_price_codes : "has_pricing"

    class_templates ||--o{ class_occurrences : "instantiates"
    class_templates ||--o{ class_pricing_defaults : "has_pricing"
    class_templates ||--o{ event_financials : "categorizes"

    %% ========================================
    %% INDIVIDUAL EVENTS (1:1 Sessions)
    %% ========================================

    events {
        bigint id PK
        enum type "INDIVIDUAL|BLOCK"
        enum status "scheduled|completed|cancelled|no_show"
        bigint staff_id FK "Trainer"
        bigint client_id FK "Main client (nullable)"
        bigint room_id FK
        bigint service_type_id FK "Service category"
        timestamp starts_at
        timestamp ends_at
        int entry_fee_brutto "HUF"
        int trainer_fee_brutto "HUF"
        string currency
        string price_source
        enum attendance_status "attended|no_show"
        timestamp checked_in_at
    }

    events ||--o{ event_additional_clients : "has_guests"

    event_additional_clients {
        bigint id PK
        bigint event_id FK
        bigint client_id FK
        int quantity "Number of guests"
        int entry_fee_brutto "HUF per guest"
        int trainer_fee_brutto "HUF per guest"
        string currency
        string price_source
        string attendance_status "attended|no_show"
        timestamp checked_in_at
    }

    %% ========================================
    %% GROUP CLASSES
    %% ========================================

    class_occurrences {
        bigint id PK
        bigint template_id FK "nullable"
        bigint trainer_id FK
        bigint room_id FK
        timestamp starts_at
        timestamp ends_at
        int capacity "Max participants"
        enum status "scheduled|completed|cancelled"
    }

    class_occurrences ||--o{ class_registrations : "has_registrations"

    class_registrations {
        bigint id PK
        bigint occurrence_id FK
        bigint client_id FK
        enum status "booked|waitlist|cancelled|no_show|attended"
        timestamp booked_at
        timestamp cancelled_at
        timestamp checked_in_at
        int credits_used
    }

    class_pricing_defaults {
        bigint id PK
        bigint class_template_id FK
        int entry_fee_brutto "HUF"
        int trainer_fee_brutto "HUF"
        string currency
        timestamp valid_from
        timestamp valid_until "nullable"
        boolean is_active
    }

    %% ========================================
    %% PRICING CONFIGURATION
    %% ========================================

    client_price_codes {
        bigint id PK
        bigint client_id FK
        string client_email "Indexed"
        bigint service_type_id FK
        string price_code "nullable"
        int entry_fee_brutto "HUF"
        int trainer_fee_brutto "HUF"
        string currency
        timestamp valid_from
        timestamp valid_until "nullable"
        boolean is_active
    }

    service_types {
        bigint id PK
        string code UK "GYOGYTORNA, PT, MASSZAZS"
        string name "Display name"
        text description
        int default_entry_fee_brutto "HUF"
        int default_trainer_fee_brutto "HUF"
        boolean is_active
    }

    %% ========================================
    %% NEW: REPORTS MODULE
    %% ========================================

    event_financials {
        bigint id PK
        enum source_type "INDIVIDUAL|GROUP"
        bigint event_id FK "nullable"
        bigint class_occurrence_id FK "nullable"
        bigint class_registration_id FK "nullable"
        bigint client_id FK
        string client_email "Indexed"
        bigint trainer_id FK
        bigint service_type_id FK "nullable"
        bigint class_template_id FK "nullable"
        int entry_fee_brutto "HUF - IMMUTABLE"
        int trainer_fee_brutto "HUF - IMMUTABLE"
        string currency
        string price_source "Traceability"
        timestamp occurred_at "Indexed"
        int duration_minutes
        bigint room_id FK "nullable"
        enum site "SASAD|TB|ÚJBUDA"
        enum attendance_status "attended|no_show|cancelled|late_cancel"
        timestamp captured_at "Snapshot time"
        bigint captured_by FK "User who triggered"
    }

    events ||--o{ event_financials : "captures_pricing"
    class_occurrences ||--o{ event_financials : "captures_pricing"
    class_registrations ||--o{ event_financials : "captures_pricing"

    %% ========================================
    %% REPORTLINE VIEW (Conceptual)
    %% ========================================

    v_reportline {
        string source "INDIVIDUAL|GROUP"
        string source_id "Composite ID"
        bigint event_id "nullable"
        bigint class_occurrence_id "nullable"
        bigint client_id
        string client_email
        string client_name
        bigint trainer_id
        string trainer_name
        bigint service_type_id "nullable"
        string service_type_name "nullable"
        bigint class_template_id "nullable"
        string class_name "nullable"
        bigint room_id
        string room_name
        enum site
        timestamp occurred_at
        int duration_minutes
        decimal hours "Calculated"
        int entry_fee_brutto
        int trainer_fee_brutto
        string currency
        string price_source
        string status
        string attendance_status
        string unified_status "Normalized"
    }

    %% Virtual relationships (view aggregates data)
    events ||--o{ v_reportline : "contributes_to"
    event_additional_clients ||--o{ v_reportline : "contributes_to"
    class_occurrences ||--o{ v_reportline : "contributes_to"
    class_registrations ||--o{ v_reportline : "contributes_to"
```

## Diagram Legend

### Cardinality
- `||--o{` : One-to-Many (1:N)
- `||--||` : One-to-One (1:1)
- `}o--o{` : Many-to-Many (N:M)

### Entity Types
- **Blue boxes**: Core domain entities (users, clients, staff)
- **Green boxes**: Event entities (events, class_occurrences)
- **Yellow boxes**: Pricing configuration (service_types, client_price_codes, class_pricing_defaults)
- **Red box**: NEW - event_financials (immutable snapshot)
- **Purple box**: NEW - v_reportline (unified view)

### Key Relationships

#### Individual Events (1:1)
```
staff_profiles ──> events <── clients
                     │
                     └──> event_additional_clients <── clients
```

#### Group Classes
```
staff_profiles ──> class_occurrences <── class_templates
                          │
                          └──> class_registrations <── clients
```

#### Financial Snapshots
```
events ──────────┐
                 │
event_additional │
clients ─────────┼──> event_financials <── staff_profiles
                 │                    │
class_           │                    └── clients
occurrences ─────┘
```

### Critical FK Chains

1. **Individual Event Pricing Flow**:
   ```
   events.service_type_id -> service_types
   events.client_id -> clients -> client_price_codes -> service_type_id
   events -> event_financials (snapshot)
   ```

2. **Group Class Pricing Flow**:
   ```
   class_occurrences.template_id -> class_templates -> class_pricing_defaults
   class_registrations -> event_financials (snapshot)
   ```

3. **Reporting Query Path**:
   ```
   v_reportline (UNION of events + class_occurrences)
   OR
   event_financials (pre-captured immutable data)
   ```

## Indexes Summary

### event_financials (NEW)
- `idx_financials_trainer_time` (trainer_id, occurred_at, deleted_at)
- `idx_financials_client_time` (client_id, occurred_at, deleted_at)
- `idx_financials_room_time` (room_id, occurred_at, deleted_at)
- `idx_financials_service_type_time` (service_type_id, occurred_at, deleted_at)
- `idx_financials_site_time` (site, occurred_at, deleted_at)
- `idx_financials_attendance` (attendance_status, occurred_at, deleted_at)
- `idx_financials_source_type` (source_type, occurred_at, deleted_at)
- `idx_financials_client_email` (client_email, occurred_at)
- `idx_financials_captured_at` (captured_at)

### events (ENHANCED)
- `idx_events_service_type_time` (service_type_id, starts_at, ends_at, deleted_at) **NEW**

### class_registrations (ENHANCED)
- `idx_class_registrations_client_time` (client_id, booked_at, deleted_at) **NEW**

### event_additional_clients (ENHANCED)
- `idx_eac_attendance` (attendance_status) **NEW**

## Data Flow: Pricing Capture

### Trigger: Event Attendance Marked

```
┌─────────────────────────────────────────────────────────────┐
│ User marks attendance: attended / no_show                    │
└────────────┬────────────────────────────────────────────────┘
             │
             ├─── INDIVIDUAL EVENT ───┐
             │                        │
             │    1. Read events.entry_fee_brutto,
             │       trainer_fee_brutto, service_type_id
             │    2. If event_additional_clients exist,
             │       read their pricing too
             │    3. Create event_financials record(s)
             │       with source_type='INDIVIDUAL'
             │
             └─── GROUP CLASS ───────┐
                                     │
                  1. Read class_occurrences.template_id
                  2. Query class_pricing_defaults for
                     valid_from <= occurred_at
                  3. For each class_registration:
                     Create event_financials record
                     with source_type='GROUP'
```

### Data Immutability Guarantee

Once `event_financials.captured_at` is set:
- **NEVER UPDATE** entry_fee_brutto or trainer_fee_brutto
- **NEVER RECALCULATE** based on current pricing rules
- Only allow soft delete (deleted_at) if correction needed
- Create NEW record if data was incorrect

## Query Patterns

### Pattern 1: Real-Time Reporting (v_reportline)
**Use Case**: Dashboard, recent events, live data
**Query**: `SELECT * FROM v_reportline WHERE occurred_at >= ?`
**Performance**: Depends on base table indexes (good for recent data)

### Pattern 2: Historical Reporting (event_financials)
**Use Case**: Month-end reports, audits, historical analysis
**Query**: `SELECT * FROM event_financials WHERE occurred_at BETWEEN ? AND ?`
**Performance**: Excellent (pre-aggregated, indexed)

### Pattern 3: Hybrid Approach
**Use Case**: Current month + historical comparison
```sql
-- Current month: live data from view
SELECT * FROM v_reportline
WHERE occurred_at >= '2025-12-01' AND occurred_at < '2025-12-31'

-- Previous months: snapshot data
SELECT * FROM event_financials
WHERE occurred_at >= '2025-01-01' AND occurred_at < '2025-12-01'
```

## Migration Order

**CRITICAL: Run migrations in this exact order:**

1. `2025_12_12_100001_create_event_financials_table.php`
2. `2025_12_12_100002_add_report_indexes_to_existing_tables.php`
3. `2025_12_12_100003_create_reportline_view.php`

**Dependencies:**
- event_financials depends on: events, class_occurrences, class_registrations, clients, staff_profiles, service_types, class_templates, rooms, users
- v_reportline depends on: all above tables + event_additional_clients, class_pricing_defaults

## Rollback Strategy

```bash
# Rollback in reverse order
php artisan migrate:rollback --step=3
```

**Order:**
1. Drop v_reportline view (no data loss)
2. Drop indexes from existing tables (no data loss)
3. Drop event_financials table (DATA LOSS - snapshot data deleted)

**WARNING**: Rolling back event_financials will delete all captured financial snapshots. Ensure backups exist before rollback.
