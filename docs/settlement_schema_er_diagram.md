# Settlement & Pricing Module - Entity Relationship Diagram

## Mermaid ER Diagram

```mermaid
erDiagram
    %% Core existing entities
    users ||--o{ class_pricing_defaults : creates
    users ||--o{ settlements : "is_trainer"
    class_templates ||--o{ class_pricing_defaults : "has_default_pricing"
    class_templates ||--o{ class_occurrences : generates
    class_templates ||--o{ client_class_pricing : "has_custom_pricing"
    class_occurrences ||--o{ class_registrations : "has_registrations"
    class_occurrences ||--o{ client_class_pricing : "has_custom_pricing"
    class_occurrences ||--o{ settlement_items : "included_in"
    clients ||--o{ class_registrations : registers
    clients ||--o{ client_class_pricing : "has_custom_pricing"
    clients ||--o{ settlement_items : "attended"

    %% Settlement relationships
    settlements ||--o{ settlement_items : contains
    class_registrations ||--o{ settlement_items : "tracked_in"

    %% Table definitions
    class_pricing_defaults {
        bigint id PK
        bigint class_template_id FK
        int entry_fee_brutto "HUF"
        int trainer_fee_brutto "HUF"
        string currency "Default HUF"
        timestamp valid_from
        timestamp valid_until "Nullable"
        boolean is_active
        bigint created_by FK
        timestamps created_at_updated_at
        timestamp deleted_at "Soft delete"
    }

    client_class_pricing {
        bigint id PK
        bigint client_id FK
        bigint class_template_id FK "Nullable"
        bigint class_occurrence_id FK "Nullable"
        int entry_fee_brutto "HUF"
        int trainer_fee_brutto "HUF"
        string currency "Default HUF"
        timestamp valid_from
        timestamp valid_until "Nullable"
        enum source "manual|import|promotion"
        bigint created_by FK
        timestamps created_at_updated_at
        timestamp deleted_at "Soft delete"
    }

    settlements {
        bigint id PK
        bigint trainer_id FK
        date period_start
        date period_end
        int total_trainer_fee "HUF"
        int total_entry_fee "HUF"
        enum status "draft|finalized|paid"
        text notes "Nullable"
        bigint created_by FK
        timestamps created_at_updated_at
        timestamp deleted_at "Soft delete"
    }

    settlement_items {
        bigint id PK
        bigint settlement_id FK
        bigint class_occurrence_id FK
        bigint client_id FK
        bigint registration_id FK
        int entry_fee_brutto "HUF"
        int trainer_fee_brutto "HUF"
        string currency "Default HUF"
        enum status "attended|no_show|cancelled"
        timestamps created_at_updated_at
    }

    class_templates {
        bigint id PK
        string title
        text description
    }

    class_occurrences {
        bigint id PK
        bigint template_id FK
        timestamp starts_at
        timestamp ends_at
    }

    class_registrations {
        bigint id PK
        bigint occurrence_id FK
        bigint client_id FK
        enum status
        timestamp booked_at
    }

    clients {
        bigint id PK
        string full_name
    }

    users {
        bigint id PK
        string name
    }
```

## Price Resolution Flow Diagram

```mermaid
flowchart TD
    Start([Settlement Generation<br/>for Registration]) --> CheckOccurrence{Check:<br/>client_class_pricing<br/>client + occurrence}

    CheckOccurrence -->|Found & Valid| UseOccurrence[Use Occurrence-Specific Price<br/>Priority 1]
    CheckOccurrence -->|Not Found| CheckTemplate{Check:<br/>client_class_pricing<br/>client + template}

    CheckTemplate -->|Found & Valid| UseTemplate[Use Template-Specific Price<br/>Priority 2]
    CheckTemplate -->|Not Found| CheckDefault{Check:<br/>class_pricing_defaults<br/>template}

    CheckDefault -->|Found & Valid| UseDefault[Use Default Price<br/>Priority 3]
    CheckDefault -->|Not Found| Error[Raise Error:<br/>MissingPricingException]

    UseOccurrence --> CreateItem[Create settlement_item<br/>with resolved prices]
    UseTemplate --> CreateItem
    UseDefault --> CreateItem
    Error --> AdminAction[Admin must define<br/>pricing before settlement]

    CreateItem --> End([Item Added to Settlement])
```

## Settlement Generation Workflow

```mermaid
stateDiagram-v2
    [*] --> Preview: Admin selects trainer + period

    Preview --> Editing: Generate Settlement
    note right of Preview
        Preview shows:
        - All class occurrences
        - Registrations (attended/no_show)
        - Resolved prices
        - Totals
    end note

    Editing: Status = draft
    note right of Editing
        Admin can:
        - Review line items
        - Add manual adjustments
        - Add notes
    end note

    Editing --> Finalized: Approve
    Editing --> Preview: Discard

    Finalized: Status = finalized
    note right of Finalized
        Locked for editing
        Ready for payment
    end note

    Finalized --> Paid: Mark as Paid

    Paid: Status = paid
    note right of Paid
        Payment completed
        Final state
    end note

    Paid --> [*]
```

## Key Indexes Visualization

```mermaid
graph LR
    subgraph class_pricing_defaults
        CPD1[idx_template_validity<br/>template_id, valid_from, valid_until]
        CPD2[idx_active_prices<br/>template_id, is_active, deleted_at]
    end

    subgraph client_class_pricing
        CCP1[idx_client_occurrence<br/>client_id, occurrence_id, deleted_at]
        CCP2[idx_client_template<br/>client_id, template_id, valid_from]
    end

    subgraph settlements
        S1[idx_trainer_period<br/>trainer_id, period_start, period_end]
        S2[idx_status_period<br/>status, period_start]
    end

    subgraph settlement_items
        SI1[idx_settlement_status<br/>settlement_id, status]
        SI2[idx_occurrence_status<br/>occurrence_id, status]
    end
```

## Data Flow: From Booking to Settlement

```mermaid
sequenceDiagram
    participant Client
    participant ClassOccurrence
    participant Registration
    participant Pricing
    participant Settlement
    participant SettlementItem

    Client->>ClassOccurrence: Books class
    ClassOccurrence->>Registration: Creates registration
    Registration->>Registration: Status = booked

    Note over Registration: Time passes...

    Registration->>Registration: Check-in
    Registration->>Registration: Status = attended

    Note over Settlement: End of period

    Settlement->>Registration: Query attended registrations
    Registration->>Pricing: Resolve price for client+occurrence

    alt Client-specific occurrence price
        Pricing-->>Settlement: Return occurrence-specific price
    else Client-specific template price
        Pricing-->>Settlement: Return template-specific price
    else Default price
        Pricing-->>Settlement: Return default price
    else No price found
        Pricing-->>Settlement: Error: Missing pricing
    end

    Settlement->>SettlementItem: Create line item
    SettlementItem->>SettlementItem: Store price snapshot
    Settlement->>Settlement: Aggregate totals
    Settlement->>Settlement: Status = draft
```

## Database Constraints Summary

| Table | Foreign Keys | Cascading Rules | Soft Delete |
|-------|-------------|-----------------|-------------|
| class_pricing_defaults | class_template_id → class_templates | ON DELETE RESTRICT | Yes |
| client_class_pricing | client_id → clients<br/>class_template_id → class_templates<br/>class_occurrence_id → class_occurrences | ON DELETE CASCADE (all) | Yes |
| settlements | trainer_id → users | ON DELETE RESTRICT | Yes |
| settlement_items | settlement_id → settlements<br/>class_occurrence_id → class_occurrences<br/>client_id → clients<br/>registration_id → class_registrations | ON DELETE CASCADE (settlement_id)<br/>ON DELETE RESTRICT (others) | No |

## Cardinality Legend

- `||--o{` : One-to-Many
- One class_template has many class_pricing_defaults (price history)
- One client has many client_class_pricing records (multiple custom prices)
- One settlement has many settlement_items (line items)
- One class_occurrence can be in many settlement_items (multiple clients)
- One class_registration links to zero or one settlement_item (if settled)

---

**Generated:** 2025-12-08
**Diagrams Format:** Mermaid (compatible with GitHub, GitLab, VS Code)
