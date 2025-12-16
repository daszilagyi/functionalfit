# Docker Infrastructure Documentation

## Overview

The FunctionalFit Calendar application uses a multi-container Docker architecture with production-ready configurations for all services. This document details the infrastructure design, configuration choices, and operational procedures.

## Architecture

### Service Layer Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Frontend Network                         │
│  ┌──────────┐                              ┌──────────────┐ │
│  │  Nginx   │ ←→ (dev only) ←→            │ Frontend Dev │ │
│  │  :80     │                              │    :5173     │ │
│  └────┬─────┘                              └──────────────┘ │
└───────┼──────────────────────────────────────────────────────┘
        │
┌───────┼──────────────────────────────────────────────────────┐
│       │            Backend Network                            │
│  ┌────▼─────┐     ┌──────────┐     ┌──────────────┐         │
│  │  Nginx   │ ←→  │ PHP-FPM  │ ←→  │ Queue Worker │         │
│  └──────────┘     │  :9000   │     └──────────────┘         │
│                   └────┬─────┘     ┌──────────────┐         │
│                        │       ←→  │  Scheduler   │         │
│                        │           └──────────────┘         │
└────────────────────────┼──────────────────────────────────────┘
                         │
┌────────────────────────┼──────────────────────────────────────┐
│                        │         Data Network                  │
│                   ┌────▼─────┐                                │
│                   │ PHP-FPM  │                                │
│                   │  Apps    │                                │
│                   └────┬─────┘                                │
│                        │                                      │
│         ┌──────────────┼──────────────┐                      │
│         ▼              ▼               ▼                      │
│   ┌─────────┐    ┌─────────┐    ┌─────────┐                │
│   │  MySQL  │    │  Redis  │    │ Storage │                │
│   │  :3306  │    │  :6379  │    │ Volumes │                │
│   └─────────┘    └─────────┘    └─────────┘                │
└─────────────────────────────────────────────────────────────┘
```

## Service Configurations

### 1. Nginx (Reverse Proxy & Web Server)

**Image**: `nginx:1.25-alpine`

**Purpose**:
- Reverse proxy for Laravel API
- Serves frontend static files
- SSL/TLS termination (production)
- Request routing and load balancing

**Key Features**:
- FastCGI cache for PHP-FPM responses
- Gzip compression for all text-based content
- Rate limiting (60 req/min API, 5 req/min auth)
- Connection limiting (10 concurrent per IP)
- Security headers (X-Frame-Options, CSP, etc.)
- WebSocket support for real-time features

**Configuration Files**:
- `/infra/nginx/nginx.conf` - Main configuration
- `/infra/nginx/conf.d/functionalfit.conf` - Application-specific routes

**Health Check**: HTTP GET to `/health`

**Resource Limits**: None (proxy should not be constrained)

---

### 2. PHP-FPM (Laravel Application Server)

**Base Image**: `php:8.3-fpm-alpine`

**Purpose**:
- Execute Laravel application code
- Process API requests
- Handle business logic

**Installed Extensions**:
- `pdo`, `pdo_mysql`, `mysqli` - Database connectivity
- `redis` - Redis integration
- `gd` - Image processing
- `zip` - Archive handling
- `intl` - Internationalization
- `opcache` - Performance optimization
- `pcntl` - Process control
- `bcmath` - Precision math
- `exif` - Image metadata

**Configuration**:
- **Process Manager**: Dynamic
- **Max Children**: 20
- **Start Servers**: 5
- **Min Spare**: 3
- **Max Spare**: 10
- **Max Requests**: 1000 (per worker before restart)
- **Memory Limit**: 256MB
- **Max Execution Time**: 300s

**OPcache Settings** (Production):
- Memory: 128MB
- Interned Strings: 16MB
- Max Files: 10,000
- Validate Timestamps: OFF (production)

**Health Check**: Custom script checking PHP-FPM process and fcgi response

**Development Features**:
- Xdebug 3.3.1 for debugging
- Hot-reload via volume mount

---

### 3. MySQL (Database)

**Image**: `mysql:8.0`

**Purpose**:
- Persistent data storage
- Transactional data integrity
- Full-text search

**Configuration**:
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Max Connections**: 200
- **InnoDB Buffer Pool**: 256MB
- **Log File Size**: 64MB
- **Timezone**: UTC

**Optimization**:
- Binary logging enabled for point-in-time recovery
- Slow query log enabled (>2s queries)
- Query cache disabled (MySQL 8.0 default)

**Backup Strategy**:
- Automated mysqldump to `/infra/backups/mysql/`
- Binary logs for incremental backups
- Retention: 7 days

**Health Check**: `mysqladmin ping` with credentials

**Initialization**:
- Creates `functionalfit_db` database
- Creates `functionalfit_test` database for testing
- Sets timezone to UTC

---

### 4. Redis (Cache & Queue Backend)

**Image**: `redis:7-alpine`

**Purpose**:
- Session storage
- Application cache
- Queue backend for jobs
- Real-time data store

**Configuration**:
- **Max Memory**: 256MB
- **Eviction Policy**: allkeys-lru
- **Persistence**: AOF (Append Only File)
- **Fsync**: everysec (balance durability/performance)

**Databases**: 16 (default)
- DB 0: Cache
- DB 1: Sessions
- DB 2: Queue (default)
- DB 3: Queue (notifications)
- DB 4: Queue (gcal-sync)
- DB 5: Queue (webhooks)

**Health Check**: `redis-cli ping`

**Backup**: AOF file in `/data/appendonly.aof`

---

### 5. Queue Worker (Background Jobs)

**Base Image**: Same as PHP-FPM (`php:8.3-fpm-alpine`)

**Purpose**:
- Process background jobs asynchronously
- Send notifications
- Sync with Google Calendar
- Process webhooks

**Configuration**:
- **Tries**: 3 (per job)
- **Max Jobs**: 1000 (before worker restart)
- **Max Time**: 3600s (1 hour per batch)
- **Timeout**: 300s (per job)
- **Memory**: 256MB
- **Sleep**: 3s (between checks)

**Queue Priorities**:
1. `webhooks` - High priority (payment events)
2. `gcal-sync` - Medium priority (calendar updates)
3. `notifications` - Medium priority (user notifications)
4. `default` - Low priority (general tasks)

**Restart Policy**: `unless-stopped`

**Health Check**: Verify `queue:work` process is running

**Scaling**: Can be scaled horizontally
```bash
docker compose up -d --scale queue-worker=3
```

---

### 6. Scheduler (Cron Jobs)

**Base Image**: Same as PHP-FPM (`php:8.3-fpm-alpine`)

**Purpose**:
- Run scheduled Laravel tasks
- Generate recurring class occurrences
- Send reminder notifications
- Calculate staff payouts
- Clean up expired data

**Configuration**:
- Runs Laravel `schedule:run` every minute
- Logs to `/var/www/html/storage/logs/scheduler.log`

**Scheduled Tasks** (configured in Laravel):
- **Every minute**: Check for due scheduled tasks
- **Hourly**: Send reminder notifications
- **Daily**: Generate next week's class occurrences
- **Weekly**: Calculate staff payouts
- **Monthly**: Archive old data

**Restart Policy**: `unless-stopped`

**Health Check**: Verify `crond` process is running

---

### 7. Frontend Dev Server (Development Only)

**Image**: `node:20-alpine`

**Purpose**:
- Hot-reload development server
- Fast refresh for React components
- Development only (not used in production)

**Configuration**:
- **Port**: 5173
- **Host**: 0.0.0.0 (accessible from host)
- **Profile**: `dev` (must be explicitly enabled)

**Usage**:
```bash
docker compose --profile dev up -d
```

## Networking

### Network Isolation Strategy

**frontend-network** (bridge):
- Purpose: Isolate frontend from backend internals
- Services: Nginx, Frontend Dev Server
- Rationale: Frontend should only communicate via reverse proxy

**backend-network** (bridge):
- Purpose: Application layer communication
- Services: Nginx, PHP-FPM, Queue Worker, Scheduler
- Rationale: API services can communicate directly

**data-network** (bridge):
- Purpose: Data layer isolation
- Services: PHP-FPM, Queue Worker, Scheduler, MySQL, Redis
- Rationale: Only application services can access data stores

**Security Benefits**:
- Frontend cannot directly access database
- External clients cannot bypass Nginx
- Data services are isolated from internet

## Volume Management

### Persistent Volumes

**mysql-data** (`functionalfit_mysql_data`):
- Path: `/var/lib/mysql`
- Purpose: MySQL database files
- Backup: Regularly dump to `/infra/backups/mysql/`

**redis-data** (`functionalfit_redis_data`):
- Path: `/data`
- Purpose: Redis AOF and RDB files
- Backup: AOF provides durability

### Development Volumes (Bind Mounts)

**Backend Code**:
- Host: `../backend`
- Container: `/var/www/html`
- Purpose: Hot-reload for PHP code

**Frontend Code** (dev profile):
- Host: `../frontend`
- Container: `/app`
- Purpose: Hot-reload for React code

**Logs**:
- Host: `./logs/*`
- Container: `/var/www/html/storage/logs`
- Purpose: Access logs from host

## Health Checks

All services include health checks for zero-downtime deployments:

| Service | Check Method | Interval | Timeout | Retries | Start Period |
|---------|--------------|----------|---------|---------|--------------|
| Nginx | HTTP GET /health | 30s | 10s | 3 | 40s |
| PHP-FPM | Custom script + fcgi | 30s | 10s | 3 | 60s |
| MySQL | mysqladmin ping | 10s | 5s | 5 | 30s |
| Redis | redis-cli ping | 10s | 5s | 5 | 10s |
| Queue Worker | ps aux grep | 30s | 10s | 3 | 60s |
| Scheduler | ps aux grep crond | 60s | 10s | 3 | 30s |

## Security Considerations

### Secrets Management

**Never commit**:
- `.env` files
- Database passwords
- API keys
- Service account credentials

**Use environment variables** for:
- Database credentials
- Redis passwords
- API tokens
- Webhook secrets

### Network Security

- All services run on isolated networks
- Only Nginx exposed to external traffic
- Database and Redis not directly accessible from outside
- Rate limiting on all API endpoints
- CORS configured for allowed origins only

### Container Security

- Alpine Linux base images (minimal attack surface)
- Non-root users for application processes
- Read-only file systems where possible
- No privileged containers

## Performance Tuning

### OPcache (Production)

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # Disable in production
opcache.revalidate_freq=0
```

### PHP-FPM Pool

```ini
pm=dynamic
pm.max_children=20         # Max concurrent requests
pm.start_servers=5         # Initial workers
pm.min_spare_servers=3     # Minimum idle workers
pm.max_spare_servers=10    # Maximum idle workers
pm.max_requests=1000       # Restart after N requests
```

### MySQL

```ini
innodb_buffer_pool_size=256M    # Increase for more RAM
max_connections=200             # Adjust based on load
innodb_flush_log_at_trx_commit=2  # Better performance
```

### Redis

```ini
maxmemory=256mb
maxmemory-policy=allkeys-lru
appendfsync=everysec           # Balance durability/speed
```

### Nginx

- FastCGI cache for read-heavy endpoints
- Gzip compression level 6
- Keepalive connections enabled
- Static file caching (1 year)

## Monitoring & Observability

### Log Locations

- **Nginx Access**: `/infra/logs/nginx/access.log`
- **Nginx Error**: `/infra/logs/nginx/error.log`
- **Laravel**: `/backend/storage/logs/laravel.log`
- **Queue**: `/infra/logs/queue/`
- **Scheduler**: `/infra/logs/scheduler/`
- **MySQL Slow Query**: `/var/log/mysql/slow-query.log`

### Health Endpoints

- **API Health**: http://localhost:8080/api/health
  - Returns: Database, Redis, Cache, Storage status
- **API Ping**: http://localhost:8080/api/ping
  - Returns: Simple OK response
- **API Version**: http://localhost:8080/api/version
  - Returns: Application and framework versions

### Metrics (Future Integration)

Prometheus exporters can be added:
- MySQL Exporter
- Redis Exporter
- Nginx VTS Module
- PHP-FPM Exporter

## Troubleshooting

### Common Issues

**1. Database connection refused**
```bash
# Check if MySQL is running
docker compose ps mysql

# Check MySQL logs
docker compose logs mysql

# Verify network connectivity
docker compose exec php-fpm ping mysql
```

**2. Redis connection refused**
```bash
# Check if Redis is running
docker compose ps redis

# Test Redis connection
docker compose exec redis redis-cli -a <password> ping
```

**3. Queue jobs not processing**
```bash
# Check queue worker status
docker compose logs queue-worker

# Restart queue workers
docker compose restart queue-worker

# Manually process queue
docker compose exec php-fpm php artisan queue:work --once
```

**4. Scheduler not running tasks**
```bash
# Check scheduler logs
docker compose logs scheduler

# Verify cron is running
docker compose exec scheduler ps aux | grep crond

# Manually run scheduler
docker compose exec php-fpm php artisan schedule:run
```

**5. Nginx 502 Bad Gateway**
```bash
# Check if PHP-FPM is running
docker compose ps php-fpm

# Check PHP-FPM logs
docker compose logs php-fpm

# Verify PHP-FPM health
docker compose exec php-fpm php-fpm-healthcheck
```

## Backup & Recovery

### Database Backup

**Manual Backup**:
```bash
docker compose exec mysql mysqldump -u functionalfit -p functionalfit_db > backup.sql
```

**Automated Backup** (cron on host):
```bash
0 2 * * * cd /path/to/project && docker compose exec -T mysql mysqldump -u functionalfit -p$(grep DB_PASSWORD .env | cut -d '=' -f2) functionalfit_db | gzip > backups/mysql/backup_$(date +\%Y\%m\%d_\%H\%M\%S).sql.gz
```

**Restore**:
```bash
docker compose exec -T mysql mysql -u functionalfit -p functionalfit_db < backup.sql
```

### Volume Backup

```bash
# Stop containers
docker compose down

# Backup volumes
docker run --rm -v functionalfit_mysql_data:/data -v $(pwd)/backups:/backup alpine tar czf /backup/mysql_data.tar.gz /data

# Start containers
docker compose up -d
```

## Scaling & High Availability

### Horizontal Scaling

**Queue Workers**:
```bash
docker compose up -d --scale queue-worker=5
```

**PHP-FPM** (requires load balancer):
```yaml
# In docker-compose.yml, remove container_name
# and use --scale flag
docker compose up -d --scale php-fpm=3
```

### Load Balancing

For production, use external load balancer:
- AWS ELB / ALB
- Nginx upstream module
- HAProxy
- Traefik

### Database High Availability

For production:
- MySQL Master-Slave replication
- MySQL Group Replication
- Cloud-managed database (RDS, Cloud SQL)

### Redis High Availability

For production:
- Redis Sentinel (automatic failover)
- Redis Cluster (sharding)
- Cloud-managed Redis (ElastiCache, Cloud Memorystore)

## Production Deployment Checklist

- [ ] Change all default passwords in `.env`
- [ ] Set `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Configure SSL certificates in Nginx
- [ ] Enable OPcache with `validate_timestamps=0`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Build frontend with `npm run build`
- [ ] Configure log rotation
- [ ] Set up automated backups
- [ ] Configure monitoring and alerting
- [ ] Test health check endpoints
- [ ] Perform load testing
- [ ] Document disaster recovery procedures

## References

- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Laravel Deployment](https://laravel.com/docs/11.x/deployment)
- [Nginx Optimization](https://www.nginx.com/blog/tuning-nginx/)
- [MySQL Performance Tuning](https://dev.mysql.com/doc/refman/8.0/en/optimization.html)
- [Redis Best Practices](https://redis.io/docs/management/optimization/)
