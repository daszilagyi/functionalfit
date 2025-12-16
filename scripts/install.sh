#!/bin/bash

# FunctionalFit Calendar - Automated Installation Script
# Ubuntu/Debian Server
# Version: 0.1.0-beta

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}FunctionalFit Calendar - Installation${NC}"
echo -e "${GREEN}========================================${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root (sudo ./install.sh)${NC}"
    exit 1
fi

# Configuration variables
INSTALL_DIR="/var/www/functionalfit"
DOMAIN=""
DB_NAME="functionalfit"
DB_USER="functionalfit"
DB_PASS=""
GITHUB_REPO="https://github.com/daszilagyi/functionalfit.git"

# Get user input
read -p "Enter your domain name (e.g., calendar.example.com): " DOMAIN
read -p "Enter database password: " -s DB_PASS
echo ""

if [ -z "$DOMAIN" ] || [ -z "$DB_PASS" ]; then
    echo -e "${RED}Domain and database password are required!${NC}"
    exit 1
fi

echo -e "${YELLOW}Installing system dependencies...${NC}"

# Update system
apt update && apt upgrade -y

# Add PHP repository
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP 8.3 and extensions
apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-common \
    php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring \
    php8.3-curl php8.3-xml php8.3-bcmath

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Install Nginx, MySQL, Supervisor, Git
apt install -y nginx mysql-server supervisor git certbot python3-certbot-nginx

echo -e "${GREEN}System dependencies installed!${NC}"

echo -e "${YELLOW}Setting up database...${NC}"

# Setup MySQL
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}Database created!${NC}"

echo -e "${YELLOW}Cloning repository...${NC}"

# Create install directory
mkdir -p ${INSTALL_DIR}
cd ${INSTALL_DIR}

# Clone repository
git clone ${GITHUB_REPO} .

# Set ownership
chown -R www-data:www-data ${INSTALL_DIR}

echo -e "${GREEN}Repository cloned!${NC}"

echo -e "${YELLOW}Setting up backend...${NC}"

cd ${INSTALL_DIR}/backend

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Create .env file
cp .env.example .env

# Generate app key
php artisan key:generate

# Update .env with database credentials
sed -i "s/DB_CONNECTION=sqlite/DB_CONNECTION=mysql/" .env
sed -i "s/DB_HOST=127.0.0.1/DB_HOST=127.0.0.1/" .env
sed -i "s/DB_PORT=3306/DB_PORT=3306/" .env
sed -i "s/DB_DATABASE=laravel/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_USERNAME=root/DB_USERNAME=${DB_USER}/" .env
sed -i "s/DB_PASSWORD=/DB_PASSWORD=${DB_PASS}/" .env
sed -i "s|APP_URL=http://localhost|APP_URL=https://${DOMAIN}|" .env
sed -i "s/APP_ENV=local/APP_ENV=production/" .env
sed -i "s/APP_DEBUG=true/APP_DEBUG=false/" .env

# Run migrations and seeders
php artisan migrate --force
php artisan db:seed --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan storage:link

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo -e "${GREEN}Backend configured!${NC}"

echo -e "${YELLOW}Setting up frontend...${NC}"

cd ${INSTALL_DIR}/frontend

# Create .env file
cp .env.example .env

# Update API URL
sed -i "s|VITE_API_URL=.*|VITE_API_URL=https://${DOMAIN}/api|" .env

# Install dependencies and build
npm ci
npm run build

echo -e "${GREEN}Frontend built!${NC}"

echo -e "${YELLOW}Configuring Nginx...${NC}"

# Create Nginx config
cat > /etc/nginx/sites-available/functionalfit << NGINXEOF
server {
    listen 80;
    server_name ${DOMAIN};
    root ${INSTALL_DIR}/frontend/dist;
    index index.html;

    # Frontend SPA
    location / {
        try_files \$uri \$uri/ /index.html;
    }

    # API proxy to PHP-FPM
    location /api {
        alias ${INSTALL_DIR}/backend/public;
        try_files \$uri \$uri/ @api;

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
            fastcgi_param SCRIPT_FILENAME ${INSTALL_DIR}/backend/public/index.php;
            include fastcgi_params;
        }
    }

    location @api {
        rewrite ^/api/(.*)$ /api/index.php?/$1 last;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
NGINXEOF

# Enable site
ln -sf /etc/nginx/sites-available/functionalfit /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Test and reload Nginx
nginx -t
systemctl reload nginx

echo -e "${GREEN}Nginx configured!${NC}"

echo -e "${YELLOW}Setting up SSL with Let's Encrypt...${NC}"

# Get SSL certificate
certbot --nginx -d ${DOMAIN} --non-interactive --agree-tos --email admin@${DOMAIN} || {
    echo -e "${YELLOW}SSL setup skipped. Run 'certbot --nginx -d ${DOMAIN}' manually.${NC}"
}

echo -e "${YELLOW}Setting up queue worker...${NC}"

# Create Supervisor config
cat > /etc/supervisor/conf.d/functionalfit.conf << SUPEOF
[program:functionalfit-worker]
command=php ${INSTALL_DIR}/backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=${INSTALL_DIR}/backend/storage/logs/worker.log
SUPEOF

# Reload Supervisor
supervisorctl reread
supervisorctl update
supervisorctl start functionalfit-worker:*

echo -e "${GREEN}Queue worker configured!${NC}"

echo -e "${YELLOW}Setting up cron scheduler...${NC}"

# Add cron job
(crontab -l 2>/dev/null; echo "* * * * * cd ${INSTALL_DIR}/backend && php artisan schedule:run >> /dev/null 2>&1") | crontab -

echo -e "${GREEN}Cron scheduler configured!${NC}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Installation Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Your FunctionalFit Calendar is now available at: ${GREEN}https://${DOMAIN}${NC}"
echo ""
echo -e "Default login credentials:"
echo -e "  Admin: ${YELLOW}admin@functionalfit.hu${NC} / ${YELLOW}password${NC}"
echo -e "  Staff: ${YELLOW}staff@functionalfit.hu${NC} / ${YELLOW}password${NC}"
echo -e "  Client: ${YELLOW}client@functionalfit.hu${NC} / ${YELLOW}password${NC}"
echo ""
echo -e "${RED}IMPORTANT: Change these passwords immediately!${NC}"
echo ""
echo -e "Useful commands:"
echo -e "  - View logs: tail -f ${INSTALL_DIR}/backend/storage/logs/laravel.log"
echo -e "  - Restart queue: supervisorctl restart functionalfit-worker:*"
echo -e "  - Clear cache: cd ${INSTALL_DIR}/backend && php artisan cache:clear"
echo ""
