# Calendar Change Logger - Security Review Report
**Date:** 2025-12-14
**Reviewer:** Auth & Security Agent
**Project:** FunctionalFit Calendar
**Scope:** Calendar Change Logger Audit System

---

## Executive Summary

The Calendar Change Logger audit system has been reviewed against the security checklist. The implementation demonstrates **strong foundational security** with proper RBAC enforcement, comprehensive input validation, and audit log integrity. However, **3 CRITICAL and 4 MEDIUM severity issues** require immediate attention to achieve GDPR compliance and prevent potential security vulnerabilities.

**Overall Security Posture:** 7/10 (Good, with critical gaps)

**Recommendation:** Address critical issues before production deployment.

---

## Critical Findings (MUST FIX)

### 1. CRITICAL: Missing Authorization Policy for Detail Endpoint
**File:** `backend/app/Http/Controllers/Api/Admin/CalendarChangeController.php:87-92`
**Risk:** Unauthorized information disclosure, privilege escalation
**CVSS Score:** 7.5 (High)

**Issue:**
```php
public function show(int $id): JsonResponse
{
    $change = CalendarChangeLog::findOrFail($id);
    return response()->json(new CalendarChangeLogDetailResource($change));
}
```

The `show()` method lacks authorization checks. While protected by `role:admin` middleware at the route level, there is **no verification that the requesting user can view this specific log entry**. This violates the principle of defense in depth.

**Attack Vector:**
- Admin A at Site SASAD could view calendar changes from Site TB
- If multi-tenancy is added later, this becomes a data breach vector
- Staff users who gain temporary admin access could view logs outside their scope

**Remediation:**
Create `CalendarChangeLogPolicy` with `view()` method:

```php
<?php
namespace App\Policies;

use App\Models\CalendarChangeLog;
use App\Models\User;

class CalendarChangeLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, CalendarChangeLog $log): bool
    {
        // Admins can view all logs
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can only view their own changes
        if ($user->isStaff()) {
            return $log->actor_user_id === $user->id;
        }

        return false;
    }
}
```

Update controller:
```php
public function show(int $id): JsonResponse
{
    $change = CalendarChangeLog::findOrFail($id);
    $this->authorize('view', $change);
    return response()->json(new CalendarChangeLogDetailResource($change));
}
```

---

### 2. CRITICAL: PII Exposure in Audit Logs Without GDPR Justification
**Files:**
- `backend/app/Services/CalendarChangeLogger.php:174`
- `backend/app/Http/Resources/CalendarChangeLogDetailResource.php:44-48`

**Risk:** GDPR Article 5 violation (data minimization), unauthorized PII disclosure
**CVSS Score:** 6.5 (Medium-High)

**Issue:**
The audit logs store and expose `client_email` in JSON snapshots without documented legal basis:

```php
// CalendarChangeLogger.php:174
'client_email' => $event->client?->user?->email,
```

```php
// CalendarChangeLogDetailResource.php:44-48
'before' => $this->before_json,  // Contains client_email
'after' => $this->after_json,     // Contains client_email
```

**GDPR Analysis:**
- **Article 5(1)(c) - Data Minimization:** Storing client email in audit logs may not be necessary. Client ID + name may suffice for audit purposes.
- **Article 32 - Security of Processing:** PII in JSON fields is stored in plaintext without column-level encryption.
- **Right to Erasure (Article 17):** If a client requests data deletion, their email will remain in audit logs indefinitely.

**Attack Vector:**
- Database backup compromise exposes client emails in audit logs
- Overly permissive staff access to logs (if staff view is enabled) exposes client PII
- JSON export/logging leaks client emails to third parties

**Remediation Options:**

**Option A: Remove PII (Recommended for GDPR)**
```php
// CalendarChangeLogger.php - remove client_email
protected function createSnapshot(Event $event): array
{
    return [
        // ... other fields
        'client_id' => $event->client_id,
        'client_name' => $event->client?->user?->name,  // Keep name for audit context
        // REMOVE: 'client_email' => $event->client?->user?->email,
    ];
}
```

**Option B: Pseudonymize (If email needed for audit)**
```php
'client_email_hash' => $event->client?->user?->email
    ? hash('sha256', $event->client->user->email)
    : null,
```

**Option C: Encrypt (If full email required - requires encryption implementation)**
Store encrypted email with key rotation support.

**Recommendation:** Use Option A. Client ID + name provides sufficient audit context without GDPR risk.

---

### 3. CRITICAL: IP Address Storage Without GDPR Consent/Legal Basis
**Files:**
- `backend/app/Services/CalendarChangeLogger.php:219-226`
- `backend/database/migrations/2025_12_14_182208_create_calendar_change_log_table.php:59`

**Risk:** GDPR Article 6 violation (lawfulness of processing), potential fines
**CVSS Score:** 6.0 (Medium)

**Issue:**
```php
protected function getIpAddress(): ?string
{
    if (!request()) {
        return null;
    }
    return request()->ip();
}
```

IP addresses are **personal data under GDPR** (Article 4(1)). The system stores IP addresses without:
1. Documented legal basis (Article 6)
2. Privacy notice to users
3. Retention/deletion policy
4. Consent mechanism (if relying on Article 6(1)(a))

**GDPR Legal Bases (choose one):**
- **Article 6(1)(f) - Legitimate Interest:** Security monitoring and fraud prevention
  - Requires Legitimate Interest Assessment (LIA)
  - Must demonstrate "compelling legitimate grounds"
  - Users must be informed via privacy policy
- **Article 6(1)(c) - Legal Obligation:** If required by industry regulation
- **Article 6(1)(a) - Consent:** Not practical for audit logs (consent must be granular)

**Remediation:**

**Step 1: Conduct Legitimate Interest Assessment**
Document:
1. Purpose: Detect unauthorized access, investigate security incidents
2. Necessity: IP addresses are essential for geo-location analysis and fraud detection
3. Balancing Test: Security benefits outweigh privacy intrusion (IP is low-sensitivity PII)

**Step 2: Update Privacy Policy**
Add clause:
> "We collect IP addresses in audit logs for security monitoring and fraud prevention under legitimate interest (GDPR Article 6(1)(f)). IP addresses are retained for 24 months and automatically purged."

**Step 3: Implement Retention Policy**
Add to migration:
```sql
-- Auto-delete logs older than 24 months
CREATE EVENT purge_old_calendar_logs
ON SCHEDULE EVERY 1 MONTH
DO
  DELETE FROM calendar_change_log WHERE created_at < NOW() - INTERVAL 24 MONTH;
```

**Step 4: Pseudonymize IP Addresses (Optional but recommended)**
```php
protected function getIpAddress(): ?string
{
    if (!request()) {
        return null;
    }
    $ip = request()->ip();
    // Hash last octet for IPv4 privacy
    return $this->pseudonymizeIp($ip);
}

protected function pseudonymizeIp(string $ip): string
{
    $parts = explode('.', $ip);
    if (count($parts) === 4) {
        $parts[3] = 'xxx';  // e.g., 192.168.1.xxx
        return implode('.', $parts);
    }
    return $ip;  // IPv6 or invalid - handle separately
}
```

---

## Medium Severity Findings (SHOULD FIX)

### 4. MEDIUM: SQL Injection Risk in Dynamic ORDER BY
**File:** `backend/app/Http/Controllers/Api/Admin/CalendarChangeController.php:62-64`
**Risk:** SQL injection via ORDER BY clause
**CVSS Score:** 5.3 (Medium)

**Issue:**
```php
$sortField = $validated['sort'];
$sortOrder = $validated['order'];
$query->orderBy($sortField, $sortOrder);
```

While `$validated['sort']` is constrained by `Rule::in([...])` in the FormRequest, directly passing user input to `orderBy()` is risky if:
1. Validation rules are modified without updating the controller
2. A future developer bypasses validation

**Attack Vector:**
If validation is weakened:
```
GET /admin/calendar-changes?sort=id);DROP TABLE users;--&order=asc
```

Laravel's query builder does escape column names, but defense in depth requires explicit whitelisting.

**Remediation:**
```php
// CalendarChangeController.php
protected const SORTABLE_FIELDS = [
    'changed_at',
    'action',
    'actor_name',
    'site',
    'room_name',
    'starts_at',
];

public function index(CalendarChangeFilterRequest $request): JsonResponse
{
    $validated = $request->validated();

    $sortField = in_array($validated['sort'], self::SORTABLE_FIELDS, true)
        ? $validated['sort']
        : 'changed_at';  // Safe default

    $sortOrder = $validated['order'] === 'asc' ? 'asc' : 'desc';  // Binary choice

    $query->orderBy($sortField, $sortOrder);
    // ...
}
```

---

### 5. MEDIUM: User Agent String Not Sanitized (XSS Risk)
**File:** `backend/app/Services/CalendarChangeLogger.php:237-241`
**Risk:** Stored XSS if user agent displayed in admin UI
**CVSS Score:** 4.7 (Medium)

**Issue:**
```php
protected function getUserAgent(): ?string
{
    if (!request()) {
        return null;
    }
    $userAgent = request()->userAgent();
    // Truncate to fit database column (255 chars)
    return $userAgent ? substr($userAgent, 0, 255) : null;
}
```

User agent strings can contain malicious payloads:
```
User-Agent: <script>alert(document.cookie)</script>
```

If the admin UI displays this without escaping, it creates an XSS vector.

**Remediation:**
```php
protected function getUserAgent(): ?string
{
    if (!request()) {
        return null;
    }
    $userAgent = request()->userAgent();
    if (!$userAgent) {
        return null;
    }

    // Strip HTML/JS tags
    $sanitized = strip_tags($userAgent);

    // Remove control characters
    $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $sanitized);

    return substr($sanitized, 0, 255);
}
```

**Frontend Protection:**
Ensure React components escape user agent when displaying:
```tsx
<div>{/* User agent */}
  <span className="text-muted">{escapeHtml(log.user_agent)}</span>
</div>
```

---

### 6. MEDIUM: Missing Rate Limiting on Admin Endpoints
**File:** `backend/routes/api.php:338-339`
**Risk:** Resource exhaustion, brute force reconnaissance
**CVSS Score:** 4.3 (Medium)

**Issue:**
```php
Route::get('/calendar-changes', [CalendarChangeController::class, 'index']);
Route::get('/calendar-changes/{id}', [CalendarChangeController::class, 'show']);
```

No rate limiting middleware applied. Admin endpoints can be abused:
- **Reconnaissance:** Enumerate all log IDs to discover system activity patterns
- **DoS:** Paginate through millions of records to exhaust database/CPU
- **Data Exfiltration:** Rapidly download all audit logs before detection

**Remediation:**
```php
// routes/api.php
Route::prefix('admin')->middleware(['role:admin', 'throttle:100,1'])->group(function () {
    Route::get('/calendar-changes', [CalendarChangeController::class, 'index']);
    Route::get('/calendar-changes/{id}', [CalendarChangeController::class, 'show'])
        ->middleware('throttle:200,1');  // Higher limit for detail view
    // ...
});
```

Adjust rate limits based on:
- Average admin usage patterns (100 requests/minute is conservative)
- Database query performance (log tables can grow large)

---

### 7. MEDIUM: Audit Logs Not Truly Immutable
**Files:**
- `backend/app/Models/CalendarChangeLog.php:56-75`
- `backend/database/migrations/2025_12_14_182208_create_calendar_change_log_table.php:28`

**Risk:** Log tampering, audit trail compromise
**CVSS Score:** 4.0 (Medium)

**Issue:**
The model declares `$timestamps = false` and fills `created_at`, but:
1. **No database constraint prevents updates:** Table lacks `ON UPDATE` trigger to block modifications
2. **Mass assignment protection allows `created_at` modification:**
   ```php
   protected $fillable = [
       'created_at',  // Allows backdating logs
       // ...
   ];
   ```
3. **No `updated_at` column to detect tampering**

**Attack Vector:**
Malicious admin with database access:
```sql
-- Backdate a log to hide activity
UPDATE calendar_change_log SET created_at = '2024-01-01 00:00:00' WHERE id = 123;

-- Modify actor to frame another user
UPDATE calendar_change_log SET actor_user_id = 999 WHERE id = 456;
```

**Remediation:**

**Step 1: Make logs truly immutable at database level**
```sql
-- Add trigger to prevent updates
DELIMITER $$
CREATE TRIGGER prevent_calendar_log_updates
BEFORE UPDATE ON calendar_change_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Calendar change logs are immutable and cannot be updated';
END$$
DELIMITER ;
```

**Step 2: Remove `created_at` from fillable**
```php
// CalendarChangeLog.php
protected $fillable = [
    'changed_at',
    'action',
    'entity_type',
    'entity_id',
    'actor_user_id',
    'actor_name',
    'actor_role',
    'site',
    'room_id',
    'room_name',
    'starts_at',
    'ends_at',
    'before_json',
    'after_json',
    'changed_fields',
    'ip_address',
    'user_agent',
    // REMOVE: 'created_at',
];

// Override boot to auto-set created_at
protected static function boot()
{
    parent::boot();
    static::creating(function ($model) {
        $model->created_at = now();
    });
}
```

**Step 3: Add checksum for integrity verification (optional but recommended)**
```php
// Migration: Add checksum column
$table->string('checksum', 64)->nullable()->comment('SHA256 hash for integrity verification');

// CalendarChangeLog.php: Calculate checksum on create
protected static function boot()
{
    parent::boot();
    static::creating(function ($model) {
        $model->created_at = now();
        $model->checksum = hash('sha256', json_encode([
            $model->changed_at,
            $model->action,
            $model->entity_id,
            $model->actor_user_id,
            $model->before_json,
            $model->after_json,
        ]));
    });
}

// Verify integrity method
public function verifyIntegrity(): bool
{
    $calculatedChecksum = hash('sha256', json_encode([
        $this->changed_at,
        $this->action,
        $this->entity_id,
        $this->actor_user_id,
        $this->before_json,
        $this->after_json,
    ]));

    return hash_equals($this->checksum, $calculatedChecksum);
}
```

---

## Informational / Low Severity

### 8. INFO: Actor User ID Can Be Spoofed via Service Layer
**File:** `backend/app/Services/CalendarChangeLogger.php:30,95,133`
**Risk:** Low (requires application-level compromise)
**CVSS Score:** 2.5 (Low)

**Issue:**
```php
public function logCreated(Event $event, ?User $actor = null): void
{
    $actor = $actor ?? $this->getActorFromRequest();
    CalendarChangeLog::create([
        'actor_user_id' => $actor?->id ?? $event->staff_id,
        // ...
    ]);
}
```

The `$actor` parameter allows callers to override the authenticated user. While useful for system-initiated actions, this could be abused if:
1. A controller passes a fake user object
2. Background jobs impersonate users

**Current Mitigation:**
- `getActorFromRequest()` uses `Auth::user()` which is secure
- Only trusted application code calls `CalendarChangeLogger`

**Recommendation:**
Add code comment to warn future developers:
```php
/**
 * Log an event creation.
 *
 * @param Event $event The event being created
 * @param User|null $actor The user performing the action.
 *                         WARNING: Only pass this parameter for system-initiated actions
 *                         (e.g., migrations, cron jobs). For user requests, leave null
 *                         to auto-detect from Auth::user().
 * @return void
 */
public function logCreated(Event $event, ?User $actor = null): void
```

---

### 9. INFO: No CSRF Protection (Acceptable for API)
**Status:** Not applicable
**Risk:** None (API design pattern)

**Analysis:**
The calendar change endpoints are read-only GET requests within a stateless API using token authentication (Laravel Sanctum/Passport). CSRF protection is not required for:
1. Read-only operations (no state mutation)
2. Token-based auth (CSRF targets cookie-based sessions)

**Verification:**
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    // CSRF not needed - stateless API
});
```

**Recommendation:** No action required. Document that CSRF is handled at the auth middleware level.

---

## Positive Security Observations

### Strong Points
1. **Comprehensive RBAC:** Proper use of `role:admin` and `role:staff,admin` middleware
2. **Defense in Depth:** Staff endpoint (`staffIndex`) enforces actor filtering in controller logic
3. **Input Validation Excellence:** `CalendarChangeFilterRequest` uses Laravel's validation with:
   - Whitelist-based validation (`Rule::in()`)
   - Foreign key existence checks (`exists:users,id`)
   - Date range validation (`after_or_equal`)
   - Pagination limits (`max:100`)
4. **Error Handling:** Graceful fallback with try-catch in logger service
5. **Denormalization for Audit Integrity:** Storing `actor_name`, `room_name` prevents issues if referenced records are deleted
6. **Performance:** Comprehensive database indexes for common query patterns

---

## Security Testing Recommendations

### Unit Tests (PHPUnit/Pest)
```php
// tests/Feature/CalendarChangeLogTest.php

test('admin can view all calendar changes', function () {
    $admin = User::factory()->admin()->create();
    $changes = CalendarChangeLog::factory(5)->create();

    actingAs($admin)
        ->get('/api/v1/admin/calendar-changes')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

test('staff can only view own calendar changes', function () {
    $staff = User::factory()->staff()->create();
    $ownChange = CalendarChangeLog::factory()->create(['actor_user_id' => $staff->id]);
    $otherChange = CalendarChangeLog::factory()->create(['actor_user_id' => 999]);

    actingAs($staff)
        ->get('/api/v1/staff/calendar-changes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownChange->id);
});

test('client cannot access calendar changes', function () {
    $client = User::factory()->client()->create();

    actingAs($client)
        ->get('/api/v1/admin/calendar-changes')
        ->assertForbidden();
});

test('unauthorized users get 401', function () {
    get('/api/v1/admin/calendar-changes')
        ->assertUnauthorized();
});

test('audit log is immutable', function () {
    $log = CalendarChangeLog::factory()->create([
        'action' => 'EVENT_CREATED',
        'actor_name' => 'Original Name',
    ]);

    expect(fn() => $log->update(['actor_name' => 'Tampered Name']))
        ->toThrow(QueryException::class);
});

test('sensitive data is not exposed in list view', function () {
    $admin = User::factory()->admin()->create();
    $log = CalendarChangeLog::factory()->create();

    $response = actingAs($admin)
        ->get('/api/v1/admin/calendar-changes')
        ->assertOk()
        ->json('data.0');

    expect($response)->not->toHaveKey('before');
    expect($response)->not->toHaveKey('after');
    expect($response)->not->toHaveKey('ip_address');
});
```

### Penetration Testing Scenarios
1. **Privilege Escalation:** Attempt to access admin endpoints as staff user
2. **IDOR:** Try to view log ID belonging to different site/tenant
3. **SQL Injection:** Fuzz sort/order parameters with SQL payloads
4. **XSS:** Inject scripts in user agent, verify sanitization
5. **Rate Limit Bypass:** Test endpoint flooding with distributed requests

---

## Compliance Checklist

### GDPR Compliance
- [ ] **Article 5(1)(a) - Lawfulness:** Document legal basis for IP address processing
- [ ] **Article 5(1)(c) - Data Minimization:** Remove client_email or justify necessity
- [ ] **Article 5(1)(e) - Storage Limitation:** Implement 24-month retention policy
- [ ] **Article 13 - Privacy Notice:** Update privacy policy with audit log disclosure
- [ ] **Article 15 - Right of Access:** Provide mechanism for users to request their audit logs
- [ ] **Article 17 - Right to Erasure:** Define policy for handling deletion requests (audit logs may be exempt under Article 17(3)(e) - legal claims)
- [ ] **Article 25 - Data Protection by Design:** Pseudonymize IP addresses, remove unnecessary PII
- [ ] **Article 32 - Security:** Implement log immutability, encryption for sensitive fields

### SOC 2 / ISO 27001 Alignment
- [ ] **Access Control:** RBAC enforced at middleware + controller levels
- [ ] **Audit Logging:** Comprehensive event capture (who, what, when, where)
- [ ] **Log Integrity:** Immutability mechanisms (database triggers + checksums)
- [ ] **Retention Policy:** 24-month retention with automated purging
- [ ] **Incident Response:** Logs available for forensic analysis

---

## Implementation Priority

### Phase 1: Critical Fixes (Before Production)
1. Create `CalendarChangeLogPolicy` with `view()` authorization (Finding #1)
2. Remove `client_email` from snapshots or document GDPR justification (Finding #2)
3. Conduct Legitimate Interest Assessment for IP address storage (Finding #3)
4. Update privacy policy with audit log disclosure (Finding #3)

### Phase 2: Medium Priority (Within 1 Sprint)
5. Add SQL injection defense for ORDER BY clause (Finding #4)
6. Sanitize user agent strings (Finding #5)
7. Implement rate limiting on admin endpoints (Finding #6)
8. Add database trigger for log immutability (Finding #7)

### Phase 3: Hardening (Within 2 Sprints)
9. Add checksum-based integrity verification (Finding #7)
10. Implement automated log retention/purging (Finding #3)
11. Create comprehensive security test suite
12. Conduct penetration testing

---

## Code Fixes

### Fix #1: CalendarChangeLogPolicy
**File:** `backend/app/Policies/CalendarChangeLogPolicy.php` (new file)

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CalendarChangeLog;
use App\Models\User;

class CalendarChangeLogPolicy
{
    /**
     * Determine if the user can view any calendar change logs.
     */
    public function viewAny(User $user): bool
    {
        // Admins can view all logs
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can view their own logs (handled by controller scoping)
        if ($user->isStaff()) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can view a specific calendar change log.
     */
    public function view(User $user, CalendarChangeLog $log): bool
    {
        // Admins can view all logs
        if ($user->isAdmin()) {
            return true;
        }

        // Staff can only view their own changes
        if ($user->isStaff()) {
            return $log->actor_user_id === $user->id;
        }

        return false;
    }
}
```

**Register Policy:**
`backend/app/Providers/AuthServiceProvider.php`
```php
protected $policies = [
    CalendarChangeLog::class => CalendarChangeLogPolicy::class,
    // ... existing policies
];
```

**Update Controller:**
`backend/app/Http/Controllers/Api/Admin/CalendarChangeController.php`
```php
public function show(int $id): JsonResponse
{
    $change = CalendarChangeLog::findOrFail($id);

    // ADDED: Authorization check
    $this->authorize('view', $change);

    return response()->json(new CalendarChangeLogDetailResource($change));
}
```

---

### Fix #2: Remove PII from Snapshots
**File:** `backend/app/Services/CalendarChangeLogger.php`

```php
protected function createSnapshot(Event $event): array
{
    return [
        'id' => $event->id,
        'title' => $event->type ?? null,
        'type' => $event->type,
        'starts_at' => $event->starts_at?->toIso8601String(),
        'ends_at' => $event->ends_at?->toIso8601String(),
        'site' => $this->getSiteName($event),
        'room_id' => $event->room_id,
        'room_name' => $event->room?->name,
        'trainer_id' => $event->staff_id,
        'trainer_name' => $event->staff?->user?->name,
        'client_id' => $event->client_id,
        'client_name' => $event->client?->user?->name,  // ADDED: Name for context
        // REMOVED: 'client_email' => $event->client?->user?->email,
        'service_type_id' => $event->service_type_id,
        'service_type_code' => $event->serviceType?->code,
        'status' => $event->status,
        'attendance_status' => $event->attendance_status,
        'notes' => $event->notes,
        'entry_fee_brutto' => $event->entry_fee_brutto,
        'trainer_fee_brutto' => $event->trainer_fee_brutto,
        'currency' => $event->currency,
    ];
}
```

---

### Fix #3: Sanitize User Agent
**File:** `backend/app/Services/CalendarChangeLogger.php`

```php
protected function getUserAgent(): ?string
{
    if (!request()) {
        return null;
    }

    $userAgent = request()->userAgent();

    if (!$userAgent) {
        return null;
    }

    // Strip HTML/JS tags to prevent stored XSS
    $sanitized = strip_tags($userAgent);

    // Remove control characters (0x00-0x1F, 0x7F)
    $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $sanitized);

    // Truncate to fit database column (255 chars)
    return substr($sanitized, 0, 255);
}
```

---

### Fix #4: Harden ORDER BY
**File:** `backend/app/Http/Controllers/Api/Admin/CalendarChangeController.php`

```php
class CalendarChangeController extends Controller
{
    /**
     * Whitelist of fields that can be used for sorting.
     * Prevents SQL injection if validation is bypassed.
     */
    protected const SORTABLE_FIELDS = [
        'changed_at',
        'action',
        'actor_name',
        'site',
        'room_name',
        'starts_at',
    ];

    public function index(CalendarChangeFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = CalendarChangeLog::query();

        // ... filters ...

        // Apply sorting with whitelist enforcement
        $sortField = in_array($validated['sort'], self::SORTABLE_FIELDS, true)
            ? $validated['sort']
            : 'changed_at';

        $sortOrder = $validated['order'] === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortField, $sortOrder);

        // ... pagination ...
    }
}
```

---

### Fix #5: Database Immutability Trigger
**File:** `backend/database/migrations/2025_12_14_create_immutable_log_trigger.php` (new migration)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Prevent updates to audit logs (immutability)
        DB::unprepared('
            CREATE TRIGGER prevent_calendar_log_updates
            BEFORE UPDATE ON calendar_change_log
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE \'45000\'
                SET MESSAGE_TEXT = \'Calendar change logs are immutable and cannot be updated\';
            END
        ');

        // Prevent deletes unless explicitly allowed by admin
        DB::unprepared('
            CREATE TRIGGER prevent_calendar_log_deletes
            BEFORE DELETE ON calendar_change_log
            FOR EACH ROW
            BEGIN
                -- Allow deletes only during retention purge (checked by stored procedure)
                IF @allow_log_deletion IS NULL OR @allow_log_deletion = 0 THEN
                    SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT = \'Calendar change logs cannot be deleted manually. Use retention policy.\';
                END IF;
            END
        ');
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_calendar_log_updates');
        DB::unprepared('DROP TRIGGER IF EXISTS prevent_calendar_log_deletes');
    }
};
```

---

### Fix #6: Rate Limiting
**File:** `backend/routes/api.php`

```php
// Admin routes with rate limiting
Route::prefix('admin')->middleware(['role:admin', 'throttle:100,1'])->group(function () {
    // Calendar changes log with stricter limit (large result sets)
    Route::get('/calendar-changes', [CalendarChangeController::class, 'index'])
        ->middleware('throttle:60,1');  // 60 requests per minute

    Route::get('/calendar-changes/{id}', [CalendarChangeController::class, 'show'])
        ->middleware('throttle:100,1'); // Higher limit for single record

    // ... other admin routes
});

// Staff routes with rate limiting
Route::prefix('staff')->middleware(['role:staff,admin', 'throttle:100,1'])->group(function () {
    Route::get('/calendar-changes', [CalendarChangeController::class, 'staffIndex'])
        ->middleware('throttle:60,1');

    // ... other staff routes
});
```

---

## Conclusion

The Calendar Change Logger audit system demonstrates **strong security fundamentals** but requires **immediate remediation of 3 critical GDPR/authorization issues** before production deployment. Once the fixes in Phase 1 are implemented, the system will provide a robust, compliant audit trail for calendar modifications.

**Next Steps:**
1. Review and approve this security report
2. Implement Phase 1 critical fixes (estimated 4-6 hours)
3. Conduct Legitimate Interest Assessment for IP address storage (1-2 hours)
4. Update privacy policy (1 hour)
5. Deploy Phase 2 hardening measures (estimated 8 hours)
6. Schedule penetration testing

**Risk if not addressed:**
- GDPR fines up to €20 million or 4% of annual revenue
- Data breach if unauthorized users access PII in audit logs
- Loss of audit trail integrity if logs can be tampered

---

**Report prepared by:** Auth & Security Agent
**Reviewed files:**
- `backend/app/Http/Controllers/Api/Admin/CalendarChangeController.php`
- `backend/app/Http/Requests/CalendarChangeFilterRequest.php`
- `backend/app/Services/CalendarChangeLogger.php`
- `backend/app/Models/CalendarChangeLog.php`
- `backend/app/Http/Resources/CalendarChangeLogResource.php`
- `backend/app/Http/Resources/CalendarChangeLogDetailResource.php`
- `backend/routes/api.php`
- `backend/database/migrations/2025_12_14_182208_create_calendar_change_log_table.php`

**Compliance frameworks referenced:**
- GDPR (EU Regulation 2016/679)
- OWASP Top 10 (2021)
- CWE Top 25 Most Dangerous Software Weaknesses
- Laravel Security Best Practices
