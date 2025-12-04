# FunctionalFit Calendar - Booking & Calendar Management System

A comprehensive booking and calendar management system for fitness centers and wellness facilities, serving clients, staff, and administrators.

## Tech Stack

- **Frontend**: React 18 + Vite + TypeScript, Tailwind CSS, shadcn/ui, React Query
- **Backend**: Laravel 11 (PHP 8.3), MySQL 8.0, Redis 7
- **Infrastructure**: Docker Compose, Nginx, PHP-FPM, Queue Workers, Scheduler
- **Integrations**: Google Calendar API, WooCommerce/Stripe webhooks, SMTP notifications

## Quick Start with Docker

### Prerequisites

- Docker Engine 24.0+ and Docker Compose 2.20+
- Git
- 4GB RAM minimum (8GB recommended)
- 10GB disk space

### Initial Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd functionalfit_calendar_project
   ```

2. **Configure environment variables**
   ```bash
   # Root environment (Docker Compose)
   cp .env.example .env

   # Backend environment (Laravel)
   cp backend/.env.example backend/.env

   # Frontend environment (React/Vite)
   cp frontend/.env.example frontend/.env.local
   ```

3. **Edit .env files**
   - Update database passwords in root `.env`
   - Generate Laravel app key: `APP_KEY=base64:...` (see step 5)
   - Configure mail settings (use Mailtrap for development)
   - Add Google Calendar credentials (optional for initial setup)
   - Add Stripe/WooCommerce credentials (optional for initial setup)

4. **Build and start all services**
   ```bash
   docker compose up -d
   ```

   This will start:
   - **nginx** on port 8080 (web server)
   - **php-fpm** (Laravel application)
   - **mysql** on port 3306 (database)
   - **redis** on port 6379 (cache & queue)
   - **queue-worker** (background job processing)
   - **scheduler** (cron jobs)

5. **Generate Laravel application key**
   ```bash
   docker compose exec php-fpm php artisan key:generate
   ```

6. **Run database migrations and seeders**
   ```bash
   docker compose exec php-fpm php artisan migrate --seed
   ```

7. **Install frontend dependencies (if not using dev profile)**
   ```bash
   cd frontend
   npm install
   npm run build
   cd ..
   ```

8. **Access the application**
   - **Frontend**: http://localhost:8080
   - **API**: http://localhost:8080/api
   - **Health Check**: http://localhost:8080/api/health

### Development Mode (with Hot Reload)

To enable frontend hot-reload during development:

```bash
# Start all services including frontend dev server
docker compose --profile dev up -d

# Frontend dev server will be available at:
# http://localhost:5173
```

## Docker Commands

### Service Management

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Restart a specific service
docker compose restart php-fpm

# View service logs
docker compose logs -f

# View logs for specific service
docker compose logs -f php-fpm
docker compose logs -f nginx
docker compose logs -f queue-worker

# Check service status
docker compose ps
```

### Database Operations

```bash
# Run migrations
docker compose exec php-fpm php artisan migrate

# Run migrations with seeders
docker compose exec php-fpm php artisan migrate --seed

# Rollback last migration
docker compose exec php-fpm php artisan migrate:rollback

# Reset database (WARNING: destroys all data)
docker compose exec php-fpm php artisan migrate:fresh --seed

# Access MySQL CLI
docker compose exec mysql mysql -u functionalfit -p functionalfit_db

# Create database backup
docker compose exec mysql mysqldump -u functionalfit -p functionalfit_db > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore database from backup
docker compose exec -T mysql mysql -u functionalfit -p functionalfit_db < backup.sql
```

### Laravel Artisan Commands

```bash
# Run any artisan command
docker compose exec php-fpm php artisan <command>

# Clear all caches
docker compose exec php-fpm php artisan cache:clear
docker compose exec php-fpm php artisan config:clear
docker compose exec php-fpm php artisan route:clear
docker compose exec php-fpm php artisan view:clear

# Optimize for production
docker compose exec php-fpm php artisan config:cache
docker compose exec php-fpm php artisan route:cache
docker compose exec php-fpm php artisan view:cache

# Create new migration
docker compose exec php-fpm php artisan make:migration create_events_table

# Create new controller
docker compose exec php-fpm php artisan make:controller EventController --api

# Create new model
docker compose exec php-fpm php artisan make:model Event -m

# Run tests
docker compose exec php-fpm php artisan test
docker compose exec php-fpm php artisan test --coverage
```

### Queue Management

```bash
# View queue status
docker compose exec php-fpm php artisan queue:monitor

# Restart queue workers (after code changes)
docker compose restart queue-worker

# Clear failed jobs
docker compose exec php-fpm php artisan queue:flush

# Retry failed jobs
docker compose exec php-fpm php artisan queue:retry all

# Process jobs manually (one-time)
docker compose exec php-fpm php artisan queue:work --once
```

### Redis Operations

```bash
# Access Redis CLI
docker compose exec redis redis-cli -a functionalfit_redis_secret_2024

# View all keys
docker compose exec redis redis-cli -a functionalfit_redis_secret_2024 KEYS '*'

# Flush all Redis data (WARNING: clears cache and queues)
docker compose exec redis redis-cli -a functionalfit_redis_secret_2024 FLUSHALL

# Check Redis memory usage
docker compose exec redis redis-cli -a functionalfit_redis_secret_2024 INFO memory
```

### Container Access

```bash
# Access PHP-FPM container shell
docker compose exec php-fpm sh

# Access Nginx container shell
docker compose exec nginx sh

# Access MySQL container shell
docker compose exec mysql bash

# Run composer commands
docker compose exec php-fpm composer install
docker compose exec php-fpm composer update
docker compose exec php-fpm composer require <package>
```

### Troubleshooting

```bash
# View real-time logs for all services
docker compose logs -f

# View specific service logs with timestamps
docker compose logs -f --timestamps php-fpm

# Check container resource usage
docker stats

# Rebuild containers after Dockerfile changes
docker compose build --no-cache
docker compose up -d --force-recreate

# Remove all containers and volumes (DESTRUCTIVE)
docker compose down -v

# Check health status of services
docker compose ps
docker inspect functionalfit_php | grep -A 10 Health
docker inspect functionalfit_mysql | grep -A 10 Health
```

## Health Checks

The application includes comprehensive health checks:

- **Overall Health**: http://localhost:8080/api/health
  - Checks database, Redis, cache, and storage
  - Returns JSON with service status

- **Simple Ping**: http://localhost:8080/api/ping
  - Quick availability check

- **Version Info**: http://localhost:8080/api/version
  - Application and framework versions

## Project Structure

```
functionalfit_calendar_project/
├── backend/                 # Laravel 11 backend
│   ├── app/                # Application code
│   ├── config/             # Configuration files
│   ├── database/           # Migrations and seeders
│   ├── routes/             # API routes
│   └── tests/              # Backend tests
├── frontend/               # React + Vite frontend
│   ├── src/                # Source code
│   ├── public/             # Static assets
│   └── dist/               # Built assets (generated)
├── infra/                  # Docker infrastructure
│   ├── docker-compose.yml  # Main compose file
│   ├── docker/             # Dockerfiles and configs
│   │   ├── php/            # PHP-FPM container
│   │   ├── frontend/       # Frontend container
│   │   ├── mysql/          # MySQL configuration
│   │   ├── redis/          # Redis configuration
│   │   └── scheduler/      # Scheduler cron config
│   ├── nginx/              # Nginx configuration
│   └── logs/               # Application logs
├── docs/                   # Documentation
└── .env.example            # Docker Compose environment
```

## Services and Ports

| Service | Internal Port | External Port | Purpose |
|---------|--------------|---------------|---------|
| Nginx | 80 | 8080 | Reverse proxy & static files |
| PHP-FPM | 9000 | - | Laravel application |
| MySQL | 3306 | 3306 | Database |
| Redis | 6379 | 6379 | Cache & queue backend |
| Frontend Dev | 5173 | 5173 | Vite dev server (dev profile) |

## Networks

- **frontend-network**: Nginx ↔ Frontend dev server
- **backend-network**: Nginx ↔ PHP-FPM ↔ Queue workers
- **data-network**: Application services ↔ MySQL & Redis

## Volumes

- **mysql-data**: Persistent MySQL database storage
- **redis-data**: Persistent Redis data (AOF)

## Environment Configuration

### Required Environment Variables

**Database**:
- `DB_DATABASE`: Database name (default: functionalfit_db)
- `DB_USERNAME`: Database user (default: functionalfit)
- `DB_PASSWORD`: Database password (CHANGE THIS)
- `DB_ROOT_PASSWORD`: MySQL root password (CHANGE THIS)

**Redis**:
- `REDIS_PASSWORD`: Redis password (CHANGE THIS)

**Application**:
- `APP_KEY`: Laravel encryption key (generate with `artisan key:generate`)
- `APP_URL`: Application URL (default: http://localhost:8080)

### Optional Environment Variables

**Mail** (for notifications):
- `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`

**Google Calendar**:
- `GOOGLE_CALENDAR_CLIENT_ID`, `GOOGLE_CALENDAR_CLIENT_SECRET`

**Payment Integrations**:
- `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`
- `WOOCOMMERCE_WEBHOOK_SECRET`, `WOOCOMMERCE_API_URL`

## Development Workflow

1. **Make code changes** in `backend/` or `frontend/`
2. **Backend changes**: Automatically picked up by PHP-FPM (no restart needed)
3. **Frontend changes**:
   - With dev profile: Hot-reload via Vite
   - Without dev profile: Rebuild with `npm run build`
4. **Database changes**: Run `docker compose exec php-fpm php artisan migrate`
5. **Queue jobs**: Restart with `docker compose restart queue-worker`

## Testing

```bash
# Run backend tests
docker compose exec php-fpm php artisan test

# Run with coverage
docker compose exec php-fpm php artisan test --coverage

# Run specific test file
docker compose exec php-fpm php artisan test tests/Feature/EventTest.php

# Run frontend tests (when implemented)
cd frontend
npm run test
```

## Production Deployment

For production deployment:

1. **Set environment to production**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   ```

2. **Use production Dockerfile stages**
   - Build stage: `--target production`

3. **Configure SSL certificates**
   - Place certificates in `infra/nginx/ssl/`
   - Update nginx configuration for HTTPS

4. **Set secure passwords**
   - Generate strong passwords for DB, Redis
   - Use secure secret keys

5. **Enable caching**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

6. **Scale queue workers**
   ```bash
   docker compose up -d --scale queue-worker=3
   ```

## Support & Documentation

- **Project Documentation**: `/docs/spec.md`
- **OpenMemory Guide**: `/openmemory.md`
- **API Documentation**: http://localhost:8080/api/docs (when implemented)

## License

Proprietary - All rights reserved
