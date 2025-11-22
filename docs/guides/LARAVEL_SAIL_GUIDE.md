# Laravel Sail Development Guide

## Overview

FOS-Streaming v70 now includes Laravel Sail for containerized development. This guide will help you get started with Docker-based development.

## What's Included

### Laravel Components Added

The following Laravel components have been integrated:

#### Core Components
- **illuminate/validation** - Input validation and form requests
- **illuminate/cache** - Caching (file, Redis, database drivers)
- **illuminate/redis** - Redis integration
- **illuminate/events** - Event broadcasting and listeners
- **illuminate/queue** - Job queues (sync, database, Redis)
- **illuminate/mail** - Email sending
- **illuminate/notifications** - Multi-channel notifications
- **illuminate/filesystem** - File and cloud storage
- **illuminate/http** - HTTP client and requests
- **illuminate/routing** - Advanced routing
- **illuminate/session** - Session management
- **illuminate/encryption** - Encryption services
- **illuminate/hashing** - Password hashing (Argon2, bcrypt)
- **illuminate/cookie** - Cookie management
- **illuminate/log** - Logging with Monolog
- **illuminate/pagination** - Database pagination
- **illuminate/auth** - Authentication scaffolding
- **illuminate/console** - Artisan commands
- **illuminate/support** - Helper functions and collections

#### Additional Packages
- **monolog/monolog** - Flexible logging library
- **vlucas/phpdotenv** - Environment configuration
- **symfony/var-dumper** - Advanced debugging (dd, dump)
- **predis/predis** - Redis client
- **guzzlehttp/guzzle** - HTTP client

#### Development Tools
- **laravel/sail** - Docker development environment
- **laravel/pint** - Code style fixer (PSR-12)
- **phpstan/phpstan** - Static analysis
- **fakerphp/faker** - Fake data generation
- **mockery/mockery** - Testing mocks
- **nunomaduro/collision** - Beautiful error reporting
- **spatie/laravel-ignition** - Error page for Laravel

## Installation

### Prerequisites

- **Docker Desktop** (Windows/Mac) or **Docker Engine** (Linux)
- **Docker Compose** v2.0+
- **Git**

### Quick Start

1. **Clone the repository** (if not already done):
   ```bash
   cd /home/casapu/projects/FOS-Streaming-v69
   ```

2. **Copy environment file**:
   ```bash
   cp .env.example .env
   ```

3. **Start Sail**:
   ```bash
   ./vendor/bin/sail up -d
   ```

   Or create an alias (recommended):
   ```bash
   alias sail='./vendor/bin/sail'
   sail up -d
   ```

4. **Access the application**:
   - Web Panel: http://localhost:7777
   - Streaming: http://localhost:8000
   - Mailpit (Email Testing): http://localhost:8025

## Docker Services

### Service Overview

| Service | Port | Description |
|---------|------|-------------|
| fos-streaming | 7777 | Main PHP application |
| mariadb | 3306 | MariaDB 11.4 database |
| redis | 6379 | Redis cache/queue |
| mailpit | 1025/8025 | Email testing |
| nginx | 80/443 | Nginx with HTTP-FLV |

### Environment Variables

Edit `.env` to customize:

```bash
# Application
APP_PORT=7777
STREAMING_PORT=8000
RTMP_PORT=1935

# Database
DB_HOST=mariadb
DB_DATABASE=fos_streaming
DB_USERNAME=fos
DB_PASSWORD=fosstreaming

# Redis
REDIS_HOST=redis

# Cache & Queue
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

## Common Commands

### Sail Commands

```bash
# Start containers
sail up -d

# Stop containers
sail down

# Rebuild containers
sail build --no-cache

# View logs
sail logs
sail logs -f  # Follow logs

# Execute commands in container
sail shell  # Enter container shell
sail php --version
sail composer --version
```

### Artisan Commands

```bash
# Run artisan commands
sail artisan list
sail artisan tinker

# Custom commands (when added)
sail artisan stream:monitor
sail artisan cache:clear
sail artisan queue:work
```

### Database Commands

```bash
# Access MariaDB
sail mysql

# Run migrations (when created)
sail artisan migrate
sail artisan migrate:fresh --seed

# Database backup
sail exec mariadb mysqldump -u fos -pfosstreaming fos_streaming > backup.sql
```

### Composer & Dependencies

```bash
# Install packages
sail composer require package/name

# Update dependencies
sail composer update

# Dump autoload
sail composer dump-autoload
```

### Testing

```bash
# Run PHPUnit tests
sail test
sail test --filter=StreamTest

# Code style checking
sail pint
sail pint --test  # Dry run

# Static analysis
sail bin phpstan analyse
```

### Redis Commands

```bash
# Access Redis CLI
sail redis

# Clear Redis cache
sail artisan cache:clear
# Or directly:
sail redis
> FLUSHALL
```

## Development Workflow

### 1. Setting Up a New Feature

```bash
# Start containers
sail up -d

# Create new branch
git checkout -b feature/new-streaming-feature

# Install dependencies if needed
sail composer require vendor/package

# Work on your feature...
```

### 2. Running Tests

```bash
# Run all tests
sail test

# Run specific test
sail test tests/Unit/StreamTest.php

# Coverage report
sail test --coverage
```

### 3. Code Quality

```bash
# Fix code style
sail pint

# Check for errors
sail bin phpstan analyse
```

### 4. Queue Workers

```bash
# Start queue worker
sail artisan queue:work

# Run specific queue
sail artisan queue:work --queue=streams,default

# Process jobs and exit
sail artisan queue:work --stop-when-empty
```

## Using Laravel Components

### Validation Example

```php
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Container\Container;

$validator = (new ValidatorFactory(new Container()))
    ->make($request, [
        'username' => 'required|string|min:3|max:50',
        'email' => 'required|email',
        'stream_url' => 'required|url',
    ]);

if ($validator->fails()) {
    $errors = $validator->errors();
}
```

### Cache Example

```php
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Container;

$cache = (new CacheManager(Container::getInstance()));

// Store
$cache->put('stream:1234:status', 'active', 3600);

// Retrieve
$status = $cache->get('stream:1234:status');

// Remember
$streams = $cache->remember('active_streams', 600, function() {
    return Stream::where('status', 'active')->get();
});
```

### Queue Example

```php
use Illuminate\Queue\QueueManager;

$queue = app(QueueManager::class);

// Dispatch job
$queue->push(new ProcessStreamJob($stream));

// Delayed job
$queue->later(60, new RestartStreamJob($stream));
```

### Mail Example

```php
use Illuminate\Mail\Mailer;

$mailer = app(Mailer::class);

$mailer->send('emails.stream-down', $data, function($message) {
    $message->to('admin@example.com')
            ->subject('Stream Alert: Channel Offline');
});
```

### Logging Example

```php
use Illuminate\Log\LogManager;

$log = app(LogManager::class);

$log->info('Stream started', ['stream_id' => 1234]);
$log->warning('Stream quality degraded', ['bitrate' => 1000]);
$log->error('Stream failed', ['error' => $exception->getMessage()]);
```

## Troubleshooting

### Port Already in Use

```bash
# Check what's using the port
sudo netstat -tlnp | grep 7777

# Change port in .env
APP_PORT=8888

# Restart
sail down && sail up -d
```

### Permission Issues

```bash
# Fix permissions
sail exec fos-streaming chown -R www-data:www-data /var/www/html/hl
sail exec fos-streaming chmod -R 755 /var/www/html/cache
```

### Container Won't Start

```bash
# View logs
sail logs

# Rebuild containers
sail build --no-cache
sail up -d
```

### Database Connection Failed

```bash
# Check MariaDB is running
sail exec mariadb mysqladmin ping

# Verify credentials in .env
DB_HOST=mariadb  # NOT localhost!

# Restart database
sail restart mariadb
```

### Redis Connection Issues

```bash
# Test Redis
sail redis
> PING
PONG

# Check .env
REDIS_HOST=redis  # NOT localhost!
```

## Production Deployment

**Note:** Sail is for development only. For production:

1. Use the native installation method (see [README.md](README.md))
2. Configure services without Docker
3. Use proper environment configuration
4. Enable UFW firewall
5. Configure fail2ban
6. Set up SSL/TLS certificates

## Advanced Configuration

### Custom Dockerfile

You can customize the Dockerfile at:
```
./vendor/laravel/sail/runtimes/8.4/Dockerfile
```

Publish Sail configuration:
```bash
sail artisan sail:publish
```

### Adding FFmpeg to Container

Edit the published Dockerfile:
```dockerfile
RUN apt-get update && apt-get install -y ffmpeg
```

### Custom Nginx Configuration

Mount your nginx config in docker-compose.yml:
```yaml
volumes:
  - './nginx.conf:/etc/nginx/nginx.conf'
```

## Resource Management

### Container Resources

Monitor resource usage:
```bash
docker stats
```

Limit resources in docker-compose.yml:
```yaml
services:
  fos-streaming:
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
```

### Cleanup

```bash
# Remove stopped containers
sail down -v

# Clean Docker system
docker system prune -a

# Remove volumes (WARNING: deletes data)
sail down -v --remove-orphans
```

## Best Practices

1. **Always use Sail commands** - Don't run php/composer directly
2. **Environment files** - Never commit `.env` to git
3. **Volume mounts** - Keep project files on host, not in container
4. **Database backups** - Regular exports before experiments
5. **Redis for cache** - Use Redis instead of file cache in development
6. **Queue workers** - Run in separate terminal with `sail artisan queue:work`
7. **Log monitoring** - Keep `sail logs -f` running during development

## Further Reading

- [Laravel Documentation](https://laravel.com/docs/10.x)
- [Laravel Sail Documentation](https://laravel.com/docs/10.x/sail)
- [Docker Documentation](https://docs.docker.com/)
- [MariaDB Documentation](https://mariadb.org/documentation/)

## Support

For issues specific to:
- **FOS Streaming**: See main [README.md](README.md)
- **Laravel Sail**: https://github.com/laravel/sail
- **Docker**: https://docs.docker.com/

---

**Happy Streaming! 🎥**
