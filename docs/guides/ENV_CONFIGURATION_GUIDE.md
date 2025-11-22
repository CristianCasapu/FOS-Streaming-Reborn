# Environment Configuration Guide

## Overview

FOS-Streaming v70 now uses environment-based configuration via `.env` files, following Laravel best practices. This makes configuration more flexible, secure, and environment-specific.

## What Changed

### Before (Hardcoded in config.php)
```php
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'fos_dev',
    'username'  => 'fos_dev',
    'password'  => 'fos_dev_password',
    // ...
]);
```

### After (Environment-Based)
```php
$capsule->addConnection([
    'driver'    => env('DB_CONNECTION', 'mysql'),
    'host'      => env('DB_HOST', 'localhost'),
    'database'  => env('DB_DATABASE', 'fos_dev'),
    'username'  => env('DB_USERNAME', 'fos_dev'),
    'password'  => env('DB_PASSWORD', 'fos_dev_password'),
    // ...
]);
```

## Files

### 1. .env (Not tracked in git)
Your actual configuration file with real credentials.

**Location:** Project root
**Git Status:** ❌ Ignored (never commit this!)
**Purpose:** Store environment-specific configuration

### 2. .env.example (Tracked in git)
Template with example/default values.

**Location:** Project root
**Git Status:** ✅ Committed
**Purpose:** Template for new installations

### 3. config.php (Updated)
Now loads configuration from .env file.

**Location:** Project root
**Git Status:** ✅ Committed
**Purpose:** Bootstrap application with env config

### 4. helpers.php (New)
Helper functions for accessing environment variables.

**Location:** Project root
**Git Status:** ✅ Committed
**Purpose:** Laravel-style helper functions

## Setup

### New Installation

```bash
# 1. Copy example file
cp .env.example .env

# 2. Edit .env with your values
nano .env

# 3. Update database credentials
DB_DATABASE=fos_streaming
DB_USERNAME=fos
DB_PASSWORD=your_secure_password

# 4. Update ports if needed
APP_PORT=7777
STREAMING_PORT=8000
RTMP_PORT=1935
```

### Existing Installation

The `.env` file has already been created from `.env.example`. Update it with your actual values.

## Environment Variables

### Application

```bash
APP_NAME="FOS Streaming v70"         # Application name
APP_ENV=local                         # Environment: local, production, testing
APP_DEBUG=true                        # Debug mode (false in production!)
APP_URL=http://localhost:7777         # Base URL
APP_TIMEZONE=America/Chicago          # Timezone
```

### Database

```bash
DB_CONNECTION=mysql                   # Database driver
DB_HOST=localhost                     # Database host
DB_PORT=3306                          # Database port
DB_DATABASE=fos_dev                   # Database name
DB_USERNAME=fos_dev                   # Database user
DB_PASSWORD=fos_dev_password          # Database password
DB_CHARSET=utf8                       # Character set
DB_COLLATION=utf8_unicode_ci          # Collation
DB_PREFIX=                            # Table prefix (optional)
```

### Ports

```bash
APP_PORT=7777                         # Web panel port
STREAMING_PORT=8000                   # Streaming port
RTMP_PORT=1935                        # RTMP port
NGINX_HTTP_PORT=80                    # Nginx HTTP port
NGINX_HTTPS_PORT=443                  # Nginx HTTPS port
```

### Template Paths

```bash
VIEWS_PATH=views                      # Blade templates directory
CACHE_PATH=cache                      # Template cache directory
```

### Redis

```bash
REDIS_HOST=redis                      # Redis host
REDIS_PASSWORD=null                   # Redis password
REDIS_PORT=6379                       # Redis port
```

### Cache & Queue

```bash
CACHE_DRIVER=file                     # Cache driver: file, redis, database
QUEUE_CONNECTION=sync                 # Queue: sync, database, redis
SESSION_DRIVER=file                   # Session: file, redis, database
```

### Mail

```bash
MAIL_MAILER=smtp                      # Mail driver
MAIL_HOST=mailpit                     # SMTP host
MAIL_PORT=1025                        # SMTP port
MAIL_USERNAME=null                    # SMTP username
MAIL_PASSWORD=null                    # SMTP password
MAIL_ENCRYPTION=null                  # Encryption: tls, ssl
MAIL_FROM_ADDRESS="noreply@fos-streaming.local"
MAIL_FROM_NAME="${APP_NAME}"
```

### Streaming

```bash
WEB_IP=*                              # Web IP (* for all)
STREAMING_AUTH=true                   # Enable streaming auth
FAIL2BAN_ENABLED=true                 # Enable fail2ban
UFW_ENABLED=false                     # Enable UFW firewall
```

### FFmpeg

```bash
FFMPEG_PATH=/usr/local/bin/ffmpeg     # FFmpeg binary path
FFPROBE_PATH=/usr/local/bin/ffprobe   # FFprobe binary path
```

### HLS

```bash
HLS_PATH=/var/www/html/hl             # HLS output directory
HLS_URL_PREFIX=http://localhost:8000/live  # HLS URL prefix
```

### Security

```bash
RATE_LIMIT_ENABLED=true               # Enable rate limiting
MAX_LOGIN_ATTEMPTS=5                  # Max login attempts
LOGIN_TIMEOUT=900                     # Login timeout (seconds)
SECURITY_LOGGING=true                 # Enable security logging
SESSION_LIFETIME=120                  # Session lifetime (minutes)
CSRF_TOKEN_TIMEOUT=7200               # CSRF timeout (seconds)
```

### Logging

```bash
LOG_CHANNEL=daily                     # Log channel: daily, single, stack
LOG_LEVEL=debug                       # Log level: debug, info, warning, error
```

## Helper Functions

### env()

Get environment variable with optional default:

```php
$dbHost = env('DB_HOST', 'localhost');
$debug = env('APP_DEBUG', false);
```

### config()

Get configuration using dot notation:

```php
$appName = config('app.name');
$dbHost = config('database.host');
$ffmpegPath = config('ffmpeg.path');
```

### Other Helpers

```php
// Path helpers
$path = base_path('vendor');
$storage = storage_path('logs');
$public = public_path('css');

// URL helpers
$url = url('/login');
$asset = asset('css/style.css');

// Session helpers
$value = session('key', 'default');
session(['key' => 'value']);
flash('success', 'Saved!');

// CSRF
$token = csrf_token();
echo csrf_field();
echo method_field('PUT');

// Debug
dd($variable);
dump($variable);
```

## Usage Examples

### In PHP Files

```php
// Get database credentials
$db = [
    'host' => env('DB_HOST'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
];

// Get app settings
$appName = config('app.name');
$debug = config('app.debug');

// Check if in production
if (env('APP_ENV') === 'production') {
    // Production code
}
```

### In Blade Templates

```php
<!-- Using helper functions -->
<title>{{ config('app.name') }}</title>

<!-- CSRF protection -->
<form method="POST">
    {!! csrf_field() !!}
    <!-- form fields -->
</form>

<!-- Assets -->
<link href="{{ asset('css/style.css') }}" rel="stylesheet">
```

## Environment-Specific Configurations

### Local Development (.env)

```bash
APP_ENV=local
APP_DEBUG=true
DB_HOST=localhost
DB_DATABASE=fos_dev
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_HOST=mailpit
```

### Production (.env)

```bash
APP_ENV=production
APP_DEBUG=false
DB_HOST=127.0.0.1
DB_DATABASE=fos_streaming
DB_PASSWORD=strong_random_password
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
MAIL_HOST=smtp.gmail.com
MAIL_ENCRYPTION=tls
```

### Docker/Sail (.env)

```bash
APP_ENV=local
APP_DEBUG=true
DB_HOST=mariadb                       # Docker service name
REDIS_HOST=redis                      # Docker service name
MAIL_HOST=mailpit                     # Docker service name
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

## Best Practices

### 1. Never Commit .env

The `.env` file contains sensitive credentials and should **NEVER** be committed to git.

**Correct:**
```bash
# .gitignore already includes:
.env
.env.local
.env.*.local
.env.backup
.env.production
```

### 2. Keep .env.example Updated

When adding new environment variables, update `.env.example`:

```bash
# Add to .env.example
NEW_API_KEY=your_api_key_here

# Add to helpers.php config() function
'api.key' => env('NEW_API_KEY'),
```

### 3. Use Secure Passwords

```bash
# ❌ Bad
DB_PASSWORD=password123

# ✅ Good
DB_PASSWORD=$(openssl rand -base64 32)
```

### 4. Environment-Specific Values

```bash
# Development
APP_DEBUG=true
LOG_LEVEL=debug

# Production
APP_DEBUG=false
LOG_LEVEL=warning
```

### 5. Boolean Values

```bash
# These all work
APP_DEBUG=true
APP_DEBUG=false
APP_DEBUG=1
APP_DEBUG=0
```

The `env()` helper automatically converts:
- `true` / `(true)` → `true`
- `false` / `(false)` → `false`
- `null` / `(null)` → `null`
- `empty` / `(empty)` → `''`

## Migration from Old Config

If you have an existing `config.php` with hardcoded values:

### 1. Identify Values

```php
// Old config.php
$capsule->addConnection([
    'database'  => 'my_database',      // ← Extract this
    'username'  => 'my_user',          // ← Extract this
    'password'  => 'my_password',      // ← Extract this
]);
```

### 2. Move to .env

```bash
# Add to .env
DB_DATABASE=my_database
DB_USERNAME=my_user
DB_PASSWORD=my_password
```

### 3. config.php is Already Updated

The new `config.php` automatically loads from `.env`:

```php
$capsule->addConnection([
    'database'  => env('DB_DATABASE', 'fos_dev'),
    'username'  => env('DB_USERNAME', 'fos_dev'),
    'password'  => env('DB_PASSWORD', 'fos_dev_password'),
]);
```

## Troubleshooting

### .env Not Loading

**Check:**
1. File exists: `ls -la .env`
2. File readable: `chmod 644 .env`
3. Composer autoload: `composer dump-autoload`
4. vlucas/phpdotenv installed: `composer require vlucas/phpdotenv`

### Variables Not Found

```php
// Debug env loading
var_dump($_ENV);
var_dump(env('DB_HOST'));
var_dump(config('database.host'));
```

### Permission Errors

```bash
# Fix permissions
chmod 644 .env
chown www-data:www-data .env
```

### Caching Issues

```bash
# Clear PHP opcache if needed
sudo systemctl restart php8.4-fpm
```

## Security Considerations

### 1. File Permissions

```bash
# .env should NOT be readable by everyone
chmod 600 .env            # Only owner can read/write
chown www-data:www-data .env
```

### 2. Backup Securely

```bash
# ❌ Don't commit backups
.env.backup

# ✅ Store securely outside repo
cp .env ~/.secure/fos-streaming-env-backup
chmod 600 ~/.secure/fos-streaming-env-backup
```

### 3. Environment Variables in Logs

```php
// ❌ Don't log env variables
error_log(env('DB_PASSWORD'));

// ✅ Mask sensitive data
error_log('DB connection failed');
```

### 4. Production Checklist

- [ ] `APP_DEBUG=false`
- [ ] Strong `DB_PASSWORD`
- [ ] `.env` file permissions: `600`
- [ ] `.env` not in git
- [ ] All secrets rotated from defaults
- [ ] `APP_ENV=production`

## Advanced Usage

### Environment-Specific Behavior

```php
if (env('APP_ENV') === 'production') {
    // Production-only code
    ini_set('display_errors', 0);
} else {
    // Development code
    ini_set('display_errors', 1);
}
```

### Dynamic Configuration

```php
// Load different config based on environment
$cacheDriver = env('APP_ENV') === 'production' ? 'redis' : 'file';
```

### Multiple Environments

```bash
# Local development
cp .env.example .env.local

# Staging
cp .env.example .env.staging

# Production
cp .env.example .env.production

# Load specific env
# (requires code changes to support)
```

## Summary

### Benefits

✅ **Security** - Credentials not in code
✅ **Flexibility** - Different configs per environment
✅ **Laravel Standard** - Familiar to Laravel developers
✅ **Version Control** - Only template committed
✅ **Maintenance** - Easy to update configuration

### Key Files

- `.env` - Your actual config (NOT in git)
- `.env.example` - Template (IN git)
- `config.php` - Loads from .env
- `helpers.php` - Helper functions

### Key Functions

- `env()` - Get environment variable
- `config()` - Get config value
- All Laravel-style helpers available

---

**Environment configuration is now complete and ready to use!** 🎉
