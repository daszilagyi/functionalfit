# Google Calendar szinkronizálás beállítása

## Probléma
A Google Calendar szinkronizálás nem működik, mert hiányzik a Service Account credentials fájl.

## Megoldás lépésről lépésre

### 1. Google Cloud Project létrehozása

1. Menj a [Google Cloud Console](https://console.cloud.google.com/)-ra
2. Hozz létre egy új projektet vagy válassz egy meglévőt
3. Projekt név: `FunctionalFit Calendar`

### 2. Google Calendar API engedélyezése

1. A projektben menj az **APIs & Services > Library** menüpontba
2. Keresd meg a **Google Calendar API**-t
3. Kattints az **Enable** gombra

### 3. Service Account létrehozása

1. Menj az **APIs & Services > Credentials** menüpontba
2. Kattints a **Create Credentials > Service Account** gombra
3. Töltsd ki az adatokat:
   - **Service account name**: `functionalfit-calendar-sync`
   - **Service account ID**: automatikusan generálódik
   - **Description**: `Service account for FunctionalFit calendar synchronization`
4. Kattints a **Create and Continue** gombra
5. **Grant this service account access to project** - hagyd üresen, kattints **Continue**
6. **Grant users access to this service account** - hagyd üresen, kattints **Done**

### 4. Service Account kulcs generálása

1. A létrehozott Service Account sorában kattints a neve mellett lévő **három pontra**
2. Válaszd a **Manage keys** opciót
3. Kattints az **Add Key > Create new key** gombra
4. Válaszd a **JSON** formátumot
5. Kattints a **Create** gombra
6. A letöltött JSON fájlt **mentsd el** a következő helyre:
   ```
   backend/storage/app/google-service-account.json
   ```

### 5. Service Account email cím hozzáadása a Google Calendarhoz

1. Nyisd meg a Service Account JSON fájlt
2. Másold ki a `client_email` mezőt (pl. `functionalfit-calendar-sync@projekt-id.iam.gserviceaccount.com`)
3. Nyisd meg a [Google Calendar](https://calendar.google.com/)-t
4. A bal oldali sávon kattints a megosztani kívánt naptár mellett lévő **három pontra**
5. Válaszd a **Settings and sharing** opciót
6. Görgess le a **Share with specific people** részhez
7. Kattints az **Add people** gombra
8. Illeszd be a Service Account email címét
9. Állítsd be a jogosultságot **Make changes to events** vagy **Make changes and manage sharing** szintre
10. Kattints a **Send** gombra

### 6. Naptár ID megszerzése

1. Ugyanott a Calendar beállításokban görgess le az **Integrate calendar** részhez
2. Másold ki a **Calendar ID**-t (pl. `daszilagyi@gmail.com` vagy egy hosszabb azonosító)
3. Ezt fogjuk használni a szinkronizálási konfigurációban

### 7. Adatbázis konfiguráció frissítése

```bash
cd backend
sqlite3 database/database.sqlite
```

Majd futtasd:

```sql
UPDATE google_calendar_sync_configs
SET google_calendar_id = 'IDE_ILLESZD_BE_A_CALENDAR_ID-T',
    sync_enabled = 1,
    sync_direction = 'both'
WHERE id = 1;
```

Vagy a frontend adminisztrációs felületén keresztül állítsd be.

### 8. Tesztelés

```bash
cd backend
php artisan tinker
```

Majd:

```php
$config = \App\Models\GoogleCalendarSyncConfig::first();
$service = app(\App\Services\GoogleCalendarImportService::class);
$startDate = new \DateTime('2025-12-01');
$endDate = new \DateTime('2025-12-31');
$log = $service->importEvents($config, $startDate, $endDate);
echo "Status: " . $log->status . "\n";
echo "Events processed: " . $log->events_processed . "\n";
echo "Events created: " . $log->events_created . "\n";
echo "Events updated: " . $log->events_updated . "\n";
echo "Conflicts: " . $log->conflicts_detected . "\n";
```

## Ellenőrzési lista

- [ ] Google Cloud Project létrehozva
- [ ] Google Calendar API engedélyezve
- [ ] Service Account létrehozva
- [ ] Service Account JSON kulcs letöltve és elhelyezve `backend/storage/app/google-service-account.json`-ba
- [ ] Service Account email cím hozzáadva a Google Calendarhoz megfelelő jogosultsággal
- [ ] Calendar ID bemásolva az adatbázis konfigurációba
- [ ] Szinkronizálás tesztelve

## Hibaelhárítás

### "Service account file not found"
- Ellenőrizd, hogy a JSON fájl a helyes helyen van-e: `backend/storage/app/google-service-account.json`
- Ellenőrizd a fájl jogosultságait (readable)

### "403 Forbidden" hiba
- A Service Account email címe nincs hozzáadva a naptárhoz
- A jogosultság nem megfelelő (legalább "Make changes to events" kell)

### "Calendar not found"
- A `google_calendar_id` hibás az adatbázisban
- Ellenőrizd a Calendar ID-t a Google Calendar beállításokban

### "Invalid credentials"
- A Service Account JSON fájl hibás vagy sérült
- Generálj új kulcsot és cseréld le a fájlt

## További információ

- [Google Calendar API dokumentáció](https://developers.google.com/calendar/api/guides/overview)
- [Service Account használata](https://cloud.google.com/iam/docs/service-accounts)
