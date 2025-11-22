# Local Development Guide

## Overview

This guide explains how to set up and run FOS-Streaming v70 in a local development environment using `npm run dev` and `php artisan serve`.

## Prerequisites

### Required Software

1. **PHP 8.4+**
   ```bash
   php --version
   ```

2. **Composer 2.x**
   ```bash
   composer --version
   ```

3. **Node.js 18+ and NPM 9+**
   ```bash
   node --version
   npm --version
   ```

4. **MariaDB 11.4+ or MySQL 8.0+**
   ```bash
   mysql --version
   ```

### Optional Software

- **Git** - For version control
- **NVM** - For managing Node.js versions
- **Redis** - For caching (optional but recommended)

---

## Quick Start

### 1. Clone and Setup

```bash
# Clone repository
git clone https://github.com/theraw/FOS-Streaming-v69.git
cd FOS-Streaming-v69

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install

# Copy environment file
cp .env.example .env

# Edit environment configuration
nano .env
```

### 2. Configure Database

Update `.env` with your database credentials:

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fos_dev
DB_USERNAME=root
DB_PASSWORD=your_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

Create the database:

```bash
mysql -u root -p -e "CREATE DATABASE fos_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Start Development Servers

**Terminal 1 - Frontend (Vite):**
```bash
npm run dev
```

**Terminal 2 - Backend (PHP):**
```bash
php artisan serve
```

### 4. Access Application

- **Application:** http://localhost:8000
- **Vite Dev Server:** http://localhost:5173 (assets only)

---

## Detailed Setup

### Environment Configuration

The `.env` file contains all environment-specific configuration. Key variables for development:

```bash
# Application
APP_NAME="FOS Streaming v70"
APP_ENV=local                         # ← Development environment
APP_DEBUG=true                        # ← Enable debug mode
APP_URL=http://localhost:8000
APP_TIMEZONE=America/Chicago

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fos_dev
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Cache (use file driver for local dev)
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug                       # ← Detailed logs

# Template Paths
VIEWS_PATH=views
CACHE_PATH=cache
```

### Database Setup

#### Create Database

```bash
# Using MySQL/MariaDB CLI
mysql -u root -p

# In MySQL prompt
CREATE DATABASE fos_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON fos_dev.* TO 'fos_dev'@'localhost' IDENTIFIED BY 'dev_password';
FLUSH PRIVILEGES;
EXIT;
```

#### Import Schema (if exists)

```bash
# If you have a schema file
mysql -u root -p fos_dev < database/schema.sql

# Or run migrations
mysql -u root -p fos_dev < database/migrations/convert_to_utf8mb4.sql
```

---

## Development Commands

### NPM Commands

```bash
# Start Vite development server with HMR
npm run dev

# Build production assets
npm run build

# Build and watch for changes
npm run watch

# Preview production build
npm run preview
```

### Artisan Commands

```bash
# Start PHP development server
php artisan serve

# Start on specific host/port
php artisan serve --host=0.0.0.0 --port=9000

# List all available commands
php artisan list

# Install Laravel Sail (Docker)
php artisan sail:install

# Publish Sail configuration
php artisan sail:publish
```

### Composer Commands

```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Regenerate autoload files
composer dump-autoload

# Install dev dependencies only
composer install --dev

# Install production dependencies only
composer install --no-dev --optimize-autoloader
```

---

## Development Workflow

### 1. Frontend Development

#### File Structure

```
resources/
├── js/
│   ├── app.js              # Main JavaScript entry point
│   └── components/         # Vue components
│       └── *.vue
└── css/
    └── app.css             # Main CSS with Tailwind
```

#### Hot Module Replacement (HMR)

Vite provides instant feedback for frontend changes:

1. **Start Vite:** `npm run dev`
2. **Edit files:** Changes in `resources/js` or `resources/css` automatically reload
3. **Browser updates:** No manual refresh needed

#### Adding Assets to Templates

```html
<!-- In your Blade/PHP templates -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>FOS Streaming</title>

    <!-- Development: Vite serves assets -->
    <script type="module" src="http://localhost:5173/@vite/client"></script>
    <link rel="stylesheet" href="http://localhost:5173/resources/css/app.css">
    <script type="module" src="http://localhost:5173/resources/js/app.js"></script>
</head>
<body>
    <div id="app">
        <!-- Your content -->
    </div>
</body>
</html>
```

### 2. Backend Development

#### File Structure

```
app/
└── Console/
    └── Commands/
        └── ServeCommand.php    # php artisan serve

models/
├── User.php
├── Stream.php
└── ...

views/
├── layouts/
│   └── app.blade.php
└── ...

public/
└── index.php               # Front controller
```

#### Making Changes

1. **Edit PHP files:** Changes require server restart or refresh
2. **Edit models:** Use Eloquent ORM for database operations
3. **Edit views:** Use Blade-like syntax in `.blade.php` files
4. **Refresh browser:** See changes immediately

#### Using Eloquent ORM

```php
<?php

// In your PHP files
use App\Models\User;
use App\Models\Stream;

// Query users
$users = User::where('active', 1)->get();

// Create a new stream
$stream = new Stream();
$stream->name = 'Test Stream';
$stream->url = 'rtmp://example.com/live';
$stream->save();

// Update
$stream = Stream::find(1);
$stream->status = 'active';
$stream->save();

// Delete
$stream->delete();
```

### 3. Database Development

#### Running Migrations

```bash
# Run SQL migrations manually
mysql -u root -p fos_dev < database/migrations/some_migration.sql
```

#### Using Query Builder

```php
<?php

use Illuminate\Database\Capsule\Manager as DB;

// Select
$users = DB::table('users')->where('status', 'active')->get();

// Insert
DB::table('streams')->insert([
    'name' => 'Test Stream',
    'url' => 'rtmp://example.com/live',
    'created_at' => now(),
]);

// Update
DB::table('streams')
    ->where('id', 1)
    ->update(['status' => 'active']);

// Delete
DB::table('streams')->where('id', 1)->delete();
```

---

## Development Tools

### Browser DevTools

**Recommended Extensions:**
- Vue.js DevTools (for Vue components)
- React DevTools (if using React)
- Redux DevTools (if using Redux)

### PHP Debugging

#### Enable Debug Mode

In `.env`:
```bash
APP_DEBUG=true
LOG_LEVEL=debug
```

#### View Logs

```bash
# Real-time log viewing
tail -f storage/logs/laravel.log

# Or use storage/logs/fos-*.log
tail -f storage/logs/fos-$(date +%Y-%m-%d).log
```

#### Debug Functions

```php
<?php

// Dump and die
dd($variable);

// Dump without dying
dump($variable);

// Log debug info
error_log('Debug: ' . print_r($variable, true));

// Use Laravel logger
use Illuminate\Support\Facades\Log;
Log::debug('Debug message', ['context' => $data]);
```

### Frontend Debugging

#### Console Logging

```javascript
// In resources/js/app.js or components
console.log('Debug:', variable);
console.table(arrayData);
console.error('Error:', error);

// Styled console
console.log('%c FOS Streaming ', 'background: #4F46E5; color: white; padding: 5px;');
```

#### Vue DevTools

1. Install browser extension
2. Open DevTools
3. Navigate to "Vue" tab
4. Inspect components, state, and events

---

## Common Development Tasks

### Adding a New Page

1. **Create PHP file:**
   ```bash
   touch streams.php
   ```

2. **Add route in `public/index.php`:**
   ```php
   case '/streams':
       require __DIR__.'/../streams.php';
       break;
   ```

3. **Create view:**
   ```bash
   touch views/streams/index.blade.php
   ```

4. **Use in PHP file:**
   ```php
   <?php
   require 'config.php';

   $streams = Stream::all();

   echo $template->view()->make('streams.index', [
       'streams' => $streams
   ])->render();
   ```

### Adding a Vue Component

1. **Create component:**
   ```bash
   touch resources/js/components/StreamCard.vue
   ```

2. **Component code:**
   ```vue
   <template>
       <div class="stream-card">
           <h3>{{ stream.name }}</h3>
           <p>{{ stream.url }}</p>
       </div>
   </template>

   <script>
   export default {
       props: ['stream'],
   }
   </script>

   <style scoped>
   .stream-card {
       padding: 1rem;
       border: 1px solid #ccc;
       border-radius: 0.5rem;
   }
   </style>
   ```

3. **Import in app.js** (auto-imported via glob)

4. **Use in template:**
   ```html
   <div id="app">
       <StreamCard :stream="streamData" />
   </div>
   ```

### Adding Tailwind Classes

Tailwind is already configured. Use utility classes directly:

```html
<div class="bg-white rounded-lg shadow-md p-6">
    <h1 class="text-2xl font-bold mb-4">Title</h1>
    <button class="btn btn-primary">Click Me</button>
</div>
```

Custom classes are defined in `resources/css/app.css`.

### Using Axios for API Calls

```javascript
// In Vue component or JS file
import axios from 'axios';

// GET request
axios.get('/api/streams')
    .then(response => {
        console.log('Streams:', response.data);
    })
    .catch(error => {
        console.error('Error:', error);
    });

// POST request
axios.post('/api/streams', {
    name: 'New Stream',
    url: 'rtmp://example.com/live'
})
    .then(response => {
        console.log('Created:', response.data);
    });
```

---

## Troubleshooting

### Port Already in Use

```bash
# PHP server
php artisan serve --port=9000

# Or find and kill process
lsof -ti:8000 | xargs kill -9

# Vite
npm run dev
# Vite automatically finds next available port
```

### Composer Autoload Issues

```bash
# Regenerate autoload
composer dump-autoload

# Clear cache
rm -rf cache/*
rm -rf vendor/
composer install
```

### NPM Install Fails

```bash
# Clear npm cache
npm cache clean --force

# Delete node_modules
rm -rf node_modules package-lock.json

# Reinstall
npm install
```

### Database Connection Failed

```bash
# Check MySQL is running
sudo systemctl status mysql
# or
sudo systemctl status mariadb

# Test connection
mysql -u root -p -h 127.0.0.1

# Verify .env settings
grep DB_ .env
```

### Assets Not Loading

```bash
# Ensure Vite is running
npm run dev

# Check Vite URL in browser
http://localhost:5173

# Rebuild assets
npm run build
```

### Permission Errors

```bash
# Fix storage permissions
chmod -R 775 storage
chmod -R 775 cache

# Fix ownership
sudo chown -R $USER:www-data storage
sudo chown -R $USER:www-data cache
```

---

## Performance Tips

### Development Performance

1. **Use file cache driver** (not Redis) for faster iteration
2. **Disable query logging** if not needed
3. **Use `--no-dev` for testing production** builds
4. **Clear cache regularly:**
   ```bash
   rm -rf cache/*
   rm -rf storage/framework/cache/*
   ```

### Frontend Performance

1. **HMR is fast** - use `npm run dev` instead of `npm run build`
2. **Lazy load components** for faster initial load
3. **Use production build** (`npm run build`) only for final testing

---

## Production vs Development

### Key Differences

| Feature | Development | Production |
|---------|------------|------------|
| APP_ENV | local | production |
| APP_DEBUG | true | false |
| Cache | file | redis |
| Assets | Vite HMR | Built files |
| Logging | debug | info/warning |
| Server | php artisan serve | Nginx/Apache |

### Testing Production Build Locally

```bash
# Build assets
npm run build

# Set production environment
cp .env .env.backup
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

# Test
php artisan serve

# Restore development
mv .env.backup .env
```

---

## Next Steps

### After Setup

1. **Read the guides:**
   - [Laravel Components Usage](LARAVEL_COMPONENTS_USAGE.md)
   - [Environment Configuration](ENV_CONFIGURATION_GUIDE.md)
   - [Security Features](SECURITY_FEATURES.md)

2. **Explore the codebase:**
   - Models in `/models`
   - Views in `/views`
   - Frontend in `/resources`

3. **Start developing:**
   - Create features
   - Add API endpoints
   - Build UI components

### Resources

- **Project Documentation:** `/docs/README.md`
- **Laravel Docs:** https://laravel.com/docs
- **Vue.js Docs:** https://vuejs.org/guide/
- **Tailwind Docs:** https://tailwindcss.com/docs
- **Vite Docs:** https://vitejs.dev/guide/

---

## Summary

### Development Commands

```bash
# Start development
npm run dev          # Terminal 1: Frontend
php artisan serve    # Terminal 2: Backend

# Access application
http://localhost:8000

# Build production assets
npm run build
```

### File Locations

- **Frontend:** `resources/js` and `resources/css`
- **Backend:** Root `.php` files and `models/`
- **Views:** `views/`
- **Config:** `.env` and `config.php`
- **Public:** `public/` (built assets)

### Support

- **Documentation:** `/docs`
- **Issues:** GitHub Issues
- **Logs:** `storage/logs/`

---

**Happy Coding!** 🚀

---

**Last Updated:** 2025-11-22
**Version:** v70
**Status:** Ready for Development
