# FunctionalFit Backend - Quick Start Guide

## What's Been Implemented

✅ **14 Eloquent Models** - All database entities with relationships, casts, scopes
✅ **5 Authorization Policies** - RBAC for Event, ClassOccurrence, Room, Client, User
✅ **3 Business Services** - ConflictDetection, PassCredit, Notification
✅ **9+ Form Requests** - Validation for Auth, Events, Classes, Admin operations
✅ **3 Custom Exceptions** - HTTP-semantic exceptions (409, 423, 451)
✅ **API Response Helper** - Standardized JSON response format
✅ **1 Example Controller** - AuthController with register/login/logout/me
✅ **1 Middleware** - Role-based access control

## Project Structure

```
backend/app/
├── Models/                    # 14 Eloquent models
├── Services/                  # Business logic layer
├── Policies/                  # Authorization rules
├── Exceptions/                # Custom exceptions
├── Http/
│   ├── Controllers/Api/       # API controllers (1 example)
│   ├── Middleware/            # Custom middleware
│   ├── Requests/              # Form validation (organized by feature)
│   └── Responses/             # API response helper
```

## Key Files to Review

1. **backend/IMPLEMENTATION_SUMMARY.md** - Detailed documentation
2. **backend/app/Models/Event.php** - Core event model with scopes
3. **backend/app/Services/ConflictDetectionService.php** - Collision prevention
4. **backend/app/Policies/EventPolicy.php** - Same-day-only rule enforcement
5. **backend/app/Http/Requests/Events/UpdateEventRequest.php** - Same-day validation
6. **backend/app/Http/Controllers/Api/AuthController.php** - Example controller pattern

## Quick Code Examples

### Using ConflictDetectionService

```php
use App\Services\ConflictDetectionService;

$service = app(ConflictDetectionService::class);

// Check for room conflicts (throws ConflictException if found)
$service->detectRoomConflict(
    $room,
    Carbon::parse('2025-11-15 10:00:00'),
    Carbon::parse('2025-11-15 11:00:00'),
    $excludeEventId // Optional: ignore this event ID
);

// Use with locking for writes
$service->checkConflictsWithLock($room, $staff, $startsAt, $endsAt);
```

### Using PassCreditService

```php
use App\Services\PassCreditService;

$service = app(PassCreditService::class);

// Check if client has credits
if ($service->hasAvailableCredits($client)) {
    // Deduct credit (transactional, with locking)
    $pass = $service->deductCredit($client, 'Class booking');
}

// Refund credit
$service->refundCredit($client, 'Cancellation', $passId);
```

### Using Policies in Controllers

```php
// In controller method
$this->authorize('update', $event);

// Or check manually
if ($request->user()->can('update', $event)) {
    // Allow action
}

// Same-day move check
if ($request->user()->can('sameDayMove', [$event, $newStartsAt])) {
    // Allow move
}
```

### Using ApiResponse Helper

```php
use App\Http\Responses\ApiResponse;

// Success response
return ApiResponse::success($data, 'Operation successful');

// Created resource
return ApiResponse::created($event, 'Event created');

// Conflict error
return ApiResponse::conflict('Room is already booked', [
    'conflicting_event_id' => 123,
    'time_range' => '10:00-11:00'
]);

// Validation error
return ApiResponse::unprocessable('Invalid input', $validator->errors());
```

### Using FormRequests

```php
// In controller method signature
public function store(StoreEventRequest $request)
{
    // $request is already validated and authorized
    $validated = $request->validated();

    // Create event...
}
```

## HTTP Status Code Guide

- **200 OK** - Successful GET
- **201 Created** - Successful POST creating resource
- **204 No Content** - Successful DELETE/PUT with no body
- **401 Unauthorized** - Not authenticated
- **403 Forbidden** - Policy denial (use `ApiResponse::forbidden()`)
- **409 Conflict** - Room/staff double-booking (use `ConflictException`)
- **422 Unprocessable** - Validation failure (use `ApiResponse::unprocessable()`)
- **423 Locked** - Time window passed (use `LockedResourceException`)

## Critical Business Rules

### Same-Day-Only Move Rule

Staff can only move events within the same day. Admin can override.

**Enforcement Locations:**
1. `UpdateEventRequest::withValidator()` - Validates new start date
2. `EventPolicy::sameDayMove()` - Authorization check
3. `EventPolicy::forceUpdate()` - Admin bypass

```php
// In UpdateEventRequest
if (!$newStartsAt->isSameDay($originalStartsAt) && !$user->isAdmin()) {
    $validator->errors()->add('starts_at', 'Staff can only move within same day');
}
```

### Conflict Detection

All room and staff bookings must check for conflicts before saving.

```php
use App\Services\ConflictDetectionService;

$service = app(ConflictDetectionService::class);

// This will throw ConflictException with details if conflict exists
$service->checkConflictsWithLock($room, $staff, $startsAt, $endsAt, $excludeEventId);
```

### Pass Credit Deduction

Credits are deducted transactionally at check-in (configurable).

```php
use App\Services\PassCreditService;

$service = app(PassCreditService::class);

try {
    $pass = $service->deductCredit($client, 'Class attendance');
    // Credit deducted, pass updated
} catch (PolicyViolationException $e) {
    // No credits available
    return ApiResponse::error($e->getMessage(), null, 451);
}
```

### Cancellation Rules

- **Client:** ≥24h free, <24h credit deduction (configurable)
- **Staff:** ≥12h self-service, <12h admin approval

```php
// In CancelBookingRequest
$hoursUntilClass = $occurrence->starts_at->diffInHours(now(), false);

if ($hoursUntilClass < 24 && !$user->isAdmin()) {
    // Late cancellation - may deduct credit
    $this->merge(['late_cancellation' => true]);
}
```

## Model Relationships Quick Reference

```php
// User relationships
$user->staffProfile       // HasOne StaffProfile
$user->client            // HasOne Client
$user->staffEvents()     // HasManyThrough Event
$user->notifications()   // HasMany Notification

// Event relationships
$event->staff           // BelongsTo StaffProfile
$event->client          // BelongsTo Client
$event->room            // BelongsTo Room
$event->eventChanges()  // HasMany EventChange

// ClassOccurrence relationships
$occurrence->template      // BelongsTo ClassTemplate
$occurrence->trainer       // BelongsTo StaffProfile
$occurrence->room          // BelongsTo Room
$occurrence->registrations() // HasMany ClassRegistration

// Client relationships
$client->user             // BelongsTo User (nullable)
$client->passes()         // HasMany Pass
$client->classRegistrations() // HasMany ClassRegistration
```

## Common Query Patterns

```php
// Get upcoming events for a room
Event::forRoom($roomId)->upcoming()->get();

// Get active passes for client
Pass::where('client_id', $clientId)->active()->get();

// Get class occurrences in date range
ClassOccurrence::withinDateRange('2025-11-15', '2025-11-22')
    ->with(['trainer', 'room', 'registrations'])
    ->get();

// Get available capacity for a class
$occurrence->availableCapacity; // Computed attribute

// Check if user is staff
$user->isStaff(); // Helper method
```

## Testing Checklist

Before implementing controllers, test existing code:

1. **Conflict Detection**
   - Create overlapping events in DB
   - Call `detectRoomConflict()` - should throw exception
   - Verify conflict details in exception

2. **Pass Credit System**
   - Create client with active pass
   - Call `deductCredit()` - verify credits_left decremented
   - Call with no credits - should throw PolicyViolationException

3. **Policies**
   - Create staff user and event
   - Test `$user->can('update', $event)` - should be true for own event
   - Test `$user->can('sameDayMove', [$event, $tomorrow])` - should be false

4. **Same-Day Validation**
   - Create UpdateEventRequest with cross-day move
   - Validate - should fail for staff, pass for admin

## Next Implementation Steps

1. **Create remaining controllers** (see IMPLEMENTATION_SUMMARY.md)
2. **Define API routes** in `routes/api.php` with v1 prefix
3. **Register policies** in `AuthServiceProvider`
4. **Register middleware** in `bootstrap/app.php`
5. **Configure CORS** for frontend origin
6. **Configure Sanctum** for API tokens
7. **Implement queue jobs** for notifications
8. **Write Pest tests** for services and policies
9. **Generate OpenAPI docs** from routes
10. **Implement health check** endpoint

## Common Issues & Solutions

**Issue:** "Class 'App\Models\X' not found"
**Solution:** Run `composer dump-autoload`

**Issue:** Policy not working
**Solution:** Register in AuthServiceProvider::boot()

**Issue:** Validation not triggering
**Solution:** Check authorize() returns true in FormRequest

**Issue:** Conflict detection not working
**Solution:** Ensure composite indexes exist (check migrations)

**Issue:** Pass credit race condition
**Solution:** Always use within DB::transaction()

## Development Commands

```bash
# Run migrations
php artisan migrate:fresh --seed

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Generate IDE helper (if installed)
php artisan ide-helper:models

# Run tests (when implemented)
php artisan test

# Queue worker (when jobs implemented)
php artisan queue:work --tries=3
```

## Configuration Checklist

In `.env`:
```env
APP_TIMEZONE=UTC
DB_CONNECTION=mysql
REDIS_CLIENT=phpredis
QUEUE_CONNECTION=redis
SANCTUM_STATEFUL_DOMAINS=localhost:3000
```

## Security Reminders

✅ All files use `declare(strict_types=1);`
✅ All models use `$fillable` (never `$guarded = []`)
✅ Passwords hashed via `password` cast in User model
✅ Policies registered for all models
✅ Validation in FormRequests, never in controllers
⚠️ Register EnsureRole middleware before using in routes
⚠️ Add rate limiting to API routes
⚠️ Implement webhook signature verification
⚠️ Set up PII encryption for phone/notes fields

## Architecture Principles

1. **Thin Controllers** - Delegate to services
2. **Service Layer** - Business logic lives here
3. **Policy Authorization** - Use policies, not if-statements
4. **FormRequest Validation** - Never validate in controllers
5. **Consistent Responses** - Always use ApiResponse helper
6. **Transaction Safety** - Wrap writes in DB::transaction()
7. **Pessimistic Locking** - Use lockForUpdate() for critical sections
8. **Queue Everything** - Notifications, exports, external APIs

## File Naming Conventions

- **Models:** Singular, PascalCase (User, Event, ClassOccurrence)
- **Controllers:** PascalCase + Controller suffix (EventController)
- **Requests:** PascalCase + Request suffix (StoreEventRequest)
- **Policies:** PascalCase + Policy suffix (EventPolicy)
- **Services:** PascalCase + Service suffix (ConflictDetectionService)
- **Exceptions:** PascalCase + Exception suffix (ConflictException)

## Resources

- Laravel 11 Docs: https://laravel.com/docs/11.x
- Sanctum: https://laravel.com/docs/11.x/sanctum
- Pest Testing: https://pestphp.com
- Project Spec: `docs/spec.md`
- Implementation Details: `backend/IMPLEMENTATION_SUMMARY.md`

## Getting Help

1. Read `IMPLEMENTATION_SUMMARY.md` for detailed information
2. Check model relationships in Models/ directory
3. Review example AuthController for patterns
4. Examine Service classes for business logic examples
5. Test with Tinker: `php artisan tinker`

---

**Status:** Foundation complete. Ready for controller implementation.

**Next Developer:** Start with implementing `StaffEventController` following the AuthController pattern. Use ConflictDetectionService for conflict checks and EventPolicy for authorization.
