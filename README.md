# FunctionalFit Calendar

**Csoportos edzések és egyéni foglalások kezelő rendszere**

Modern, full-stack alkalmazás edzőtermek, wellness központok és fitnesz stúdiók számára. A rendszer lehetővé teszi csoportos órák kezelését, online foglalást, bérletkezelést és automatikus értesítéseket.

![Version](https://img.shields.io/badge/version-0.1.0--beta-blue)
![Laravel](https://img.shields.io/badge/Laravel-11-red)
![React](https://img.shields.io/badge/React-18-blue)
![TypeScript](https://img.shields.io/badge/TypeScript-5-blue)
![License](https://img.shields.io/badge/license-MIT-green)

## Funkciók

### Naptár és Foglalás
- **Interaktív naptár nézet** - Heti/napi/lista nézet váltással
- **Drag & drop** esemény mozgatás (admin)
- **Valós idejű szabad helyek** megjelenítése
- **Több helyszín támogatása** szűrővel
- **Mobilbarát reszponzív dizájn**

### Csoportos Órák Kezelése
- **Óra sablonok** ismétlődő órákhoz
- **Óra előfordulások** egyedi módosításokkal
- **Várólistás rendszer** telített órákhoz
- **Automatikus várólistáról felhozás** lemondáskor

### Felhasználókezelés és Jogosultságok (RBAC)
- **Admin** - Teljes hozzáférés, beállítások
- **Staff/Edző** - Saját órák kezelése, résztvevők
- **Ügyfél** - Foglalás, saját adatok, bérletek

### Bérlet és Árképzés
- **Többféle bérlet típus** (alkalmi, 5/10 alkalmas, havi)
- **Ügyfél-specifikus árak** (VIP, kedvezményes)
- **Bérlet egyenleg követés**
- **Árkalkulátor** foglaláskor

### Email Értesítések
- **9 testreszabható sablon**:
  - Regisztráció megerősítése
  - Jelszó visszaállítás
  - Foglalás visszaigazolás
  - Foglalás lemondás
  - Várólistáról felhozás
  - Óra emlékeztető
  - Óra módosítás
  - Óra törlés
  - Fiók törlés
- **HTML + szöveges verzió**
- **Változók támogatása** ({{user.name}}, {{class.title}}, stb.)
- **Verziókezelés** a sablonokhoz

### Google Calendar Integráció
- **Kétirányú szinkron** Google Naptárral
- **Service Account** alapú hitelesítés
- **Automatikus esemény frissítés**

### Riportok és Statisztikák
- **Jelenlét kimutatás**
- **Edző munkaidő összesítő**
- **Foglalási statisztikák**
- **Excel export**

### Többnyelvűség
- Magyar (hu) - alapértelmezett
- Angol (en)
- i18next alapú fordítási rendszer

## Technológiai Stack

### Backend
- **PHP 8.3** + **Laravel 11**
- **SQLite** (fejlesztéshez) / **MySQL 8** (production)
- **Laravel Sanctum** autentikáció
- **Queue** alapú feladatkezelés
- **PHPUnit** tesztek

### Frontend
- **React 18** + **TypeScript 5**
- **Vite** build eszköz
- **TailwindCSS** + **shadcn/ui** komponensek
- **React Query** (TanStack Query) adatkezelés
- **React Hook Form** + **Zod** validáció
- **FullCalendar** naptár komponens
- **i18next** többnyelvűség

### Infrastruktúra
- **Docker** + **Docker Compose** támogatás
- **Nginx** webszerver
- **Redis** cache és queue (opcionális)

## Telepítés

### Előfeltételek

- PHP 8.3+
- Composer 2.x
- Node.js 18+ és npm
- SQLite vagy MySQL 8

### Gyors Telepítés (Fejlesztéshez)

\`\`\`bash
# 1. Repository klónozása
git clone https://github.com/daszilagyi/functionalfit.git
cd functionalfit

# 2. Backend beállítása
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
cd ..

# 3. Frontend beállítása
cd frontend
npm install
cp .env.example .env
cd ..

# 4. Alkalmazás indítása (két terminálban)
# Terminal 1 - Backend
cd backend && php artisan serve

# Terminal 2 - Frontend
cd frontend && npm run dev

# 5. Queue worker indítása (opcionális, email küldéshez)
cd backend && php artisan queue:work --queue=notifications
\`\`\`

Az alkalmazás elérhető: http://localhost:3000

### Alapértelmezett belépési adatok

| Szerepkör | Email | Jelszó |
|-----------|-------|--------|
| Admin | admin@functionalfit.hu | password |
| Staff | staff@functionalfit.hu | password |
| Client | client@functionalfit.hu | password |

### Docker Telepítés

\`\`\`bash
# Docker környezet indítása
docker-compose up -d

# Migrációk futtatása
docker-compose exec app php artisan migrate --seed
\`\`\`

### Production Telepítés

#### Környezeti változók beállítása

\`\`\`bash
# Backend (.env)
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=functionalfit
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=465
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="FunctionalFit"

# Frontend (.env)
VITE_API_URL=https://your-domain.com/api
\`\`\`

## Projekt Struktúra

\`\`\`
functionalfit/
├── backend/                    # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/  # API végpontok
│   │   ├── Models/            # Eloquent modellek
│   │   ├── Services/          # Üzleti logika
│   │   └── Jobs/              # Queue jobok
│   ├── database/
│   │   ├── migrations/        # Adatbázis migrációk
│   │   └── seeders/           # Teszt adatok
│   └── routes/api.php         # API útvonalak
│
├── frontend/                   # React SPA
│   ├── src/
│   │   ├── api/               # API hívások
│   │   ├── components/        # React komponensek
│   │   ├── pages/             # Oldal komponensek
│   │   └── hooks/             # Custom React hooks
│   └── public/locales/        # Fordítások
│
├── docs/                       # Dokumentáció
└── infra/                      # Infrastruktúra fájlok
\`\`\`

## API Dokumentáció

Az API végpontok a \`/api/v1/\` prefix alatt érhetők el.

### Főbb végpontok

| Metódus | Végpont | Leírás |
|---------|---------|--------|
| POST | /auth/login | Bejelentkezés |
| POST | /auth/register | Regisztráció |
| GET | /classes/occurrences | Óra előfordulások |
| POST | /bookings | Foglalás létrehozása |
| GET | /admin/users | Felhasználók (admin) |
| GET | /admin/reports/* | Riportok (admin) |

## Fejlesztés

### Tesztek futtatása

\`\`\`bash
# Backend tesztek
cd backend
php artisan test

# Frontend tesztek
cd frontend
npm run test
\`\`\`

### Kód formázás

\`\`\`bash
# Backend (Laravel Pint)
cd backend
./vendor/bin/pint

# Frontend (ESLint + Prettier)
cd frontend
npm run lint
\`\`\`

## Licensz

MIT License

## Támogatás

- **Issues**: [GitHub Issues](https://github.com/daszilagyi/functionalfit/issues)
- **Email**: daniel.szilagyi@egeszsegkozpont-buda.hu

---

Készítette: Daniel Szilagyi | 2024-2025
