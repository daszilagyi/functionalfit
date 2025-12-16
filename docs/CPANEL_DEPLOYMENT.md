# FunctionalFit Calendar - cPanel Telepítési Útmutató

Ez az útmutató leírja, hogyan telepítheted a FunctionalFit Calendar alkalmazást egy cPanel tárhelyre.

## Előkészületek (Saját gépen)

### 1. Backend előkészítése

```bash
cd backend

# Composer függőségek telepítése
composer install --no-dev --optimize-autoloader

# .env fájl létrehozása
cp .env.example .env

# Alkalmazás kulcs generálása
php artisan key:generate
```

### 2. Frontend buildelése

```bash
cd frontend

# .env beállítása - FONTOS: írd át a domain-edre!
# Szerkeszd a .env fájlt:
# VITE_API_URL=https://te-domain.hu/api

npm install
npm run build
```

---

## cPanel Beállítások

### 1. Adatbázis létrehozása

1. Lépj be a **cPanel**-be
2. Keresd meg: **MySQL Databases** vagy **MySQL adatbázisok**
3. Hozz létre egy új adatbázist:
   - Adatbázis neve: `functionalfit` (a cPanel hozzáadja az előtagot, pl. `username_functionalfit`)
4. Hozz létre egy felhasználót:
   - Felhasználónév: `ffuser`
   - Jelszó: **erős jelszó** (jegyezd fel!)
5. Add hozzá a felhasználót az adatbázishoz:
   - Válaszd ki: **ALL PRIVILEGES**

**Jegyezd fel:**
- Adatbázis neve: `username_functionalfit`
- Felhasználó: `username_ffuser`
- Jelszó: `amit_megadtál`

### 2. Fájlok feltöltése

#### A) File Manager használatával

1. Nyisd meg a **File Manager**-t a cPanel-ben
2. Navigálj a `public_html` mappába (vagy a domain almappájába)

**Mappastruktúra létrehozása:**

```
public_html/
├── api/                    ← Backend public mappa tartalma
│   ├── index.php
│   ├── .htaccess
│   └── ...
├── backend/                ← Backend (api mappán kívül)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── .env
│   └── ...
├── index.html              ← Frontend dist tartalma
├── assets/
└── ...
```

#### B) Lépésről lépésre

**1. Backend feltöltése:**
- Hozz létre egy `backend` mappát a `public_html`-ben
- Töltsd fel a `backend` mappa **teljes tartalmát** (kivéve a `public` mappát)
- A `backend/public` mappa tartalmát töltsd fel az `api` mappába

**2. Frontend feltöltése:**
- A `frontend/dist` mappa tartalmát töltsd fel közvetlenül a `public_html`-be

### 3. Backend index.php módosítása

Szerkeszd az `api/index.php` fájlt:

```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Útvonal módosítása - a backend egy szinttel feljebb van
require __DIR__.'/../backend/vendor/autoload.php';

$app = require_once __DIR__.'/../backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
```

### 4. .htaccess fájlok

**public_html/.htaccess** (Frontend routing):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # API kérések átirányítása
    RewriteRule ^api/(.*)$ api/index.php [L]

    # Frontend SPA routing
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} !^/api
    RewriteRule ^(.*)$ index.html [L]
</IfModule>
```

**api/.htaccess** (Laravel API):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /api/

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [L]
</IfModule>

# PHP beállítások (ha szükséges)
<IfModule mod_php.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 300
</IfModule>
```

### 5. Backend .env konfigurálása

Szerkeszd a `backend/.env` fájlt:

```env
APP_NAME="FunctionalFit Calendar"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://te-domain.hu

# Adatbázis - a cPanel-ben létrehozott adatokkal
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=username_functionalfit
DB_USERNAME=username_ffuser
DB_PASSWORD=az_adatbazis_jelszavad

# FONTOS: cPanel-en nincs queue worker, használj sync-et
QUEUE_CONNECTION=sync

# Session és cache
SESSION_DRIVER=file
CACHE_DRIVER=file

# Email beállítások (opcionális)
MAIL_MAILER=smtp
MAIL_HOST=mail.te-domain.hu
MAIL_PORT=465
MAIL_USERNAME=noreply@te-domain.hu
MAIL_PASSWORD=email_jelszo
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@te-domain.hu
MAIL_FROM_NAME="FunctionalFit"

# Timezone
APP_TIMEZONE=Europe/Budapest
```

### 6. Storage mappa jogosultságok

A cPanel File Manager-ben:
1. Navigálj a `backend/storage` mappához
2. Jobb klikk → **Change Permissions**
3. Állítsd be: **755** vagy **775**
4. Pipáld be: "Recurse into subdirectories"

Ugyanezt csináld a `backend/bootstrap/cache` mappával is.

### 7. Adatbázis táblák létrehozása

#### A) phpMyAdmin-nal (Ajánlott)

1. cPanel → **phpMyAdmin**
2. Válaszd ki az adatbázist (bal oldalt)
3. Kattints az **Import** fülre
4. Töltsd fel a `database_schema.sql` fájlt (lásd lent)

#### B) SSH-val (ha van hozzáférésed)

```bash
cd ~/public_html/backend
php artisan migrate --force
php artisan db:seed --force
```

---

## Adatbázis SQL Fájl

Ha nincs SSH hozzáférésed, használd ezt az SQL fájlt a phpMyAdmin-ban:

A teljes adatbázis struktúra exportálásához futtasd lokálisan:

```bash
cd backend
php artisan migrate:fresh
php artisan db:seed
```

Majd exportáld phpMyAdmin-ból vagy mysqldump-pal.

---

## Cron Job beállítása

1. cPanel → **Cron Jobs**
2. Add hozzá ezt a sort:

```
* * * * * cd ~/public_html/backend && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Megjegyzés:** A PHP útvonal változhat a tárhelyen. Ellenőrizd:
- `/usr/bin/php`
- `/usr/local/bin/php`
- `/opt/cpanel/ea-php83/root/usr/bin/php`

---

## Ellenőrzés

### 1. Frontend teszt
Nyisd meg a böngészőben: `https://te-domain.hu`
- Látod a bejelentkezési oldalt? ✓

### 2. API teszt
Nyisd meg: `https://te-domain.hu/api/health`
- JSON választ kapsz? ✓

### 3. Bejelentkezés
Próbálj bejelentkezni:
- Email: `admin@functionalfit.hu`
- Jelszó: `password`

---

## Hibaelhárítás

### 500 Internal Server Error

1. Ellenőrizd a `backend/storage/logs/laravel.log` fájlt
2. Ellenőrizd a jogosultságokat (storage, bootstrap/cache)
3. Győződj meg róla, hogy a PHP verzió 8.1+

### "Class not found" hibák

A vendor mappa nincs feltöltve, vagy hiányos. Töltsd fel újra.

### Adatbázis kapcsolódási hiba

1. Ellenőrizd a .env fájlban az adatbázis adatokat
2. A cPanel-ben ellenőrizd, hogy a felhasználó hozzá van-e adva az adatbázishoz

### CORS hibák

Add hozzá a `backend/.env` fájlhoz:
```env
SANCTUM_STATEFUL_DOMAINS=te-domain.hu
SESSION_DOMAIN=.te-domain.hu
```

### Frontend nem töltődik be megfelelően

Ellenőrizd, hogy a `frontend/.env` fájlban jó API URL van-e beállítva a build előtt:
```env
VITE_API_URL=https://te-domain.hu/api
```

---

## Mappastruktúra összefoglaló

```
public_html/
├── api/
│   ├── index.php          ← Módosított Laravel entry point
│   ├── .htaccess          ← API routing
│   └── storage/           ← Symlink (opcionális)
├── backend/
│   ├── app/
│   ├── bootstrap/
│   │   └── cache/         ← 755 jogosultság
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── storage/           ← 755 jogosultság
│   │   ├── app/
│   │   ├── framework/
│   │   └── logs/
│   ├── vendor/            ← Teljes Composer vendor mappa
│   └── .env               ← Production konfiguráció
├── assets/                ← Frontend assets (Vite build)
├── index.html             ← Frontend entry point
└── .htaccess              ← Frontend SPA routing
```

---

## Támogatás

Ha elakadtál, írj a következő címre:
- Email: daniel.szilagyi@egeszsegkozpont-buda.hu
- GitHub Issues: https://github.com/daszilagyi/functionalfit/issues
