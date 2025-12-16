# Settlement & Pricing Module - Quick Reference Guide

## Table Overview

| Table | Purpose | Key Fields | Soft Delete |
|-------|---------|-----------|-------------|
| `class_pricing_defaults` | Default pricing per class template | entry_fee_brutto, trainer_fee_brutto | Yes |
| `client_class_pricing` | Client-specific price overrides | client_id, template/occurrence_id | Yes |
| `settlements` | Settlement header for trainers | trainer_id, period, totals, status | Yes |
| `settlement_items` | Settlement line items | occurrence_id, client_id, prices | No |

---

## Price Resolution Algorithm

```php
function resolvePrice($client_id, $occurrence_id, $template_id, $at_time) {
    // Priority 1: Client + Occurrence specific
    $price = ClientClassPricing::where('client_id', $client_id)
        ->where('class_occurrence_id', $occurrence_id)
        ->where('valid_from', '<=', $at_time)
        ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $at_time))
        ->whereNull('deleted_at')
        ->first();

    if ($price) return $price;

    // Priority 2: Client + Template general
    $price = ClientClassPricing::where('client_id', $client_id)
        ->where('class_template_id', $template_id)
        ->where('valid_from', '<=', $at_time)
        ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $at_time))
        ->whereNull('deleted_at')
        ->first();

    if ($price) return $price;

    // Priority 3: Template default
    $price = ClassPricingDefault::where('class_template_id', $template_id)
        ->where('is_active', true)
        ->where('valid_from', '<=', $at_time)
        ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $at_time))
        ->whereNull('deleted_at')
        ->first();

    if ($price) return $price;

    // No match
    throw new MissingPricingException("No pricing found for client {$client_id} and occurrence {$occurrence_id}");
}
```

---

## Settlement Generation Logic

```php
function generateSettlement($trainer_id, $period_start, $period_end) {
    DB::beginTransaction();

    // 1. Fetch all class occurrences in period for this trainer
    $occurrences = ClassOccurrence::where('trainer_id', $trainer_id)
        ->whereBetween('starts_at', [$period_start, $period_end])
        ->get();

    // 2. For each occurrence, fetch attended/no_show registrations
    $items = [];
    $total_trainer_fee = 0;
    $total_entry_fee = 0;

    foreach ($occurrences as $occurrence) {
        $registrations = $occurrence->registrations()
            ->whereIn('status', ['attended', 'no_show', 'cancelled'])
            ->get();

        foreach ($registrations as $registration) {
            // Skip based on business rules
            if (!shouldIncludeInSettlement($registration)) {
                continue;
            }

            // Resolve price
            $price = resolvePrice(
                $registration->client_id,
                $occurrence->id,
                $occurrence->template_id,
                $occurrence->starts_at
            );

            $items[] = [
                'class_occurrence_id' => $occurrence->id,
                'client_id' => $registration->client_id,
                'registration_id' => $registration->id,
                'entry_fee_brutto' => $price->entry_fee_brutto,
                'trainer_fee_brutto' => $price->trainer_fee_brutto,
                'status' => $registration->status,
            ];

            $total_trainer_fee += $price->trainer_fee_brutto;
            $total_entry_fee += $price->entry_fee_brutto;
        }
    }

    // 3. Create settlement header
    $settlement = Settlement::create([
        'trainer_id' => $trainer_id,
        'period_start' => $period_start,
        'period_end' => $period_end,
        'total_trainer_fee' => $total_trainer_fee,
        'total_entry_fee' => $total_entry_fee,
        'status' => 'draft',
        'created_by' => auth()->id(),
    ]);

    // 4. Create settlement items
    foreach ($items as $item) {
        $item['settlement_id'] = $settlement->id;
        SettlementItem::create($item);
    }

    DB::commit();

    return $settlement;
}

function shouldIncludeInSettlement($registration) {
    $settings = Settings::get();

    switch ($registration->status) {
        case 'attended':
            return true;

        case 'no_show':
            // Check global settings
            return $settings->include_trainer_fee_on_no_show
                || $settings->include_entry_fee_on_no_show;

        case 'cancelled':
            // Check if late cancellation (< 24h)
            $hours_before = $registration->cancelled_at->diffInHours(
                $registration->occurrence->starts_at
            );

            if ($hours_before < 24) {
                // Late cancellation - apply settings
                return $settings->include_trainer_fee_on_late_cancel
                    || $settings->include_entry_fee_on_late_cancel;
            }

            return false; // Early cancellation, no fees

        default:
            return false;
    }
}
```

---

## Common Queries

### Find Active Default Price for Class Template

```sql
SELECT * FROM class_pricing_defaults
WHERE class_template_id = ?
  AND is_active = 1
  AND valid_from <= NOW()
  AND (valid_until IS NULL OR valid_until >= NOW())
  AND deleted_at IS NULL
LIMIT 1;
```

### Find Client-Specific Price for Occurrence

```sql
SELECT * FROM client_class_pricing
WHERE client_id = ?
  AND class_occurrence_id = ?
  AND valid_from <= NOW()
  AND (valid_until IS NULL OR valid_until >= NOW())
  AND deleted_at IS NULL
LIMIT 1;
```

### Get All Settlements for Trainer in Year

```sql
SELECT * FROM settlements
WHERE trainer_id = ?
  AND period_start >= '2025-01-01'
  AND period_end <= '2025-12-31'
  AND deleted_at IS NULL
ORDER BY period_start DESC;
```

### Settlement Details with Line Items

```sql
SELECT
    s.id AS settlement_id,
    s.period_start,
    s.period_end,
    s.status,
    si.id AS item_id,
    co.starts_at AS class_time,
    c.full_name AS client_name,
    si.entry_fee_brutto,
    si.trainer_fee_brutto,
    si.status AS attendance_status
FROM settlements s
JOIN settlement_items si ON s.id = si.settlement_id
JOIN class_occurrences co ON si.class_occurrence_id = co.id
JOIN clients c ON si.client_id = c.id
WHERE s.id = ?
ORDER BY co.starts_at, c.full_name;
```

### Clients Without Pricing (Error Detection)

```sql
-- Find registrations that would fail settlement
SELECT
    cr.id AS registration_id,
    c.full_name AS client_name,
    co.starts_at AS class_time,
    ct.title AS class_title
FROM class_registrations cr
JOIN clients c ON cr.client_id = c.id
JOIN class_occurrences co ON cr.occurrence_id = co.id
JOIN class_templates ct ON co.template_id = ct.id
WHERE cr.status IN ('attended', 'no_show')
  AND cr.deleted_at IS NULL
  AND NOT EXISTS (
      -- Check client-specific occurrence price
      SELECT 1 FROM client_class_pricing ccp
      WHERE ccp.client_id = cr.client_id
        AND ccp.class_occurrence_id = co.id
        AND ccp.valid_from <= co.starts_at
        AND (ccp.valid_until IS NULL OR ccp.valid_until >= co.starts_at)
        AND ccp.deleted_at IS NULL
  )
  AND NOT EXISTS (
      -- Check client-specific template price
      SELECT 1 FROM client_class_pricing ccp
      WHERE ccp.client_id = cr.client_id
        AND ccp.class_template_id = co.template_id
        AND ccp.valid_from <= co.starts_at
        AND (ccp.valid_until IS NULL OR ccp.valid_until >= co.starts_at)
        AND ccp.deleted_at IS NULL
  )
  AND NOT EXISTS (
      -- Check default price
      SELECT 1 FROM class_pricing_defaults cpd
      WHERE cpd.class_template_id = co.template_id
        AND cpd.is_active = 1
        AND cpd.valid_from <= co.starts_at
        AND (cpd.valid_until IS NULL OR cpd.valid_until >= co.starts_at)
        AND cpd.deleted_at IS NULL
  );
```

---

## API Endpoints (Suggested)

### Pricing Management

```
GET    /api/v1/admin/pricing/class-defaults
POST   /api/v1/admin/pricing/class-defaults
PUT    /api/v1/admin/pricing/class-defaults/{id}
DELETE /api/v1/admin/pricing/class-defaults/{id}

GET    /api/v1/admin/pricing/clients/{clientId}
POST   /api/v1/admin/pricing/client-class
PUT    /api/v1/admin/pricing/client-class/{id}
DELETE /api/v1/admin/pricing/client-class/{id}
```

### Settlement Operations

```
GET    /api/v1/admin/settlements/preview?trainerId=&from=&to=
POST   /api/v1/admin/settlements/generate
GET    /api/v1/admin/settlements
GET    /api/v1/admin/settlements/{id}
PATCH  /api/v1/admin/settlements/{id}/status
DELETE /api/v1/admin/settlements/{id}
GET    /api/v1/admin/settlements/{id}/export
```

---

## Validation Rules

### ClassPricingDefault

```php
[
    'class_template_id' => 'required|exists:class_templates,id',
    'entry_fee_brutto' => 'required|integer|min:0|max:1000000',
    'trainer_fee_brutto' => 'required|integer|min:0|max:1000000',
    'valid_from' => 'required|date',
    'valid_until' => 'nullable|date|after:valid_from',
    'is_active' => 'boolean',
]
```

### ClientClassPricing

```php
[
    'client_id' => 'required|exists:clients,id',
    'class_template_id' => 'required_without:class_occurrence_id|nullable|exists:class_templates,id',
    'class_occurrence_id' => 'required_without:class_template_id|nullable|exists:class_occurrences,id',
    'entry_fee_brutto' => 'required|integer|min:0|max:1000000',
    'trainer_fee_brutto' => 'required|integer|min:0|max:1000000',
    'valid_from' => 'required|date',
    'valid_until' => 'nullable|date|after:valid_from',
    'source' => 'in:manual,import,promotion',
]
```

### Settlement Generation

```php
[
    'trainer_id' => 'required|exists:users,id',
    'period_start' => 'required|date',
    'period_end' => 'required|date|after:period_start',
]
```

---

## Business Logic Settings

These should be stored in the `settings` table:

```php
[
    'include_trainer_fee_on_no_show' => false,
    'include_entry_fee_on_no_show' => true,
    'include_trainer_fee_on_late_cancel' => false,
    'include_entry_fee_on_late_cancel' => true,
    'late_cancellation_hours' => 24,
]
```

---

## Testing Scenarios

### Price Resolution

1. Client with occurrence-specific price → should use occurrence price
2. Client with template-specific price → should use template price
3. Client without custom price → should use default price
4. Client without any price → should throw exception
5. Expired validity period → should skip and check next priority
6. Multiple valid prices → should use most recent valid_from

### Settlement Generation

1. Generate settlement with all attended → full fees
2. Generate with no_show → check settings
3. Generate with late cancellation → apply fees per settings
4. Generate with early cancellation → no fees
5. Overlapping settlements → should prevent duplicate items
6. Missing pricing → should list in errors

### Status Workflow

1. Create as draft → allow edits
2. Finalize → lock from edits
3. Mark as paid → final state
4. Cannot revert from paid → enforce in policy

---

## Performance Optimization Tips

1. Use eager loading for relationships:
   ```php
   Settlement::with(['items.client', 'items.occurrence.template'])->find($id);
   ```

2. Index usage verification:
   ```sql
   EXPLAIN SELECT * FROM client_class_pricing
   WHERE client_id = ? AND class_occurrence_id = ?;
   ```

3. Batch insert settlement items:
   ```php
   SettlementItem::insert($items); // Instead of create() in loop
   ```

4. Cache default prices:
   ```php
   Cache::remember("pricing_default_{$template_id}", 3600, fn() =>
       ClassPricingDefault::where('class_template_id', $template_id)
           ->where('is_active', true)
           ->first()
   );
   ```

---

**Generated:** 2025-12-08
**For:** FunctionalFit Calendar Settlement Module
