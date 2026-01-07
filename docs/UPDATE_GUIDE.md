# FunctionalFit Calendar - Frissítési Útmutató

Ez az útmutató leírja, hogyan frissítheted a FunctionalFit Calendar alkalmazást egy már működő éles környezeten.

---

## Előkészületek

### 1. Mentés készítése (KÖTELEZŐ!)

**Adatbázis mentés:**
```bash
# SSH-val a szerveren
mysqldump -u DB_USER -p DB_NAME > backup_$(date +%Y%m%d_%H%M%S).sql

# Vagy cPanel phpMyAdmin-ban: Export → Quick → Go
```

**Fájlok mentése (opcionális, de ajánlott):**
```bash
# .env fájl mentése
cp /var/www/functionalfit/backend/.env ~/backup_env_$(date +%Y%m%d).env
```

### 2. Karbantartási mód bekapcsolása (opcionális)

```bash
cd /var/www/functionalfit/backend
php artisan down --message="Frissítés folyamatban, kérlek várj néhány percet..."
```

---

## Frissítési Lépések

### A) VPS/Dedicated szerver (SSH hozzáféréssel)

#### 1. Új verzió letöltése

```bash
cd /var/www/functionalfit

# Ha git-tel telepítetted:
git fetch origin
git pull origin main

# Vagy ha zip-ből:
# Töltsd le az új release-t és csomagold ki
```

#### 2. Backend frissítése

```bash
cd /var/www/functionalfit/backend

# Composer függőségek frissítése
composer install --no-dev --optimize-autoloader

# Adatbázis migrációk futtatása
php artisan migrate --force

# Cache újraépítése
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# Jogosultságok ellenőrzése
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

#### 3. Frontend frissítése

```bash
cd /var/www/functionalfit/frontend

# NPM függőségek frissítése
npm ci

# Production build készítése
npm run build
```

#### 4. Queue worker újraindítása (ha van)

```bash
sudo supervisorctl restart functionalfit-worker:*

# Vagy ha nincs supervisor:
# Lépj ki és indítsd újra a queue:work parancsot
```

#### 5. Karbantartási mód kikapcsolása

```bash
cd /var/www/functionalfit/backend
php artisan up
```

---

### B) cPanel/Shared hosting (SSH nélkül)

#### 1. Fájlok előkészítése lokálisan

```bash
# Saját gépen
cd functionalfit_calendar_project

# Backend - composer install
cd backend
composer install --no-dev --optimize-autoloader

# Frontend - build
cd ../frontend
npm ci
npm run build
```

#### 2. Fájlok feltöltése

**File Manager vagy FTP használatával:**

1. **Backend fájlok** → `/public_html/backend/`
   - Töltsd fel a teljes `backend` mappát (kivéve `.env` - azt NE írd felül!)
   - Figyelj, hogy a `vendor` mappa is feltöltésre kerüljön

2. **Frontend fájlok** → `/public_html/`
   - Töltsd fel a `frontend/dist` mappa tartalmát
   - `index.html`, `assets/` mappa, stb.

3. **API entry point** → `/public_html/api/`
   - Töltsd fel a `backend/public` mappa tartalmát
   - Ügyelj az `index.php` megfelelő módosítására (lásd CPANEL_DEPLOYMENT.md)

#### 3. Adatbázis migrációk futtatása

**Ha van SSH/Terminal hozzáférés:**
```bash
cd ~/public_html/backend
php artisan migrate --force
```

**Ha nincs SSH:**

1. Nyisd meg a phpMyAdmin-t
2. Válaszd ki az adatbázist
3. Kattints az "SQL" fülre
4. Futtasd a szükséges migrációs SQL-eket (lásd: Manuális migráció szekció)

#### 4. Cache ürítése

**Ha van SSH:**
```bash
cd ~/public_html/backend
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

**Ha nincs SSH:**
- Töröld a `backend/bootstrap/cache/config.php` fájlt
- Töröld a `backend/storage/framework/cache/*` tartalmát
- Töröld a `backend/storage/framework/views/*` tartalmát

---

## Manuális Migráció (phpMyAdmin)

Ha nincs SSH hozzáférésed, az új táblákat/oszlopokat manuálisan kell létrehoznod.

### Migrációk ellenőrzése

Nézd meg a `backend/database/migrations/` mappában az új migráció fájlokat.

Példa: Ha van egy új `2025_01_06_create_example_table.php` fájl:

```sql
-- Ellenőrizd, hogy létezik-e már a tábla
SHOW TABLES LIKE 'example_table';

-- Ha nem létezik, hozd létre (a migráció alapján):
CREATE TABLE `example_table` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Frissítsd a migrations táblát
INSERT INTO `migrations` (`migration`, `batch`)
VALUES ('2025_01_06_create_example_table', (SELECT MAX(batch) + 1 FROM migrations m));
```

---

## Verzióellenőrzés

### Aktuális verzió megtekintése

```bash
# Ha git-tel van telepítve
git describe --tags --always

# Vagy nézd meg a CHANGELOG.md fájlt
```

### API verzió ellenőrzése

Böngészőben: `https://te-domain.hu/api/health`

---

## Hibaelhárítás

### 500-as hiba a frissítés után

1. **Ellenőrizd a logot:**
   ```bash
   tail -50 /var/www/functionalfit/backend/storage/logs/laravel.log
   ```

2. **Cache törlése:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

3. **Jogosultságok:**
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   ```

### "Class not found" hiba

```bash
composer dump-autoload
```

### Migráció hiba

```bash
# Migráció állapot ellenőrzése
php artisan migrate:status

# Egy adott migráció újrafuttatása
php artisan migrate:refresh --path=/database/migrations/2025_01_06_xxxxx.php
```

### Frontend nem frissül (cache)

1. Böngésző cache törlése (Ctrl+Shift+R)
2. Ellenőrizd, hogy az `assets` mappa új hash-ekkel lett feltöltve

---

## Gyors Frissítési Szkript (VPS)

Mentsd el ezt a szkriptet: `/var/www/functionalfit/update.sh`

```bash
#!/bin/bash
set -e

echo "🔄 FunctionalFit frissítés indítása..."

cd /var/www/functionalfit

# Karbantartási mód
echo "⏸️  Karbantartási mód bekapcsolása..."
cd backend && php artisan down

# Git pull
echo "📥 Új verzió letöltése..."
cd /var/www/functionalfit
git pull origin main

# Backend
echo "🔧 Backend frissítése..."
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
echo "🎨 Frontend buildelése..."
cd ../frontend
npm ci
npm run build

# Queue worker
echo "🔄 Queue worker újraindítása..."
sudo supervisorctl restart functionalfit-worker:* 2>/dev/null || true

# Karbantartási mód vége
echo "✅ Frissítés kész, alkalmazás indítása..."
cd ../backend
php artisan up

echo "🎉 Frissítés sikeresen befejeződött!"
```

Használat:
```bash
chmod +x /var/www/functionalfit/update.sh
./update.sh
```

---

## Visszaállítás (Rollback)

Ha a frissítés után probléma van:

### Adatbázis visszaállítása

```bash
mysql -u DB_USER -p DB_NAME < backup_XXXXXXXX.sql
```

### Fájlok visszaállítása (git)

```bash
git log --oneline -5  # Előző commit hash megkeresése
git checkout <commit_hash>
```

---

## Ellenőrzőlista

Frissítés előtt:
- [ ] Adatbázis mentés készült
- [ ] .env fájl mentve
- [ ] Felhasználók értesítve a karbantartásról

Frissítés után:
- [ ] Bejelentkezés működik
- [ ] Naptár betöltődik
- [ ] Esemény létrehozás működik
- [ ] API válaszol (`/api/health`)
- [ ] Nincs hibaüzenet a logban

---

## Támogatás

Ha elakadtál:
- Email: daniel.szilagyi@egeszsegkozpont-buda.hu
- GitHub Issues: https://github.com/daszilagyi/functionalfit/issues
