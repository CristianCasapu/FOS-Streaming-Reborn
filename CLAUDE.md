# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

---

## Project Overview

**FOS-Streaming Reborn v70** is evolving into an enterprise-grade SaaS IPTV platform providing secured streaming services to subscribers through advanced encryption, traffic obfuscation, and modern streaming protocols. The platform is designed to operate as a secure proxy for streaming content with enterprise-grade security, high performance, and efficient resource utilization.

**Repository**: [CristianCasapu/FOS-Streaming-Reborn](https://github.com/CristianCasapu/FOS-Streaming-Reborn)
- **Active Branch**: `develop`
- **Stable Branch**: `master`
- **Refactoring Plan**: See `docs/guides/PLATFORM_REFACTORING_MASTER_PLAN.md`

### Current Tech Stack
- **Backend**: PHP 8.4 + Laravel Illuminate components + Eloquent ORM
- **Frontend**: Vue.js 3 (Composition API) + Vue Router + Pinia + TailwindCSS
- **Database**: MariaDB 11.4 (UTF8MB4)
- **Build**: Vite 7 + PostCSS + Autoprefixer
- **Workers**: PM2 + Node.js 20 LTS
- **Server**: Nginx with HTTP-FLV module
- **Streaming**: RTMP/HLS/HTTP-FLV with FFmpeg

### Target Architecture (In Development)
- **Streaming Protocol**: SRT (Secure Reliable Transport) with AES-256 encryption
- **Web Delivery**: QUIC/HTTP3 with TLS 1.3 + ECH (Encrypted Client Hello)
- **Traffic Obfuscation**: V2Ray with VMess/VLESS protocols
- **Container Formats**: MP4+AAC (Live), MKV (VOD with multi-track support)
- **Security**: Zero-trust model with CDN integration (Cloudflare/Sucuri)
- **Load Balancing**: Multi-node architecture with automatic failover

---

## Common Commands

### Frontend Development
```bash
# Development server with hot reload (port 5173)
npm run dev

# Production build
npm run build

# Watch mode for continuous builds
npm run watch

# Preview production build
npm run preview
```

### Backend Development (Composer Scripts)
```bash
# Install dependencies
composer install
composer dump-autoload     # Update autoloader

# Testing
composer test              # Run all tests
composer test:unit         # Unit tests only
composer test:feature      # Feature tests only
composer test:coverage     # Generate coverage report

# Code Quality
composer lint              # Auto-fix code style
composer lint:test         # Check style without fixing
composer analyze           # Static analysis (PHPStan)
composer check             # Run lint + analyze + test

# Development
composer serve             # Start dev server
composer dev               # Clear cache + migrate + serve
composer fresh             # Fresh database with seeding
composer cache:clear       # Clear all caches

# Database (shortcuts)
composer migrate           # Run migrations
composer migrate:status    # Show migration status
composer db:seed           # Seed database

# Deployment
composer deploy:check      # Pre-deployment checks
composer prod:deploy       # Production deployment

# Utilities
composer security-check    # Security vulnerability scan
composer organize-docs     # Organize documentation
composer check-syntax      # Check PHP syntax
```

### PM2 Worker Management
```bash
# Start all background workers
npm run pm2:start

# Stop all workers
npm run pm2:stop

# Restart workers
npm run pm2:restart

# View worker status
npm run pm2:status

# View real-time logs
npm run pm2:logs

# Interactive monitoring
npm run pm2:monit
```

### Database Operations (Artisan CLI)
```bash
# List all available commands
php artisan list

# Run database migrations
php artisan migrate

# Drop all tables and re-migrate (DESTRUCTIVE!)
php artisan migrate:fresh

# Fresh migration + seeding
php artisan migrate:fresh --seed

# Show migration status
php artisan migrate:status

# Seed database with data
php artisan db:seed

# Seed specific seeder
php artisan db:seed PackagesSeeder

# Drop all tables (DESTRUCTIVE!)
php artisan db:wipe

# Get help for any command
php artisan help migrate
```

### Database Operations (Utilities)
```bash
# Connect to database (credentials from .env)
mysql -u $(grep DB_USERNAME .env | cut -d '=' -f2) -p$(grep DB_PASSWORD .env | cut -d '=' -f2) $(grep DB_DATABASE .env | cut -d '=' -f2)

# Verify deployment
php database/verify_deployment.php   # Check tables and data
```

### Docker Development (Laravel Sail)
```bash
# Start development environment
./vendor/bin/sail up -d

# Access at http://localhost:7777
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev

# Stop environment
./vendor/bin/sail down
```

---

## Platform Purpose & Vision

### Core Mission
Transform FOS-Streaming into a **secured IPTV service platform** that provides:
- Enterprise-grade security for content protection
- ISP-resistant streaming through traffic obfuscation
- Multi-tenant SaaS architecture for resellers
- Zero-trust security model with complete audit trails

### Key Service Gateways
1. **Web Gateway (80/443)**: Admin, Subscriber, and Reseller portals
2. **RTMP Gateway (1935)**: Source ingestion and inter-node communication
3. **Streaming Gateway (8000)**: Secured subscriber stream delivery
4. **API Gateway (3000)**: Node heartbeat and load balancing

### Security Philosophy
- **No Unauthenticated Access**: Every request must be validated
- **Traffic Obfuscation**: Hide streaming patterns from ISP detection
- **End-to-End Encryption**: SRT with AES-256 for all streams
- **DPI Evasion**: V2Ray tunneling with WebSocket disguise
- **Automated Protection**: Auto-ban on port scanning/sniffing attempts

### Target Users
- **IPTV Service Providers**: Need secure, scalable streaming infrastructure
- **Content Distributors**: Require DRM-like protection without complexity
- **Resellers**: Want white-label streaming services
- **Enterprises**: Need private, secured video distribution

For detailed implementation roadmap, see: `docs/guides/PLATFORM_REFACTORING_MASTER_PLAN.md`

---

## Critical Architecture Rules

### 🚨 SPA Architecture Pattern

This is a **single-page application (SPA)**. Always maintain strict separation:

**Frontend (Vue.js 3)**
- Components: `/resources/js/views/` (pages) and `/resources/js/components/` (reusable)
- State: Pinia stores in `/resources/js/stores/`
- API layer: `/resources/js/services/api.js` (centralized axios instance)
- Router: `/resources/js/router/index.js` (hash mode navigation)
- Build: Vite with entry points in `vite.config.js`

**Backend (PHP API)**
- Endpoints: `/public/admin/api/*.php` (return JSON only)
- Models: `/models/*.php` (Eloquent ORM)
- Auth: `logincheck()` function enforces session validation
- Response format: Always `{ success: bool, data: any, error?: string }`

**Never mix**: Don't create Blade views for admin UI, don't return HTML from API endpoints, don't use jQuery.

### API Endpoint Pattern

All admin APIs follow this structure:

```php
<?php
// /public/admin/api/example.php
require_once '../../../config.php';
logincheck(); // Enforce authentication

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $items = Model::all();
        echo json_encode(['success' => true, 'data' => $items]);
        break;

    case 'create':
        $data = json_decode(file_get_contents('php://input'), true);
        $item = Model::create($data);
        echo json_encode(['success' => true, 'data' => $item]);
        break;

    case 'delete':
        $id = $_GET['id'] ?? null;
        Model::destroy($id);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
```

Frontend calls these via centralized API service:

```javascript
// /resources/js/services/api.js
export const exampleAPI = {
    getAll: (params) => api.get(`${ADMIN_API_PREFIX}/example.php?action=list&${new URLSearchParams(params)}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/example.php?action=create`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/example.php?action=delete&id=${id}`),
};
```

---

## Architecture Deep Dive

### PM2 Background Workers

Eight Node.js workers run continuously via PM2, configured in `ecosystem.config.js`:

**Core Workers** (CommonJS format - .cjs):
1. **stream-import-worker.cjs** - Imports streams from M3U playlists
2. **ffprobe-worker.cjs** - Analyzes stream technical details (codec, bitrate, resolution)
3. **stream-manager-worker.cjs** - Manages stream lifecycle (start/stop/restart)
4. **stream-monitor-worker.cjs** - Health checks and logging
5. **website-health-worker.cjs** - Website uptime monitoring

**Protocol Workers** (New - Advanced streaming):
6. **srt-proxy-worker.cjs** - SRT protocol proxy with AES-256 encryption
7. **quic-proxy-worker.cjs** - QUIC/HTTP3 proxy for next-gen delivery
8. **v2ray-proxy-worker.cjs** - V2Ray traffic obfuscation (VMess/VLESS)

Workers are **auto-configured** by `App\Services\PM2WorkerService` which reads from database `pm2_workers` table and generates `ecosystem.config.js`. Manage workers through admin UI at Settings → PM2 Manager, not by editing files manually.

**Note**: All workers converted from .js to .cjs (CommonJS) for better Node.js 20+ compatibility and explicit module system.

### Subscriber Management System

Simplified multi-table architecture for IPTV subscription management:

- **subscribers** - Customer accounts
- **packages** - Subscription tiers (Basic/Standard/Premium/Enterprise)
- **bouquets** - Stream groups (Sports, Movies, News, etc.) - directly reference streams via `stream_ids` JSON array
- **streams** - Individual streaming sources (link directly to bouquets)
- **subscriptions** - Active paid subscriptions (many per subscriber)
- **trials** - Time-limited free access (one per subscriber, enforced by unique constraint)
- **package_bouquet** - Package contains which bouquets

**Key constraint**: One subscriber can have multiple subscriptions but only ONE trial (database-level unique constraint + trigger for auto-expiration).

**Architecture Note**: The redundant `channels` table and `bouquet_channel` pivot table have been removed. Bouquets now directly reference streams via a `stream_ids` JSON column, simplifying the data model.

Navigation organization:
- **Streams dropdown**: Manage Streams, Bouquets, Categories, Packages (content management)
- **Subscribers dropdown**: Manage Subscribers, Subscriptions, Trials, Activity (customer management)

### Database Standards

**Always UTF8MB4**: All tables/columns use `utf8mb4` charset with `utf8mb4_unicode_ci` collation for full Unicode support (emojis, international characters).

**Eloquent Models**: All database operations use Eloquent ORM. Models in `/models/` use `Illuminate\Database\Eloquent\Model`. Never write raw SQL - use query builder or Eloquent.

**Environment Configuration**: Database credentials in `.env` (never commit), loaded via `vlucas/phpdotenv`. Access with `env('DB_HOST')` or `config('database.host')`.

**Database Migrations (IMPORTANT)**:
- **ALWAYS** create Laravel PHP migration files for any database schema changes
- **NEVER** modify the database directly with raw SQL in scripts or CLI
- **NEVER** create raw SQL migration files - use Laravel migrations exclusively
- Migration files ensure changes are reproducible and tracked via `php artisan migrate`
- **Location**: All migrations in `/database/migrations/laravel/` with Laravel naming format `YYYY_MM_DD_HHMMSS_description.php`
- **Run migrations**: `php artisan migrate`
- **Check status**: `php artisan migrate:status`
- **Rollback**: `php artisan migrate:rollback`

**Migration Template**:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Capsule::schema();

        // Check before creating/modifying
        if (!$schema->hasTable('table_name')) {
            $schema->create('table_name', function (Blueprint $table) {
                $table->increments('id');
                // ... columns
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('table_name');
    }
};
```

### Frontend Build System

Vite 7 configuration in `vite.config.js`:

- **Entry points**:
  - `resources/js/app.js` → Admin SPA
  - `resources/js/subscriber.js` → Subscriber portal
  - `resources/css/app.css` → TailwindCSS styles
- **Output**: `public/build/` (gitignored)
- **Aliases**: `@` = `/resources/js`, `~` = `/resources`
- **Dev server**: Port 5173 with HMR
- **Production**: Modern browser targets (esnext)

TailwindCSS config in `tailwind.config.js` scans all Vue/PHP files for class usage.

### Session & Authentication

- **Sessions**: PHP native sessions via `Illuminate\Session`
- **Auth check**: `logincheck()` function in `config.php` validates admin session
- **Storage**: Session data in `/storage/framework/sessions/`
- **CSRF**: Vue components include CSRF token from meta tag, axios adds to headers
- **Logout**: Clears session and redirects to `/#/login`

---

## Environment Configuration

### Required .env Variables

```bash
# Database (UTF8MB4 required)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=fos_streaming
DB_USERNAME=fos
DB_PASSWORD=secure_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Application
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Chicago
APP_PORT=7777

# Streaming
STREAMING_PORT=8000
RTMP_PORT=1935
```

Configuration is accessed via helpers:
- `env('DB_HOST', 'localhost')` - Get from .env with fallback
- `config('database.host')` - Dot notation for nested config

### Configuration Flow

1. `.env` loaded by `config.php` using Dotenv
2. Eloquent configured with database credentials
3. Blade template engine initialized
4. Helper functions loaded from `helpers.php`
5. Session and auth middleware activated

---

## Documentation Organization

**Location**: All docs in `/docs/` subdirectories (except CLAUDE.md and README.md in project root)

**Structure**:
- `/docs/guides/` - Feature documentation, implementation guides, migration plans
- `/docs/database/` - Schema changes, migration scripts
- `/docs/install/` - Installation comparison, platform-specific guides

**Key docs**:
- Subscriber system: `docs/guides/SUBSCRIBER_MANAGEMENT_COMPLETED.md`
- Navigation: `docs/guides/NAVIGATION_REORGANIZATION.md`
- PM2 workers: `docs/guides/PM2_BACKGROUND_WORKERS_GUIDE.md`
- Stream analysis: `docs/guides/STREAM_ANALYSIS_GUIDE.md`
- Laravel integration: `docs/guides/LARAVEL_COMPONENTS_USAGE.md`

**Policy**: Create new docs in appropriate `/docs/` subdirectory, not project root. Update existing docs rather than creating duplicates.

---

## Code Conventions

### Vue.js 3 Composition API

Always use `<script setup>` syntax:

```vue
<script setup>
import { ref, computed, onMounted } from 'vue';
import { exampleAPI } from '../services/api';

const items = ref([]);
const loading = ref(false);

const fetchItems = async () => {
    loading.value = true;
    try {
        const response = await exampleAPI.getAll();
        items.value = response.data.data;
    } catch (error) {
        console.error('Error:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchItems();
});
</script>

<template>
    <div class="max-w-7xl mx-auto px-4">
        <div v-if="loading">Loading...</div>
        <div v-else v-for="item in items" :key="item.id">
            {{ item.name }}
        </div>
    </div>
</template>
```

### Eloquent Models

```php
<?php
// /models/Example.php

use Illuminate\Database\Eloquent\Model;

class Example extends Model
{
    protected $table = 'examples';
    protected $fillable = ['name', 'description', 'active'];
    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function related()
    {
        return $this->hasMany(Related::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }
}

// Usage
$items = Example::active()->with('related')->get();
```

### API Service Pattern

All API calls through centralized service in `/resources/js/services/api.js`:

```javascript
// Add new API endpoints here
export const newFeatureAPI = {
    getAll: (params = {}) => {
        const queryString = new URLSearchParams(params).toString();
        return api.get(`${ADMIN_API_PREFIX}/new_feature.php?action=list&${queryString}`);
    },
    getOne: (id) => api.get(`${ADMIN_API_PREFIX}/new_feature.php?action=get&id=${id}`),
    create: (data) => api.post(`${ADMIN_API_PREFIX}/new_feature.php?action=create`, data),
    update: (id, data) => api.post(`${ADMIN_API_PREFIX}/new_feature.php?action=update&id=${id}`, data),
    delete: (id) => api.get(`${ADMIN_API_PREFIX}/new_feature.php?action=delete&id=${id}`),
};
```

### Git Workflow

**Never commit**:
- `.env` (contains secrets)
- `/vendor/` (Composer packages)
- `/node_modules/` (NPM packages)
- `/public/build/` (Vite output)
- `/storage/logs/*.log`
- `/cache/` (template cache)

**Always commit**:
- `.env.example` (template)
- `composer.lock` (dependency versions)
- `package-lock.json` (NPM versions)
- `/database/migrations/laravel/*.php` (Laravel migrations)

---

## Adding New Features

### 1. Create Backend API

```bash
# Create model
touch models/FeatureName.php

# Create API endpoint
touch public/admin/api/feature_name.php

# Create Laravel migration (if needed)
touch database/migrations/laravel/2025_11_23_100000_create_feature_name_table.php
```

### 2. Create Frontend Components

```bash
# Create Vue component
touch resources/js/views/FeatureName/FeatureNameList.vue

# Add API service methods to resources/js/services/api.js
# Add route to resources/js/router/index.js
# Add navigation link to resources/js/components/AppLayout.vue
```

### 3. Build and Test

```bash
# Run frontend build
npm run build

# Clear autoloader cache
composer dump-autoload

# Test in browser
# Access at http://localhost:7777/admin#/feature-name
```

### 4. Document

Create guide in `/docs/guides/FEATURE_NAME_GUIDE.md` documenting:
- Purpose and functionality
- Database schema changes
- API endpoints
- Vue components
- Configuration requirements

---

## Troubleshooting

### Build Issues

```bash
# Clear node_modules and reinstall
rm -rf node_modules package-lock.json
npm install

# Clear Vite cache
rm -rf node_modules/.vite

# Rebuild
npm run build
```

### Database Connection Issues

```bash
# Verify .env settings
grep -E "DB_" .env

# Test MySQL connection
mysql -u $(grep DB_USERNAME .env | cut -d'=' -f2) -p

# Verify UTF8MB4 charset
mysql> SHOW VARIABLES LIKE 'char%';
```

### PM2 Worker Issues

```bash
# Check worker status
npm run pm2:status

# View error logs
pm2 logs <worker-name> --err --lines 50

# Restart specific worker
pm2 restart <worker-name>

# Delete and reload all workers
pm2 delete all
npm run pm2:start
```

### Permission Issues

```bash
# Fix ownership (web server user)
sudo chown -R www-data:www-data .

# Fix directory permissions
find . -type d -exec chmod 755 {} \;

# Fix file permissions
find . -type f -exec chmod 644 {} \;

# Storage needs write access
chmod -R 775 storage cache
```

---

## Version Information

- **Version**: 70.0.0 (Security Fortress)
- **Released**: 2025-11-21
- **PHP**: 8.4 required
- **MariaDB**: 11.4+ required
- **Node.js**: 20.19.0+ LTS required
- **Nginx**: 1.26+ with HTTP-FLV module

---

## Quick Reference Links

**Installation**: `/docs/install/INSTALLATION_COMPARISON.md`
**Laravel Components**: `/docs/guides/LARAVEL_COMPONENTS_USAGE.md`
**Environment Config**: `/docs/guides/ENV_CONFIGURATION_GUIDE.md`
**Subscriber System**: `/docs/guides/SUBSCRIBER_MANAGEMENT_COMPLETED.md`
**PM2 Workers**: `/docs/guides/PM2_BACKGROUND_WORKERS_GUIDE.md`
**Stream Analysis**: `/docs/guides/STREAM_ANALYSIS_GUIDE.md`

---

**Last Updated**: 2025-12-11
**Maintained By**: Claude Code + Development Team
