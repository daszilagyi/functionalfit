# FunctionalFit Calendar - Felhasználói Útmutató

## Tartalomjegyzék

1. [Rendszer Áttekintés](#rendszer-áttekintés)
2. [Szerepkörök és Jogosultságok](#szerepkörök-és-jogosultságok)
3. [Teszt Felhasználók](#teszt-felhasználók)
4. [Funkciók Szerepkörönként](#funkciók-szerepkörönként)
5. [Gyakori Műveletek](#gyakori-műveletek)
6. [Hibaelhárítás](#hibaelhárítás)

---

## Rendszer Áttekintés

A FunctionalFit Calendar egy edzőtermi foglalási és adminisztrációs rendszer, amely lehetővé teszi:
- Csoportos órák és egyéni edzések kezelését
- Foglalások és lemondások adminisztrálását
- Bérletek és fizetések nyomon követését
- Jelentések és riportok készítését
- Google Calendar szinkronizálást

A rendszer három fő felhasználói szerepkört támogat, mindegyik különböző jogosultságokkal és funkciókkal.

---

## Szerepkörök és Jogosultságok

### 1. Admin (Adminisztrátor)

**Leírás:** Teljes körű hozzáféréssel rendelkezik a rendszer minden funkciójához. Felelős a rendszer konfigurálásáért, felhasználók kezeléséért és üzleti riportok készítéséért.

**Jogosultságok:**
- Minden funkció elérhető
- Felhasználók (admin, staff, client) létrehozása, módosítása, törlése
- Helyszínek és termek kezelése
- Órasablonok és csoportos órák konfigurálása
- Árképzés és elszámolások kezelése
- Riportok és statisztikák megtekintése
- Email sablonok szerkesztése
- Google Calendar integráció beállítása
- Audit logok megtekintése
- Bármely esemény módosítása vagy törlése

### 2. Staff (Edző/Munkatárs)

**Leírás:** Az edzők és munkatársak szerepköre. Saját naptárjukat kezelhetik, eseményeket hozhatnak létre, és láthatják a hozzájuk tartozó ügyfeleket.

**Jogosultságok:**
- Saját naptár megtekintése és kezelése
- 1:1 edzések létrehozása, módosítása (csak ugyanazon napon belül)
- Csoportos órákon résztvevők kezelése (saját órák)
- Check-in funkció: jelenlét/no-show jelölése
- Termek megtekintése (csak olvasás)
- Ügyfelek keresése (foglaláshoz)
- Saját riportok megtekintése (óraszám, bevétel)
- Export készítése (jelenlét, kifizetés)
- Saját naptármódosítások megtekintése

### 3. Client (Ügyfél/Vendég)

**Leírás:** A rendszer végfelhasználói, akik csoportos órákra foglalhatnak és nyomon követhetik aktivitásukat.

**Jogosultságok:**
- Csoportos órák böngészése és foglalása
- Foglalások lemondása
- Saját aktivitás és bérletek megtekintése
- Közelgő foglalások listázása
- Személyes beállítások módosítása
- Értesítési preferenciák kezelése

---

## Teszt Felhasználók

### Bejelentkezési Adatok

A rendszerben az alábbi teszt felhasználók állnak rendelkezésre. **Minden felhasználó jelszava: `password`**

#### Admin Felhasználó

| Mező | Érték |
|------|-------|
| **Név** | Admin User |
| **Email** | admin@functionalfit.hu |
| **Jelszó** | password |
| **Telefon** | +36201234567 |
| **Szerepkör** | admin |
| **Státusz** | active |

#### Staff Felhasználók (Edzők)

| Név | Email | Telefon | Telephely | Szakterület |
|-----|-------|---------|-----------|-------------|
| János Kovács | janos.kovacs@functionalfit.hu | +36201234568 | SASAD | Personal Training, Rehabilitáció, Sportmasszázs |
| Éva Nagy | eva.nagy@functionalfit.hu | +36201234569 | TB | Jóga, Pilates, Csoportos órák |
| Péter Tóth | peter.toth@functionalfit.hu | +36201234570 | ÚJBUDA | CrossFit, Funkcionális edzés, Táplálkozás |

**Minden staff jelszava: `password`**

#### Client Felhasználók (Ügyfelek)

| Név | Email | Telefon |
|-----|-------|---------|
| Anna Szabó | anna.szabo@example.com | +36301234567 |
| Béla Kiss | bela.kiss@example.com | +36301234568 |
| Csilla Varga | csilla.varga@example.com | +36301234569 |

**Minden client jelszava: `password`**

---

## Funkciók Szerepkörönként

### Admin Funkciók

#### Irányítópult (`/admin/dashboard`)
- Rendszer áttekintő statisztikák
- Gyors műveletek elérése
- Mai foglalások összesítése

#### Felhasználók Kezelése (`/admin/users`)
- Felhasználók listázása, szűrése, keresése
- Új felhasználó létrehozása (admin/staff/client)
- Felhasználó adatainak módosítása
- Felhasználó aktiválása/deaktiválása
- Felhasználó törlése (soft delete)

#### Telephelyek Kezelése (`/admin/sites`)
- Telephelyek (SASAD, TB, ÚJBUDA) kezelése
- Új telephely hozzáadása
- Telephely aktiválása/deaktiválása

#### Termek Kezelése (`/admin/rooms`)
- Termek listázása telephelyenként
- Új terem létrehozása
- Terem kapacitás, felszereltség beállítása
- Terem módosítása, törlése

#### Órasablonok (`/admin/class-templates`)
- Csoportos óra típusok definiálása
- Sablonok (név, leírás, időtartam, kapacitás)
- Alapértelmezett árképzés beállítása
- Bérlet követelmények (credits_required)

#### Email Sablonok (`/admin/email-templates`)
- Automatikus emailek szerkesztése
- Változók használata ({{client_name}}, {{event_date}}, stb.)
- Sablon előnézet és teszt küldés
- Verziókezelés és visszaállítás

#### Email Shortcode-ok (`/admin/email-shortcodes`)
- Elérhető változók listája
- Használati útmutató

#### Google Calendar Szinkronizálás (`/admin/google-calendar-sync`)
- Service account konfiguráció
- Import/Export műveletek
- Szinkronizálási logok megtekintése
- Konfliktusok kezelése

#### Árképzés (`/admin/pricing`)
- Alapértelmezett árak beállítása
- Ügyfél-specifikus árak
- Szolgáltatástípusok szerinti árképzés

#### Szolgáltatástípusok (`/admin/service-types`)
- Szolgáltatás kategóriák kezelése
- Aktív/inaktív státusz

#### Elszámolások (`/admin/settlements`)
- Edzői kifizetések kezelése
- Elszámolási időszakok
- Státusz követés (draft, approved, paid)

#### Riportok (`/admin/reports`)
- Jelenlét riport
- Kifizetési riport
- Bevételi riport
- Kihasználtsági riport
- Ügyfél riport
- Excel export minden riportból

#### Eseménymódosítások (`/admin/event-changes`)
- Naptármódosítások audit logja
- Részletes előtte/utána összehasonlítás
- Szűrés dátum, felhasználó, terem szerint

---

### Staff Funkciók

#### Irányítópult (`/dashboard`)
- Napi áttekintés
- Mai foglalások
- Közelgő események

#### Naptár (`/calendar`)
- Heti/napi nézet
- Saját események megtekintése
- Terem szerinti szűrés
- Esemény részletek

#### Új Esemény Létrehozása
- 1:1 edzés foglalása
- Ügyfél kiválasztása (keresővel)
- Dátum, időpont, terem megadása
- Megjegyzés hozzáadása

#### Események Kezelése
- Esemény módosítása (csak ugyanazon napon belül!)
- Esemény törlése
- Résztvevők kezelése csoportos órákon

#### Check-in (`/staff/events/{id}/checkin`)
- Megjelenés jelölése
- No-show jelölése
- Bérlet automatikus levonása

#### Riportok (`/staff`)
- Saját órák összesítése
- Ügyfél statisztikák
- Trendek áttekintése

---

### Client Funkciók

#### Irányítópult (`/dashboard`)
- Közelgő foglalások
- Bérlet egyenleg
- Gyors foglalás

#### Csoportos Órák (`/classes`)
- Órák böngészése dátum szerint
- Szabad helyek megtekintése
- Foglalás leadása
- Várólista funkció

#### Aktivitás (`/activity`)
- Foglalási előzmények
- Jelenlét statisztika
- Bérlet használat

#### Beállítások (`/settings`)
- Profil adatok módosítása
- Értesítési preferenciák

---

### Nyilvános Funkciók (Bejelentkezés nélkül)

#### Nyilvános Órarend (`/public/classes`)
- Csoportos órák megtekintése
- Szabad helyek láthatósága
- Regisztráció nélküli böngészés

---

## Gyakori Műveletek

### Bejelentkezés
1. Nyisd meg a `/login` oldalt
2. Add meg az email címed és jelszavad
3. Kattints a "Bejelentkezés" gombra

### Elfelejtett Jelszó
1. A bejelentkezési oldalon kattints az "Elfelejtett jelszó" linkre
2. Add meg az email címed
3. Ellenőrizd a postaládádat
4. Kövesd a levélben kapott linket

### Csoportos Órára Foglalás (Client)
1. Navigálj a "Csoportos órák" menüpontra
2. Válaszd ki a kívánt dátumot
3. Kattints a kívánt órára
4. Erősítsd meg a foglalást
5. Ha nincs szabad hely, feliratkozhatsz a várólistára

### Foglalás Lemondása (Client)
1. Nyisd meg az "Aktivitás" oldalt
2. Keresd meg a lemondandó foglalást
3. Kattints a "Lemondás" gombra
4. Erősítsd meg a műveletet

### 1:1 Edzés Létrehozása (Staff)
1. Nyisd meg a Naptárat
2. Kattints egy üres időpontra vagy a "+" gombra
3. Válaszd ki az ügyfelet
4. Add meg az időpontot és termet
5. Mentsd el az eseményt

### Check-in Végrehajtása (Staff)
1. Nyisd meg az esemény részleteit
2. Kattints a "Check-in" gombra
3. Jelöld be a megjelent résztvevőket
4. A rendszer automatikusan levonja a bérletet

### Riport Exportálása (Admin)
1. Navigálj az Admin > Riportok menüpontra
2. Válaszd ki a riport típusát
3. Állítsd be a szűrőket (dátum, telephely, stb.)
4. Kattints az "Export" gombra
5. A letöltés automatikusan elindul (Excel formátum)

---

## Hibaelhárítás

### "Nincs jogosultságod" hibaüzenet
- Ellenőrizd, hogy a megfelelő szerepkörrel vagy bejelentkezve
- Admin funkciók csak admin felhasználóknak érhetők el
- Staff funkciók staff és admin felhasználóknak érhetők el

### Nem látom a naptárat
- A naptár csak staff és admin szerepkörrel érhető el
- Client felhasználók a "Csoportos órák" menüponton keresztül foglalhatnak

### Nem tudom módosítani az eseményt
- Staff felhasználók csak ugyanazon napon belül módosíthatják az eseményeket
- Múltbeli események nem módosíthatók
- Admin felhasználók bármely eseményt módosíthatnak

### A foglalás sikertelen
- Ellenőrizd, hogy van-e szabad hely
- Ellenőrizd, hogy van-e elegendő bérleted
- Ha nincs szabad hely, iratkozz fel a várólistára

### Nem kapok email értesítést
- Ellenőrizd a Beállítások > Értesítések menüpontot
- Ellenőrizd a spam/levélszemét mappát
- Kérd az admint az email konfiguráció ellenőrzésére

---

## API Végpontok Összefoglaló

### Publikus végpontok
- `GET /api/v1/public/classes` - Nyilvános órarend

### Hitelesítés
- `POST /api/v1/auth/login` - Bejelentkezés
- `POST /api/v1/auth/logout` - Kijelentkezés
- `POST /api/v1/auth/forgot-password` - Jelszó emlékeztető
- `POST /api/v1/auth/reset-password` - Jelszó visszaállítás

### Client végpontok
- `GET /api/v1/classes` - Órák listázása
- `POST /api/v1/classes/{id}/book` - Foglalás
- `POST /api/v1/classes/{id}/cancel` - Lemondás
- `GET /api/v1/clients/{id}/activity` - Aktivitás
- `GET /api/v1/clients/{id}/upcoming` - Közelgő foglalások

### Staff végpontok
- `GET /api/v1/staff/my-events` - Saját események
- `POST /api/v1/staff/events` - Esemény létrehozása
- `PATCH /api/v1/staff/events/{id}` - Esemény módosítása
- `POST /api/v1/staff/events/{id}/checkin` - Check-in

### Admin végpontok
- `GET/POST/PUT/DELETE /api/v1/admin/users` - Felhasználók
- `GET/POST/PUT/DELETE /api/v1/admin/rooms` - Termek
- `GET/POST/PUT/DELETE /api/v1/admin/class-templates` - Órasablonok
- `GET /api/v1/admin/reports/*` - Riportok

---

## Támogatás

Technikai problémák esetén keresd az adminisztrátort a következő címen:
- Email: admin@functionalfit.hu
- Telefon: +36201234567

---

*Dokumentáció verzió: 1.0*
*Utolsó frissítés: 2025. december*
