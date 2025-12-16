# FunctionalFit – Foglalási és Naptár Webapp

**Technológia:** Frontend: React (Vite/Next.js – lásd javaslat), Tailwind + shadcn/ui, React Query. Backend: PHP 8.3 (Laravel 11 javasolt), MySQL/MariaDB, Redis. Integrációk: Google Calendar API, SMTP/Transactional e‑mail (Mailersend/Sendgrid), WooCommerce (opcionális bérletvásárlás), SMS gateway (opcionális), OAuth2/OTP.

---

## 1) Cél és alapfogalmak

* Egy egységes webes alkalmazás, amely:

  * **Ügyfeleknek**: regisztráció, bejelentkezés csoportos órákra, saját aktivitás és bérlet/egyenleg megtekintése.
  * **Dolgozóknak**: privát 1:1 események rögzítése, naptárnézet, ügyfelek hozzárendelése, gyors módosítási jog napi kereten belül.
  * **Adminoknak**: erőforrások (helyiségek/"Rooms"), dolgozók, ügyfelek, csoportos órák kezelése, jogosultságok, riportok, elszámolás.
* A jelenlegi Google Naptár alapú működést **szinkronizáljuk**, de az igazság forrása (SoT) a **belső adatbázis** lesz.
* Helyszínek/erőforrás-naptárak (példa): SASAD Gym, SASAD Masszázs, SASAD Rehab, TB Gym, TB Nagyterem, TB Terem 1, 2, 3, ÚJBUDA Gym, Masszázs, Terem I–IV.

---

## 2) Szerepkörök és jogosultságmodell (RBAC)

* **Ügyfél (client)**

  * Regisztráció / bejelentkezés (e‑mail + jelszó, opcionális OTP/SMS).
  * Csoportos órák böngészése, foglalás/jelentkezés, várólista.
  * Saját **aktivitás** és **bérlet/egyenleg** megtekintése (felhasznált / fennmaradó alkalmak, dátumlista).
  * Lemondás a szabályok szerint (pl. ≥24h). No‑show jelző megjelenítése.
* **Dolgozó (staff)**

  * Saját naptárnézet és listanézet.
  * Új **egyéni esemény** (1:1) létrehozása, ügyfél hozzárendelése **adatlapból választással** (névvariációk tiltanak manuális gépeléssel).
  * **Napon belüli** (T+0) időpont‑tolás engedélyezett, **másnapra áthelyezés tiltott** (admin felülbírálhatja).
  * Saját eseményei exportja (CSV/XLSX), saját ügyfél-aktivitás összesítők.
* **Admin**

  * Teljes CRUD: dolgozók, ügyfelek, helyek/erőforrások, csoportos órák, bérletek.
  * Jogosultság kiosztás (role, per‑permission flags).
  * **Elszámolás** dolgozónként: megtartott óraszám és díjszabás alapján.
  * Ütközéskezelés (room/staff double‑booking tiltás), kivételek jóváhagyása.
  * Riportok, statisztikák, audit logok.

> **Kiegészítő admin funkciók:** munkaszüneti/blackout napok kezelése, erőforrás‑karbantartás, globális lemondási szabályok (külön ügyfél vs. dolgozó – 24h/12h), árképzés bérlettípusokhoz, automatizált havi e‑mail összesítők, számlázási export.

---

## 3) Fő funkciók és üzleti szabályok

### 3.1 Naptár és események

* Nézetek: **Hét/Nap/Lista**, Google Calendar‑szerű UI (színezés: helyiség és eseménytípus szerint).
* Eseménytípusok: `INDIVIDUAL` (1:1), `GROUP_CLASS` (kapacitással), `BLOCK` (zárás/karbantartás).
* **Ütközésgátlás**: ugyanarra az időre egy erőforrás vagy dolgozó nem foglalható kétszer.
* **Mozgatási szabályok**:

  * Dolgozó napon belül tolhatja a saját 1:1 eseményét (időablakon belül), más napra nem.
  * Csoportos órát csak admin (vagy dedikált jogosultságú dolgozó) módosíthat.
* **Lemondási szabályok**:

  * Ügyfél: ≥24h ingyenes, <24h no‑show / alkalom levonás (paraméterezhető).
  * Dolgozó: pl. ≥12h nyithat felhelyezést (belső szabály), <12h admin jóváhagyás szükséges.
* **Check‑in / jelenlét**: dolgozó jelöli meg, megjelent‑e az ügyfél; no‑show státusz mentése.

### 3.2 Csoportos órák

* Heti ismétlődés (RRULE) + kivételek.
* Kapacitás, várólista, automatikus értesítés felszabaduló hely esetén.
* Típus, helyszín, tréner, nehézség, leírás, szükséges eszközök.

### 3.3 Ügyfél-adatlap és bérletek

* Ügyfél azonosítása **adatlappal** (duplikáció elkerülése; e‑mail egyediség, telefonszám opció).
* Bérletek: típus (pl. 5/10/20 alkalmas, időkorlátos), érvényesség, felhasználás napló.
* Egyenlegkövetés: bejelentkezéskor vagy check‑innél vonódik az alkalom (paraméterezhető).
* Ügyfélportál: összes aktivitás dátumokkal, fennmaradó alkalmak.

### 3.4 Elszámolás

* Dolgozónként havi riport: megtartott 1:1 órák, lemondások, csoportos órák (vezetett alkalmak), díjszabás.
* Export: XLSX/CSV, könyvelésbarát formátum, tételes és összesítő nézetek.

### 3.5 Értesítések és automatizmusok

* E‑mail/SMS: foglalás visszaigazolás, emlékeztető (24h/3h), felszabadult hely, havi záró e‑mail (ügyfélnek és dolgozónak), no‑show értesítés.
* Ütemezések: queue + cron (Laravel Scheduler), retry, dead‑letter.

### 3.6 Webshop integráció (opcionális)

* WooCommerce (WordPress) – **bérletvásárlás** integrálása: order paid → webhook → bérlet jóváírás.
* Alternatíva: natív Stripe Checkout; mindkét irány REST webhook szinkron.

---

## 4) Google Calendar szinkron

* **Mapping**: minden helyiséghez fix Google Calendar ID.
* **Irányelvek**:

  * Belső DB a SoT. Kifelé push → GCal; befelé pull → csak összevetés/konfliktusfelismerés vagy import mód.
  * Jogosultság: szolgáltatásfiók / OAuth2. Minden külső kézi bejegyzést megjelölünk (pl. `[EXTERNAL]`).
* **Szinkronfolyamat**:

  * Outbound: belső esemény create/update/delete → GCal event (extendedProperties: internal IDs).
  * Inbound (opcionális): időzített import új‑/módosult eseményekről → admin felülvizsgálati sor (mappolás ügyfélhez/dolgozóhoz, majd elfogadás), vagy teljes tiltás a kézi GCal‑írásokra.

---

## 5) Adatmodell (ER – fő táblák)

* `users` (id, role {client, staff, admin}, name, email, phone, status, password_hash, last_login_at)
* `rooms` (id, site, name, google_calendar_id, color, capacity_optional)
* `staff_profiles` (user_id FK, bio, skills, default_site, visibility)
* `clients` (id, user_id nullable, full_name, doj, notes, gdpr_consent_at)
* `passes` (id, client_id, type, total_credits, credits_left, valid_from, valid_until, source {woo,stripe,manual}, status)
* `class_templates` (id, title, description, trainer_id, room_id, weekly_rrule, duration_min, capacity, tags)
* `class_occurrences` (id, template_id, starts_at, ends_at, room_id, trainer_id, status)
* `class_registrations` (id, occurrence_id, client_id, status {booked,waitlist,cancelled,no_show,attended}, booked_at, cancelled_at)
* `events` (id, type {INDIVIDUAL,BLOCK}, staff_id, client_id nullable, room_id, starts_at, ends_at, status)
* `event_changes` (id, event_id, action, by_user_id, meta JSON, created_at) – audit
* `billing_rules` (id, staff_id, rate_type, rate_value, applies_to {INDIVIDUAL/GROUP}, effective_from)
* `payouts` (id, staff_id, period_from, period_to, hours_total, amount_total, exported_at)
* `notifications` (id, user_id, channel, template_key, payload JSON, status)
* `settings` (key, value JSON)

> Indexek: `starts_at`/`ends_at` kombinált indexek időablak kereséshez; `room_id + starts_at` ütközésgátláshoz; `extendedProperties.event_id` (GCal synchez).

---

## 6) API specifikáció (v1 – REST)

**Auth/Session**

* `POST /auth/register` (client)
* `POST /auth/login`
* `POST /auth/logout`
* `POST /auth/otp` (opcionális)

**Közös**

* `GET /me` – profil + jogosultságok
* `GET /rooms` – erőforrások
* `GET /schedule?from=...&to=...&view=...` – kombinált feed (saját jog szerint szűrve)

**Ügyfél**

* `GET /classes` – listázás, szűrők (site, room, trainer, időtartomány)
* `POST /classes/{occurrenceId}/book`
* `POST /classes/{occurrenceId}/cancel`
* `GET /clients/{id}/activity` – saját aktivitás, bérletek

**Dolgozó**

* `GET /staff/my-events?from&to`
* `POST /events` – 1:1 létrehozás (client_id kötelező)
* `PATCH /events/{id}` – **csak napon belüli** módosítás engedett; backend validál
* `DELETE /events/{id}` – szabályok szerint
* `POST /events/{id}/checkin` – attended/no‑show
* `GET /staff/exports?period=YYYY-MM` – XLSX link

**Admin**

* CRUD: `/users`, `/staff`, `/clients`, `/rooms`, `/class-templates`, `/class-occurrences`
* `POST /classes/{occurrenceId}/force‑move` – kivételes módosítás
* `GET /reports/attendance?from&to&groupBy=room|trainer|client`
* `GET /reports/payouts?period=YYYY-MM`
* `POST /sync/gcal/push` | `POST /sync/gcal/pull` (ha inbound engedélyezett)

**Webhookok**

* `POST /webhooks/woocommerce` – order paid → pass credit add
* `POST /webhooks/stripe` – event dispatcher

**Hibakódok**: 409 Conflict (ütközés), 422 Validation, 403 Forbidden, 423 Locked (nem módosítható időablak), 429 Rate limit, 451 Policy.

---

## 7) Frontend – UX követelmények

* **Naptár UI**: Google Calendar‑szerű, drag‑to‑create (dolgozó), drag‑within‑day move.
* **Gyorskereső** ügyfélre (név, e‑mail, telefon), új ügyfél felvétele modálból.
* **Foglalási kártya**: kiválasztott ügyfél + szabályok jelzése (pl. „másnapra áthelyezéshez kérj admin jóváhagyást”).
* **Csoportos órák**: jól szűrhető katalógus, kapacitásjelző, várólista gomb.
* **Ügyfélportál**: letisztult aktivitáslista, bérlet csík (progress), letölthető havi összesítő.
* **Admin**: dashboard kpi: heti foglalások, kihasználtság helyenként, no‑show arány, top trénerek.
* **I18n**: HU elsődleges, EN opció; dátumformátum HU.
* **A11y**: billentyűzet‑navigáció, kontraszt, screen reader címkék.

---

## 8) Beágyazás a meglévő WordPress site‑ba

* **Opció A (ajánlott):** külön domain/app (pl. app.functionalfit.hu), WP‑n csak menüpont → SSO‑s link (aláírt JWT query param), vagy egyszerű **iframe** beágyazás a nyilvános csoportos órák listájához.
* **Opció B:** WooCommerce integráció csak bérletvásárlásra; a foglalás minden eleme az új appban.

---

## 9) Biztonság, audit, megfelelés

* GDPR: hozzájárulás napló, adatexport/kérelmek kezelése, adatminimalizálás.
* RBAC + további per‑object ellenőrzés (csak saját esemény).
* **Audit log** minden kritikus változtatásról (`event_changes`).
* Rate‑limiting, CSRF, 2FA (opcionális), jelszóhasító: Argon2id.
* PII titkosítás (telefon, megjegyzések) oszlop‑szinten.

---

## 10) Fejlesztési környezet és repo‑struktúra

```
/functionalfit
  /frontend  (React + Vite + TS)
  /backend   (Laravel 11, PHP 8.3)
  /infra     (docker-compose, nginx, mysql, redis)
```

**Docker compose**: nginx, php-fpm, mysql, redis, queue worker, scheduler. **.env** példányok külön (local/stage/prod). CI: GitHub Actions – build, tests, deploy artefacts.

---

## 11) Migráció és seed

* Seed: helyek/rooms, mintatrénerek, heti minta csoportos óra sablonok.
* Import: jelenlegi Google naptár eseményeinek egyszeri importja admin ellenőrző listára (duplikációk egyesítése, ügyfél‑match e‑mail/telefon alapján).

---

## 12) Tesztelés

* Unit: ütközésdetektálás, lemondási/módosítási szabályok.
* Feature: foglalási flow (ügyfél), 1:1 létrehozás és napi‑tolás (dolgozó), admin override.
* Integráció: Woo/Stripe webhookok, GCal push.
* E2E: Cypress: ügyfél bejelentkezik → foglal → lemond → riport generál.

---

## 13) Példa validációs logikák (pszeudó)

```pseudo
function canMoveEvent(user, event, newStart):
  if user.role == 'admin': return true
  if user.id != event.staff_id: return false
  if date(newStart) != date(event.starts_at): return false // csak napon belül
  if now() > event.starts_at - settings.staff_move_lock: return false
  return true

function canCancelClient(occurrence, client):
  delta = occurrence.starts_at - now()
  if delta >= settings.client_cancel_free_hours: return true
  else return settings.consume_credit_on_late_cancel
```

---

## 14) Minta endpoint szerződések

**POST /events** (dolgozó)

```json
{
  "type": "INDIVIDUAL",
  "client_id": 123,
  "room_id": 9,
  "starts_at": "2025-11-15T18:00:00+01:00",
  "ends_at": "2025-11-15T19:00:00+01:00"
}
```

Válasz 201: `{ id, gcal_event_id, ... }`

**POST /classes/{id}/book** (ügyfél)

```json
{ "client_id": 123, "consume_on": "checkin" }
```

Válasz: `{"status":"booked","position":1}` vagy `{"status":"waitlist","position":3}`

---

## 15) Riportok és exportok

* **Kihasználtság**: room/trainer/nap bontás, hőtérkép.
* **No‑show arány**: ügyfél és dolgozó szerint.
* **Bevételi előrejelzés**: jövőbeni foglalások és bérlet‑lejáratok alapján.
* **Elszámolás**: óraszám × díj, külön sor csoportos vs. 1:1; export XLSX sablon szerint.

---

## 16) UI komponens‑vázlatok (shadcn/ui + Tailwind)

* CalendarBoard, EventCard, ClassCard, ClientPicker (typeahead), PassBadge, RoomPill, CapacityBar, MoveWithinDayDialog, ConflictToast, ReportTable, XLSXExportButton.

---

## 17) Teljesítmény és skálázás

* Időablakos lekérdezésekre optimalizált indexek.
* Redis cache + HTTP etag.
* Queue‑zott értesítések, batch e‑mailek.

---

## 18) Ütemezett feladatok

* `0 7 * * *` – napi emlékeztetők.
* `0 0 1 * *` – havi ügyfél‑összesítők (portál linkkel), dolgozói elszámoló draft.
* `*/10 * * * *` – várólista felszabadulás figyelése.
* `*/15 * * * *` – GCal push/pull diff (ha inbound bekapcsolt).

---

## 19) Bevezetési ütemterv (rövid)

1. Architektúra + DB sémák + auth (2 hét)
2. Naptár backend + ütközésgátlás + alap UI (3 hét)
3. Csoportos órák + foglalás + ügyfélportál (3 hét)
4. Admin modul + riportok + export (3 hét)
5. GCal push + Woo/Stripe webhookok (2 hét)
6. Pilot, adatimport, finomhangolás (2 hét)

---

## 20) Nyitott döntések

* GCal inbound szinkron bekapcsolása vagy teljes tiltása? (ajánlott: tilt, csak push)
* Bérletlevonás időpontja: foglaláskor vs. check‑innél? (ajánlott: check‑in)
* No‑show szabály pontos értékei (24h/12h paraméterezve Settings‑ben)

---

### Megjegyzés a Claude Code‑nak

* Kövesd a fenti API‑szerződéseket és RBAC‑ot.
* Írj típusos frontendet (TypeScript), React Query cache‑szabályokkal.
* Laravel Policy‑kban érvényesítsd a „napon belüli mozgatás” szabályt.
* Írj integrációs teszteket a konfliktus‑detektálásra és a lemondási logikára.
* Adj admin feature‑flaget a GCal inbound sync kapcsolására és a késői lemondás levonási módjára.

---

# 21) Claude Code – Agentek és utasítások

Az alábbi szerepekhez külön **Claude Code agent** promptokat készíts. Mindegyik egy-egy *context prompt* (system-level stílus), amelyhez a feladatokat a Chat / Tasks során adod.

## 21.1 Product/PM Agent (Spec Keeper)

**Cél:** A specifikáció konzisztenst tartani, követelményeket pontosítani, és PR‑szinten ellenőrizni, hogy a kód megfelel-e az üzleti szabályoknak.
**Input:** Üzleti igények, specifikáció, változáskérés.
**Output:** Elfogadási kritériumok, user story-k, DoD, release notes.
**Stílus:** Tömör, következetes, traceability.
**Feladatlista:**

* Derive user story-k (INVEST), acceptance criteria Gherkinben.
* Verziózott *CHANGELOG.md* és *docs/spec-updates.md* karbantartása.
* PR-k review-ja üzleti megfelelőség szerint.

**Alapprompt:**

> Te vagy a Product/PM Agent. Feladatod, hogy a *FunctionalFit* projekt üzleti követelményeit karbantartsd, user story-kat és acceptance criteria-t írj, és ellenőrizd, hogy a kód megfelel-e a specifikációnak (RBAC, lemondási/módosítási szabályok, elszámolás). Outputjaid rövidek, Gherkin vagy táblázatos formában legyenek. Ne írj kódot.

---

## 21.2 Backend Agent (Laravel/PHP)

**Cél:** Laravel 11 backend, REST API v1, RBAC (Policies), ütközésgátlás, értesítési queue, Woo/Stripe webhookok, GCal push integráció.
**Input:** API szerződés, ER diagram, acceptance criteria.
**Output:** Laravel kód, migrációk, seeder, Feature/Unit tesztek, API dokumentáció.
**Konvenciók:** PHP 8.3, strict types, Laravel Policies, FormRequest validation, DTO-k, Pest tesztek.
**Feladatlista:**

* Entitások és migrációk létrehozása (users, rooms, events, class_* stb.).
* Ütközésdetektálás + 409 hibakezelés.
* Napon belüli mozgatás Policy-ban érvényesítve.
* Notifications (mail/SMS) queue-zva, Scheduler beállítások.
* Webhook endpointok és aláírás-ellenőrzés.

**Alapprompt:**

> Te vagy a Backend Agent. Laravel 11-ben valósítsd meg az API v1-et a megadott entitásokkal és szabályokkal. Írj migrációkat, Eloquent modelleket, Policy-kat és Pest teszteket. Tartsd be a 409/422/403/423 hibakód-szemantikát. Készíts OpenAPI/Swagger leírást is.

---

## 21.3 Frontend Agent (React/TS)

**Cél:** React + Vite + TypeScript + Tailwind + shadcn/ui alapú kliens. React Query cache, i18n (HU/EN), A11y.
**Input:** API szerződés, UX vázlatok.
**Output:** Oldalak, komponensek, formok (Zod), állapotkezelés, routing, E2E tesztekhez selectorok.
**Feladatlista:**

* Naptárnézet (week/day/list), drag‑within‑day move, ClientPicker, CapacityBar.
* Csoportos órák katalógus + foglalási flow + várólista.
* Ügyfélportál (aktivitás + bérlet progress), Dolgozó nézet (saját események), Admin dashboard.

**Alapprompt:**

> Te vagy a Frontend Agent. Implementáld a képernyőket és komponenseket a specifikáció szerint. Használj TypeScriptet, React Query-t, Zodot, és shadcn/ui-t. Ütközés/hibák felhasználóbarát toasthibákkal.

---

## 21.4 Calendar/GCal Agent

**Cél:** Google Calendar push szinkron (service account/OAuth2), extendedProperties-ben belső azonosítók.
**Input:** GCal API dokumentáció, belső esemény-modellek.
**Output:** Szervizréteg, tokenkezelés, retry/backoff, idempotencia.
**Feladatlista:**

* Event create/update/delete → GCal mapping.
* Inbound import *feature-flag* mögött, admin review queue.
* Konfliktuskezelés, naplózás.

**Alapprompt:**

> Te vagy a Calendar/GCal Agent. Készíts idempotens push szinkront GCal felé. Belső DB a SoT. Használj extendedProperties-t az event kapcsoláshoz. Írj retry/backoffot és részletes loggolást.

---

## 21.5 Data & Reporting Agent

**Cél:** Elszámolás, kihasználtság, no‑show arány, exportok (CSV/XLSX), könyvelésbarát struktúrák.
**Input:** DB séma, időszakok, díjszabás.
**Output:** SQL lekérdezések, Eloquent scope-ok, riport endpointok, XLSX generátorok.
**Feladatlista:**

* Payout riport (óraszám × díj) dolgozónként.
* Attendance/kihasználtság idősoros riportok.
* Export API + letöltési linkek, jogosultság ellenőrzés.

**Alapprompt:**

> Te vagy a Data & Reporting Agent. Készíts hatékony (index‑barát) riportvégpontokat és XLSX exportot. Ügyelj a jogosultságokra és az időzónára (Europe/Budapest).

---

## 21.6 Auth & Security Agent

**Cél:** RBAC, Policies, 2FA/OTP (opcionális), audit log, PII titkosítás, rate limiting, CSRF.
**Input:** RBAC mátrix, érzékeny mezők.
**Output:** Middleware-ek, Policies, titkosítás, naplózás.
**Feladatlista:**

* Role + per‑object access ellenőrzés.
* Audit log az esemény változásokról.
* PII oszlop‑titkosítás, Argon2id jelszavak.

**Alapprompt:**

> Te vagy az Auth & Security Agent. Biztosítsd a megfelelést (GDPR), implementáld a Policies-t és audit logot. Védelmek: CSRF, rate limit, input sanitization.

---

## 21.7 DevOps Agent (CI/CD & Infra)

**Cél:** Docker-compose, Nginx + PHP‑FPM, MySQL, Redis, queue worker, scheduler, GitHub Actions CI.
**Input:** Repo struktúra, .env, deploy target.
**Output:** Docker és GitHub Actions fájlok, migráció futtatás, zero-downtime deploy.
**Feladatlista:**

* Build/test/lint pipeline.
* .env secrets kezelése, artefactok.
* Monitoring/healthcheck endpointok.

**Alapprompt:**

> Te vagy a DevOps Agent. Készíts futtatható docker-compose stacket és CI pipeline-t. Gondoskodj az ütemezők és queue-k futtatásáról és healthcheckről.

---

## 21.8 QA/Test Agent

**Cél:** Unit/Feature tesztek (Pest), E2E (Cypress), kontraktus tesztek (OpenAPI).
**Input:** Acceptance criteria, API spec.
**Output:** Tesztforgatókönyvek, automatizált tesztek, coverage report.
**Feladatlista:**

* Foglalási flow, napon belüli mozgatás, lemondási logika.
* RBAC jogosultságok és tiltott műveletek.
* Webhookok happy/sad path.

**Alapprompt:**

> Te vagy a QA/Test Agent. Készíts automatizált teszteket a kritikus folyamatokra. Használj seedelt adatokat és mockokat, ahol szükséges.

---

## 21.9 Payments & Commerce Agent (Woo/Stripe)

**Cél:** Bérletvásárlás WooCommerce/Stripe integráció, webhook szinkron a *passes* táblába.
**Input:** Woo/Stripe webhook események.
**Output:** Webhook handler, aláírás‑ellenőrzés, hibakezelés/retry.
**Feladatlista:**

* Order paid → pass credit add, idempotencia kulccsal.
* Refund/cancel edge case-ek kezelése.

**Alapprompt:**

> Te vagy a Payments & Commerce Agent. Valósítsd meg a bérletvásárlások szinkronját. A webhookok legyenek idempotensek és aláírás‑ellenőrzöttek.

---

# 22) Parancsfile a Claude Code‑nak

Helyezd a repo gyökerébe `commands/functionalfit.cmd.md` néven.

```md
# FunctionalFit – Command File

## Project
- Name: FunctionalFit Booking & Calendar
- Stack: React (Vite+TS), Tailwind, shadcn/ui, React Query; Laravel 11 (PHP 8.3), MySQL, Redis
- Timezone: Europe/Budapest
- SoT: Internal DB; Google Calendar: push sync

## Repos & Structure
- /frontend – React app
- /backend – Laravel API
- /infra – docker-compose, nginx, mysql, redis, scheduler, queue

## Primary Docs
- docs/spec.md (üzleti specifikáció)
- docs/openapi.yaml (API szerződés)
- docs/er-diagram.png

## Agents
- Product/PM, Backend, Frontend, Calendar/GCal, Data & Reporting, Auth & Security, DevOps, QA/Test, Payments & Commerce

## Global Conventions
- API errors: 409 (conflict), 422 (validation), 403 (forbidden), 423 (locked)
- RBAC: client, staff, admin; Policies + per‑object checks
- Date handling: all timestamps stored UTC, UI in Europe/Budapest

## Must‑Have Epics
1. Auth + RBAC + Audit Log
2. Calendar Core (events, collision, move-within-day)
3. Group Classes (RRULE, capacity, waitlist)
4. Client Portal (activity, passes)
5. Reporting & Payouts
6. GCal Push Sync
7. Commerce Webhooks (Woo/Stripe)

## Definition of Done
- Unit/Feature/E2E tesztek zöldek, coverage ≥ 70%
- OpenAPI naprakész; *docs/spec-updates.md* frissítve
- Accessibility alapok, i18n HU/EN
- Security review: Policies, CSRF, rate limits, PII encryption

## Start Commands
- Dev: `docker compose up -d` → backend `.env.example` másolat, `php artisan migrate --seed`
- Frontend: `pnpm i && pnpm dev`
- Tests: backend `pnpm pest`, frontend `pnpm vitest`, e2e `pnpm cypress`

```

# 23) Machine‑readable projektfájl (opcionális)

Helyezd `commands/functionalfit.project.yaml` néven.

```yaml
project: FunctionalFit Booking & Calendar
stack:
  frontend: [react, typescript, vite, tailwind, shadcn-ui, react-query]
  backend: [php8.3, laravel11, mysql, redis]
  infra: [docker, nginx, github-actions]
roles:
  - product_pm
  - backend
  - frontend
  - calendar_gcal
  - data_reporting
  - auth_security
  - devops
  - qa_test
  - payments_commerce
timezone: Europe/Budapest
sot: internal_db
calendar_sync: push_only
acceptance:
  coverage_min: 0.7
  error_codes: [409, 422, 403, 423]
  rbac_roles: [client, staff, admin]
  i18n: [hu, en]
```
