# Google Calendar Push-Only Synchronization

## Overview

This document describes the Google Calendar push-only synchronization system for the FunctionalFit Calendar backend. The system automatically syncs events from the internal database (Single Source of Truth) to Google Calendar.

**Core Principle:** Internal database → Google Calendar (one-way sync)

## Architecture

### Components

1. **GoogleCalendarService** (`app/Services/GoogleCalendarService.php`)
   - Core service handling Google Calendar API interactions
   - Implements idempotent create/update/delete operations
   - Uses exponential backoff retry logic with jitter
   - Stores internal event IDs in `extendedProperties` for tracking

2. **Queue Jobs**
   - `SyncEventToGoogleCalendar` - Creates or updates events in Google Calendar
   - `DeleteEventFromGoogleCalendar` - Removes events from Google Calendar

3. **EventObserver** (`app/Observers/EventObserver.php`)
   - Automatically triggers sync jobs on event lifecycle changes
   - Listens for: created, updated, deleted, restored, forceDeleted

4. **Event Model Extension**
   - `google_event_id` column stores Google Calendar event ID
   - Used for idempotency and tracking

### Flow Diagram

```
Internal DB Event
    ↓
Event Observer (created/updated/deleted)
    ↓
Queue Job Dispatch (gcal-sync queue)
    ↓
GoogleCalendarService
    ↓
Google Calendar API
    ↓
Update google_event_id in DB
```

## Setup Instructions

### Step 1: Install Dependencies

The Google API Client library should already be installed. If not:

```bash
cd backend
composer require google/apiclient
```

### Step 2: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **Google Calendar API**:
   - Navigate to "APIs & Services" → "Library"
   - Search for "Google Calendar API"
   - Click "Enable"

### Step 3: Choose Authentication Method

You have two authentication options:

#### Option A: Service Account (Recommended)

**Best for:** Backend-to-backend automation, no user interaction required

**Steps:**

1. In Google Cloud Console, go to "IAM & Admin" → "Service Accounts"
2. Click "Create Service Account"
3. Enter details:
   - Name: `functionalfit-calendar-sync`
   - Description: `Service account for FunctionalFit Calendar sync`
4. Click "Create and Continue"
5. Grant role: **Project → Editor** (or create custom role with Calendar permissions)
6. Click "Continue" → "Done"
7. Click on the created service account
8. Go to "Keys" tab → "Add Key" → "Create new key"
9. Choose "JSON" format
10. Save the downloaded JSON file

**Configure:**

```bash
# Copy the service account JSON to your backend storage
mkdir -p backend/storage/app
cp /path/to/downloaded-service-account.json backend/storage/app/google-service-account.json

# Set permissions
chmod 600 backend/storage/app/google-service-account.json
```

**Update `.env`:**

```env
GOOGLE_CALENDAR_SYNC_ENABLED=true
GOOGLE_SERVICE_ACCOUNT_PATH=storage/app/google-service-account.json
GOOGLE_CALENDAR_APP_NAME="FunctionalFit Calendar"
```

**Important:** Each staff member's calendar must be **shared with the service account email** (found in the JSON file as `client_email`).

To share calendars:
1. Open Google Calendar
2. Find the calendar to share
3. Click "Settings and sharing"
4. Under "Share with specific people", add the service account email
5. Grant "Make changes to events" permission

#### Option B: OAuth2 (User-Delegated Access)

**Best for:** When you need user consent or accessing user-specific calendars

**Steps:**

1. In Google Cloud Console, go to "APIs & Services" → "Credentials"
2. Click "Create Credentials" → "OAuth client ID"
3. Application type: "Web application"
4. Name: `FunctionalFit Calendar`
5. Authorized redirect URIs:
   - `http://localhost:8080/api/auth/google/callback` (development)
   - `https://yourdomain.com/api/auth/google/callback` (production)
6. Click "Create"
7. Copy the Client ID and Client Secret

**Update `.env`:**

```env
GOOGLE_CALENDAR_SYNC_ENABLED=true
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8080/api/auth/google/callback
GOOGLE_CALENDAR_APP_NAME="FunctionalFit Calendar"
```

**Note:** OAuth2 requires implementing an authorization flow to obtain and refresh tokens. Service account is simpler for automated sync.

### Step 4: Configure Queue Worker

The sync jobs run on the `gcal-sync` queue. Ensure your queue worker is running:

**Development:**

```bash
cd backend
php artisan queue:work --queue=gcal-sync,default --tries=5
```

**Production (Docker):**

The queue worker service should already be configured in `docker-compose.yml`. Verify it includes:

```yaml
command: php artisan queue:work --queue=gcal-sync,default --tries=5 --timeout=300
```

### Step 5: Test the Sync

1. Ensure sync is enabled:
   ```bash
   # Check .env
   GOOGLE_CALENDAR_SYNC_ENABLED=true
   ```

2. Create a test event via API or database:
   ```php
   $event = Event::create([
       'type' => 'INDIVIDUAL',
       'status' => 'scheduled',
       'staff_id' => 1,
       'client_id' => 1,
       'room_id' => 1,
       'starts_at' => now()->addDay(),
       'ends_at' => now()->addDay()->addHour(),
       'notes' => 'Test sync',
   ]);
   ```

3. Check the logs:
   ```bash
   tail -f storage/logs/laravel.log | grep "Google Calendar"
   ```

4. Verify in Google Calendar:
   - The event should appear in the staff member's calendar
   - Check the event description for "Synced from FunctionalFit Calendar"

## Configuration Reference

### Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `GOOGLE_CALENDAR_SYNC_ENABLED` | Yes | `false` | Enable/disable sync |
| `GOOGLE_SERVICE_ACCOUNT_PATH` | For Service Account | `storage/app/google-service-account.json` | Path to service account JSON |
| `GOOGLE_CLIENT_ID` | For OAuth2 | - | OAuth2 client ID |
| `GOOGLE_CLIENT_SECRET` | For OAuth2 | - | OAuth2 client secret |
| `GOOGLE_REDIRECT_URI` | For OAuth2 | - | OAuth2 redirect URI |
| `GOOGLE_CALENDAR_APP_NAME` | No | `FunctionalFit Calendar` | Application name for API |

### Service Configuration

Edit `config/services.php` if you need to customize:

```php
'google_calendar' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    'service_account_path' => env('GOOGLE_SERVICE_ACCOUNT_PATH', storage_path('app/google-service-account.json')),
    'sync_enabled' => env('GOOGLE_CALENDAR_SYNC_ENABLED', false),
    'application_name' => env('GOOGLE_CALENDAR_APP_NAME', 'FunctionalFit Calendar'),
    'scopes' => [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events',
    ],
],
```

## Event Mapping

### Internal Event → Google Calendar Event

| Internal Field | Google Calendar Field | Notes |
|----------------|----------------------|-------|
| `id` | `extendedProperties.private.internal_event_id` | For idempotency |
| Title (generated) | `summary` | "Session - {ClientName}" or "BLOCK: ..." |
| `notes` + metadata | `description` | Includes event details, client info |
| `starts_at` | `start.dateTime` | ISO 8601 with Europe/Budapest timezone |
| `ends_at` | `end.dateTime` | ISO 8601 with Europe/Budapest timezone |
| `room.name` + location | `location` | Combined room and facility address |
| `status` | `status` | 'cancelled' → 'cancelled', else 'confirmed' |
| `type` | `colorId` | BLOCK=8 (graphite), INDIVIDUAL=7 (peacock) |

### Extended Properties

Stored in `extendedProperties.private` for tracking:

```json
{
  "internal_event_id": "123",
  "system": "functionalfit",
  "sync_version": "1699900000",
  "event_type": "INDIVIDUAL"
}
```

## Sync Behavior

### When Sync Triggers

| Action | Trigger | Job |
|--------|---------|-----|
| Create event with `status='scheduled'` | Immediate | `SyncEventToGoogleCalendar` |
| Update event (scheduled) | Immediate | `SyncEventToGoogleCalendar` |
| Change status to 'cancelled' | Immediate | `DeleteEventFromGoogleCalendar` |
| Delete event (soft/hard) | Immediate | `DeleteEventFromGoogleCalendar` |
| Restore event (scheduled) | Immediate | `SyncEventToGoogleCalendar` |

### What Gets Synced

**Synced:**
- Events with `status = 'scheduled'`
- Event types: INDIVIDUAL, BLOCK
- Relevant field updates: starts_at, ends_at, staff_id, client_id, room_id, notes, type

**Not Synced:**
- Events with status: completed, cancelled, no_show
- Trivial updates (e.g., only updated_at changed)

## Idempotency & Retry Logic

### Idempotency

1. Before creating a new event, the service searches Google Calendar for events with matching `internal_event_id` in extended properties
2. If found, updates the existing event instead of creating a duplicate
3. Stores `google_event_id` in the database for faster lookups

### Retry Strategy

**Exponential Backoff:**
- Attempt 1: Immediate
- Attempt 2: Wait 60 seconds
- Attempt 3: Wait 120 seconds (2 minutes)
- Attempt 4: Wait 240 seconds (4 minutes)
- Attempt 5: Wait 480 seconds (8 minutes)
- Attempt 6: Wait 960 seconds (16 minutes)

**Retryable Errors:**
- 429 (Rate Limit Exceeded)
- 500, 502, 503, 504 (Server Errors)
- Network timeouts

**Non-Retryable Errors:**
- 400 (Bad Request)
- 401 (Unauthorized)
- 403 (Forbidden)
- 404 (Not Found)

### Jitter

To prevent thundering herd, retry delays include ±20% random jitter.

## Monitoring & Observability

### Structured Logs

Every sync operation logs:

```php
[
    'event_id' => 123,
    'google_event_id' => 'abc123...',
    'operation' => 'create|update|delete',
    'status' => 'success|failure|retry',
    'attempt_number' => 1,
    'latency_ms' => 250.5,
    'error_code' => 429,  // if failed
    'error_message' => '...',  // if failed
    'staff_id' => 5,
    'event_type' => 'INDIVIDUAL',
]
```

### Key Metrics to Track

1. **Sync Success Rate**: Percentage of successful syncs
2. **Retry Rate**: Percentage of jobs that required retries
3. **Latency**: p50, p95, p99 sync times
4. **Queue Depth**: Number of pending gcal-sync jobs
5. **Failed Jobs**: Count of permanently failed jobs

### Log Queries

**Successful syncs:**
```bash
grep "Google Calendar sync successful" storage/logs/laravel.log
```

**Failed syncs:**
```bash
grep "Google Calendar.*error" storage/logs/laravel.log
```

**Retry attempts:**
```bash
grep "attempt_number" storage/logs/laravel.log | grep -v "attempt_number\":1"
```

### Failed Job Handling

Permanently failed jobs (after 5 retries) are stored in the `failed_jobs` table.

**View failed jobs:**
```bash
php artisan queue:failed
```

**Retry a failed job:**
```bash
php artisan queue:retry {job-id}
```

**Retry all failed jobs:**
```bash
php artisan queue:retry all
```

**Flush failed jobs:**
```bash
php artisan queue:flush
```

## Staff Calendar Management

### Calendar ID Strategy

By default, the system uses the **primary calendar** for each staff member. The calendar ID is determined by the service account's access.

To customize calendar assignment:

1. **Store calendar IDs in database:**
   ```php
   // Add to staff_profiles table
   $table->string('google_calendar_id')->nullable();
   ```

2. **Update GoogleCalendarService::getCalendarIdForStaff():**
   ```php
   public function getCalendarIdForStaff(StaffProfile $staff): ?string
   {
       return $staff->google_calendar_id ?? 'primary';
   }
   ```

3. **Create separate calendars per staff:**
   - Use Google Calendar API to create calendars programmatically
   - Store the returned calendar ID in the database

### Sharing Calendars (Service Account)

**Critical:** When using service accounts, each staff member must share their calendar with the service account.

**Steps:**
1. Staff member opens Google Calendar
2. Selects their calendar → Settings → "Share with specific people"
3. Adds service account email (e.g., `functionalfit-sync@project-id.iam.gserviceaccount.com`)
4. Grants "Make changes to events" permission

## Troubleshooting

### Sync Not Working

**Check 1: Is sync enabled?**
```bash
grep GOOGLE_CALENDAR_SYNC_ENABLED .env
# Should be: GOOGLE_CALENDAR_SYNC_ENABLED=true
```

**Check 2: Is queue worker running?**
```bash
ps aux | grep "queue:work"
```

**Check 3: Check logs**
```bash
tail -100 storage/logs/laravel.log | grep "Google Calendar"
```

**Check 4: Check failed jobs**
```bash
php artisan queue:failed
```

### Common Errors

#### "Service account file not found"

**Cause:** `GOOGLE_SERVICE_ACCOUNT_PATH` points to non-existent file

**Fix:**
1. Verify file exists: `ls -la storage/app/google-service-account.json`
2. Check file permissions: `chmod 600 storage/app/google-service-account.json`
3. Update `.env` with correct path

#### "401 Unauthorized" or "403 Forbidden"

**Cause:** Authentication or permission issues

**Fix:**
1. Verify service account has Calendar API enabled
2. Check calendar sharing (staff calendar must be shared with service account)
3. Re-download service account JSON and replace old file

#### "404 Not Found" when updating

**Cause:** Event exists in DB but not in Google Calendar

**Fix:**
1. The system will automatically search by `internal_event_id` and create if needed
2. If persistent, manually clear `google_event_id` from DB:
   ```sql
   UPDATE events SET google_event_id = NULL WHERE id = 123;
   ```
3. Trigger re-sync (will create new event)

#### "429 Rate Limit Exceeded"

**Cause:** Too many API requests

**Fix:**
1. Jobs will automatically retry with exponential backoff
2. If persistent, implement request batching or increase backoff delays
3. Check Google Calendar API quotas in Cloud Console

### Debug Mode

Enable verbose logging:

```php
// In GoogleCalendarService.php, add debug logs
Log::debug('Google Calendar API request', [
    'calendar_id' => $calendarId,
    'event_data' => $googleEvent->toSimpleObject(),
]);
```

## Security Considerations

### Service Account JSON

- **Never commit** the service account JSON to version control
- Add to `.gitignore`:
  ```
  storage/app/google-service-account.json
  ```
- Store in encrypted storage for production
- Rotate keys periodically (every 90 days recommended)

### Permissions

- Grant **minimum required permissions** to service accounts
- Use **custom IAM roles** instead of Editor if possible
- Audit calendar sharing permissions regularly

### Data Privacy

- Event descriptions include client names and details
- Ensure compliance with GDPR/data protection laws
- Consider implementing data retention policies for synced events

## Advanced Usage

### Manual Sync Commands

Create artisan commands for manual operations:

#### Sync All Events

```bash
php artisan gcal:sync-all
```

**Implementation:** `app/Console/Commands/SyncAllEventsToGoogleCalendar.php`

```php
public function handle(GoogleCalendarService $service)
{
    $events = Event::where('status', 'scheduled')
        ->whereDoesntHave('google_event_id')
        ->get();

    foreach ($events as $event) {
        SyncEventToGoogleCalendar::dispatch($event)->onQueue('gcal-sync');
    }

    $this->info("Queued {$events->count()} events for sync");
}
```

#### Reset Staff Calendar

```bash
php artisan gcal:reset-staff {staffId}
```

**Implementation:** Removes all synced events for a staff member and re-syncs.

### Batch Operations

For bulk imports, use Laravel's job batching:

```php
use Illuminate\Support\Facades\Bus;

$jobs = $events->map(fn($event) => new SyncEventToGoogleCalendar($event));

Bus::batch($jobs)
    ->onQueue('gcal-sync')
    ->name('Bulk Event Sync')
    ->dispatch();
```

### Testing

#### Unit Tests

Test service methods with mocked Google API:

```php
public function test_push_event_creates_google_event()
{
    $service = Mockery::mock(GoogleCalendarService::class);
    $service->shouldReceive('pushEventToGoogleCalendar')
        ->once()
        ->andReturn('google-event-id-123');

    $event = Event::factory()->create();
    $result = $service->pushEventToGoogleCalendar($event);

    $this->assertEquals('google-event-id-123', $result);
}
```

#### Integration Tests

Test with Google Calendar test calendar:

1. Create a dedicated test calendar
2. Use test service account
3. Run sync operations
4. Verify via API

## Performance Optimization

### Caching

Calendar IDs are cached for 1 hour to reduce API calls:

```php
Cache::remember("gcal_calendar_id_staff_{$staff->id}", 3600, function () {
    // Fetch calendar ID
});
```

### Queue Prioritization

Use separate queues for different priorities:

```php
// High priority (immediate)
SyncEventToGoogleCalendar::dispatch($event)->onQueue('gcal-sync-high');

// Normal priority
SyncEventToGoogleCalendar::dispatch($event)->onQueue('gcal-sync');

// Low priority (bulk operations)
SyncEventToGoogleCalendar::dispatch($event)->onQueue('gcal-sync-low');
```

### Rate Limiting

Google Calendar API has quotas:
- **10,000 requests/day** (default)
- **500 requests/100 seconds/user**

For high-volume usage, request quota increase in Google Cloud Console.

## Future Enhancements

### Phase 2: Inbound Sync (If Needed)

If you need to import changes from Google Calendar back to the internal DB:

1. Implement webhook listeners (Google Calendar Push Notifications)
2. Create admin review queue for conflict resolution
3. Add conflict detection and merge strategies
4. Store sync metadata for comparison

**Note:** This violates the current "push-only" principle and adds complexity. Only implement if business requirements demand it.

### Calendar Permissions

Implement fine-grained permissions:
- Which staff members can sync to Google Calendar
- Which event types to sync
- Per-staff sync toggles

## Support

For issues or questions:
1. Check logs: `storage/logs/laravel.log`
2. Review failed jobs: `php artisan queue:failed`
3. Check Google Cloud Console for API errors
4. Contact system administrator

## Appendix

### Google Calendar API Resources

- [Google Calendar API Documentation](https://developers.google.com/calendar/api/guides/overview)
- [Service Account Authentication](https://developers.google.com/identity/protocols/oauth2/service-account)
- [OAuth2 Authentication](https://developers.google.com/identity/protocols/oauth2)
- [API Quotas and Limits](https://developers.google.com/calendar/api/guides/quota)

### Relevant Files

- Service: `backend/app/Services/GoogleCalendarService.php`
- Jobs: `backend/app/Jobs/SyncEventToGoogleCalendar.php`, `DeleteEventFromGoogleCalendar.php`
- Observer: `backend/app/Observers/EventObserver.php`
- Config: `backend/config/services.php`
- Model: `backend/app/Models/Event.php`
- Migration: `backend/database/migrations/*_create_events_table.php`

---

**Last Updated:** 2025-01-13
**Version:** 1.0
**Author:** FunctionalFit Development Team
