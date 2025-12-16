# FunctionalFit Calendar - Telepitesi Utmutato

Ez a dokumentum reszletesen leirja, hogyan telepitheted a FunctionalFit Calendar rendszert egy kulso szerverre.

## Rendszerkovetelmenyek

### Minimum kovetelmenyek
- CPU: 1 vCPU
- RAM: 2 GB
- Tarhely: 10 GB SSD
- OS: Ubuntu 22.04 LTS / Debian 12

### Szoftver kovetelmenyek

**Backend:**
- PHP 8.3+
- Composer 2.x
- PHP extensions: mbstring, xml, curl, mysql, zip, bcmath, gd

**Frontend:**
- Node.js 18+ (LTS)
- npm 9+

**Adatbazis:**
- MySQL 8.0+ vagy MariaDB 10.6+

**Webszerver:**
- Nginx 1.18+ (ajanlott) vagy Apache 2.4+

---

## Gyors Telepites (Ubuntu/Debian)

### 1. Rendszer elokeszitese

```bash
# Rendszer frissitese
sudo apt update && sudo apt upgrade -y

# PHP 8.3 telepitese
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring \
    php8.3-curl php8.3-xml php8.3-bcmath

# Composer telepitese
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 18 telepitese
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Nginx es MySQL telepitese
sudo apt install -y nginx mysql-server
```

### 2. Adatbazis letrehozasa

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE functionalfit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'functionalfit'@'localhost' IDENTIFIED BY 'EROS_JELSZO';
GRANT ALL PRIVILEGES ON functionalfit.* TO 'functionalfit'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Alkalmazas telepitese

```bash
# Projekt klonozasa
sudo mkdir -p /var/www/functionalfit
cd /var/www/functionalfit
sudo git clone https://github.com/daszilagyi/functionalfit.git .
sudo chown -R $USER:www-data .

# Backend telepites
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate

# .env szerkesztese - allitsd be az adatbazis adatokat!
nano .env

# Adatbazis inicializalas
php artisan migrate --force
php artisan db:seed --force

# Optimalizalas
php artisan config:cache
php artisan route:cache
php artisan storage:link

# Jogosultsagok
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 4. Frontend build

```bash
cd /var/www/functionalfit/frontend
cp .env.example .env
# Szerkeszd a .env-t: VITE_API_URL=https://your-domain.com/api
nano .env

npm ci
npm run build
```

### 5. Nginx konfiguracio

```bash
sudo nano /etc/nginx/sites-available/functionalfit
```

Tartalom:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/functionalfit/frontend/dist;
    index index.html;

    # Frontend SPA
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API proxy
    location /api {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Vagy PHP-FPM-mel:
    # location ~ ^/api(.*)$ {
    #     root /var/www/functionalfit/backend/public;
    #     fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    #     fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    #     include fastcgi_params;
    # }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/functionalfit /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 6. SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

### 7. Queue Worker beallitasa

```bash
sudo apt install -y supervisor
sudo nano /etc/supervisor/conf.d/functionalfit.conf
```

Tartalom:
```ini
[program:functionalfit-worker]
command=php /var/www/functionalfit/backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/functionalfit/backend/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start functionalfit-worker:*
```

### 8. Cron Scheduler

```bash
sudo crontab -e
```

Add hozza:
```
* * * * * cd /var/www/functionalfit/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## Kornyezeti Valtozok

### Backend (.env)

```env
APP_NAME="FunctionalFit Calendar"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=functionalfit
DB_USERNAME=functionalfit
DB_PASSWORD=EROS_JELSZO

MAIL_MAILER=smtp
MAIL_HOST=mail.your-domain.com
MAIL_PORT=465
MAIL_USERNAME=noreply@your-domain.com
MAIL_PASSWORD=email_jelszo
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="FunctionalFit"

QUEUE_CONNECTION=database
```

### Frontend (.env)

```env
VITE_API_URL=https://your-domain.com/api
VITE_APP_NAME="FunctionalFit Calendar"
```

---

## Alapertelmezett Belepesi Adatok

| Szerepkor | Email | Jelszo |
|-----------|-------|--------|
| Admin | admin@functionalfit.hu | password |
| Staff | staff@functionalfit.hu | password |
| Client | client@functionalfit.hu | password |

**FONTOS:** Production kornyezetben valtoztasd meg ezeket a jelszavakat!

---

## Karbantartas

### Frissites

```bash
cd /var/www/functionalfit
git pull origin main

cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache

cd ../frontend
npm ci
npm run build

sudo supervisorctl restart functionalfit-worker:*
```

### Backup

```bash
# Adatbazis backup
mysqldump -u functionalfit -p functionalfit > backup_$(date +%Y%m%d).sql

# Visszaallitas
mysql -u functionalfit -p functionalfit < backup_file.sql
```

### Logok

```bash
# Laravel log
tail -f /var/www/functionalfit/backend/storage/logs/laravel.log

# Nginx log
tail -f /var/log/nginx/error.log
```

---

## Tamogatas

- GitHub: https://github.com/daszilagyi/functionalfit/issues
- Email: daniel.szilagyi@egeszsegkozpont-buda.hu
