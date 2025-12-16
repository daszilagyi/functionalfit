# Google Calendar Sync - Quick Start Guide

## 5-Minute Setup

### Prerequisites
- Google Cloud account
- Google Calendar API enabled
- Service account JSON file

### Step 1: Install (Already Done)
```bash
composer require google/apiclient
```

### Step 2: Configure Environment

Copy to your `.env`:

```env
GOOGLE_CALENDAR_SYNC_ENABLED=true
GOOGLE_SERVICE_ACCOUNT_PATH=storage/app/google-service-account.json
GOOGLE_CALENDAR_APP_NAME="FunctionalFit Calendar"
```

### Step 3: Add Service Account File

```bash
# Place your service account JSON file
cp /path/to/service-account.json backend/storage/app/google-service-account.json
chmod 600 backend/storage/app/google-service-account.json
```

### Step 4: Share Calendars

Each staff member must share their Google Calendar with the service account email (found in the JSON file):

1. Open Google Calendar
2. Calendar Settings → Share with specific people
3. Add service account email
4. Grant "Make changes to events" permission

### Step 5: Start Queue Worker

```bash
cd backend
php artisan queue:work --queue=gcal-sync,default --tries=5
```

### Step 6: Test

```php
// Create a test event
$event = Event::create([
    'type' => 'INDIVIDUAL',
    'status' => 'scheduled',
    'staff_id' => 1,
    'client_id' => 1,
    'room_id' => 1,
    'starts_at' => now()->addDay(),
    'ends_at' => now()->addDay()->addHour(),
    'notes' => 'Test Google Calendar sync',
]);

// Check logs
tail -f storage/logs/laravel.log | grep "Google Calendar"
```

## How It Works

1. **Event Created/Updated** → EventObserver triggers → SyncEventToGoogleCalendar job queued
2. **Job Executes** → GoogleCalendarService pushes to Google Calendar
3. **Success** → `google_event_id` saved to database
4. **Failure** → Auto-retry with exponential backoff (5 attempts max)

## Key Features

- **Idempotent**: Safe to retry, won't create duplicates
- **Automatic**: Syncs on create/update/delete
- **Resilient**: Exponential backoff retry (60s, 120s, 240s, 480s, 960s)
- **Observable**: Structured logs for all operations
- **Push-Only**: Internal DB is single source of truth

## What Gets Synced

✅ Events with `status = 'scheduled'`
✅ Event types: INDIVIDUAL, BLOCK
✅ Field updates: starts_at, ends_at, staff_id, client_id, room_id, notes

❌ Events with status: completed, cancelled, no_show

## Troubleshooting

### Sync not working?

```bash
# 1. Check sync is enabled
grep GOOGLE_CALENDAR_SYNC_ENABLED .env

# 2. Check queue worker is running
ps aux | grep "queue:work"

# 3. Check logs
tail -100 storage/logs/laravel.log | grep "Google Calendar"

# 4. Check failed jobs
php artisan queue:failed
```

### Common Issues

**"Service account file not found"**
→ Verify file exists and path is correct in .env

**"401 Unauthorized"**
→ Calendar not shared with service account email

**"404 Not Found" when updating**
→ Clear google_event_id: `UPDATE events SET google_event_id = NULL WHERE id = X`

## Complete Documentation

See `GOOGLE_CALENDAR_SETUP.md` for full documentation including:
- Detailed setup instructions
- OAuth2 authentication option
- Advanced configuration
- Monitoring & metrics
- Security best practices
- Manual sync commands

## Files Created

```
backend/
├── app/
│   ├── Services/GoogleCalendarService.php      # Core sync logic
│   ├── Jobs/
│   │   ├── SyncEventToGoogleCalendar.php      # Create/update job
│   │   └── DeleteEventFromGoogleCalendar.php  # Delete job
│   ├── Observers/EventObserver.php             # Auto-trigger sync
│   └── Providers/AppServiceProvider.php        # Observer registration
├── config/services.php                         # Google Calendar config
└── storage/app/
    └── google-service-account.json            # (You add this)
```

## Support

Questions? Check:
1. Full documentation: `GOOGLE_CALENDAR_SETUP.md`
2. Logs: `storage/logs/laravel.log`
3. Failed jobs: `php artisan queue:failed`
4. Google Cloud Console API errors

---

**Note:** This is a push-only sync. Changes made directly in Google Calendar are NOT synced back to the internal database.
