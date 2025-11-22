# Development Environment Setup - Summary

## What Was Added

This document summarizes the development environment setup that enables `npm run dev` and `php artisan serve` for local development.

**Date:** 2025-11-22

---

## Files Created

### 1. Frontend Configuration

#### package.json
- **Location:** `/package.json`
- **Purpose:** NPM package configuration with Vite scripts
- **Scripts:**
  - `npm run dev` - Start Vite development server with HMR
  - `npm run build` - Build production assets
  - `npm run watch` - Build and watch for changes
  - `npm run preview` - Preview production build

#### vite.config.js
- **Location:** `/vite.config.js`
- **Purpose:** Vite bundler configuration
- **Features:**
  - Vue.js support
  - Development server on port 5173
  - Hot Module Replacement (HMR)
  - Build output to `public/build/`
  - Path aliases (@, ~)

#### postcss.config.js
- **Location:** `/postcss.config.js`
- **Purpose:** PostCSS configuration for Tailwind CSS
- **Plugins:** tailwindcss, autoprefixer

#### tailwind.config.js
- **Location:** `/tailwind.config.js`
- **Purpose:** Tailwind CSS configuration
- **Content:** Scans `.blade.php`, `.js`, `.vue`, `.php` files

### 2. Frontend Assets

#### resources/js/app.js
- **Location:** `/resources/js/app.js`
- **Purpose:** Main JavaScript entry point
- **Features:**
  - Vue 3 initialization
  - Auto-import Vue components
  - Axios configuration
  - CSRF token setup

#### resources/css/app.css
- **Location:** `/resources/css/app.css`
- **Purpose:** Main stylesheet with Tailwind
- **Includes:**
  - Tailwind base, components, utilities
  - Custom component classes (btn, card, form, table, alert)
  - Custom utilities
  - Scrollbar styling

### 3. Backend Configuration

#### app/Console/Commands/ServeCommand.php
- **Location:** `/app/Console/Commands/ServeCommand.php`
- **Purpose:** Artisan serve command implementation
- **Features:**
  - Start PHP built-in server
  - Configurable host and port
  - Automatic port finding
  - Serves from `/public` or project root

#### artisan (Updated)
- **Location:** `/artisan`
- **Change:** Registered ServeCommand
- **Usage:** `php artisan serve [--host=127.0.0.1] [--port=8000]`

#### public/index.php
- **Location:** `/public/index.php`
- **Purpose:** Front controller for PHP development server
- **Features:**
  - Routes requests to appropriate PHP files
  - Serves static files
  - Legacy compatibility with root PHP files

#### composer.json (Updated)
- **Location:** `/composer.json`
- **Change:** Added `App\\` PSR-4 autoload namespace
- **Maps:** `App\\` → `app/`

### 4. Documentation

#### docs/guides/LOCAL_DEVELOPMENT_GUIDE.md
- **Location:** `/docs/guides/LOCAL_DEVELOPMENT_GUIDE.md`
- **Purpose:** Comprehensive local development guide
- **Sections:**
  - Prerequisites
  - Quick start
  - Detailed setup
  - Development commands
  - Workflow
  - Troubleshooting
  - Performance tips

#### docs/guides/DEV_ENVIRONMENT_SETUP_SUMMARY.md
- **Location:** `/docs/guides/DEV_ENVIRONMENT_SETUP_SUMMARY.md`
- **Purpose:** This file - Quick reference summary

---

## Directory Structure

### New Directories

```
FOS-Streaming-v69/
├── resources/              # Frontend assets (NEW)
│   ├── js/
│   │   └── app.js         # Main JavaScript
│   ├── css/
│   │   └── app.css        # Main CSS with Tailwind
│   └── views/             # Additional views
│
├── public/                 # Public web root (NEW)
│   ├── index.php          # Front controller
│   ├── build/             # Built assets (gitignored)
│   └── .gitignore
│
└── app/                    # Application code (NEW)
    └── Console/
        └── Commands/
            └── ServeCommand.php
```

### Configuration Files

```
FOS-Streaming-v69/
├── package.json           # NPM configuration
├── vite.config.js         # Vite bundler config
├── postcss.config.js      # PostCSS config
├── tailwind.config.js     # Tailwind CSS config
├── composer.json          # Updated with App\\ namespace
└── artisan                # Updated with serve command
```

---

## How It Works

### Development Workflow

1. **Start Frontend (Terminal 1):**
   ```bash
   npm run dev
   ```
   - Starts Vite on http://localhost:5173
   - Watches `resources/js/` and `resources/css/`
   - Hot Module Replacement (HMR) for instant updates

2. **Start Backend (Terminal 2):**
   ```bash
   php artisan serve
   ```
   - Starts PHP server on http://localhost:8000
   - Serves from `/public` directory
   - Routes requests through `public/index.php`

3. **Access Application:**
   - Open http://localhost:8000 in browser
   - Vite assets loaded via http://localhost:5173
   - Changes update automatically

### Asset Loading

**Development Mode:**
```html
<!-- Vite serves assets with HMR -->
<script type="module" src="http://localhost:5173/@vite/client"></script>
<link rel="stylesheet" href="http://localhost:5173/resources/css/app.css">
<script type="module" src="http://localhost:5173/resources/js/app.js"></script>
```

**Production Mode:**
```bash
npm run build
```
Assets built to `public/build/` and served statically.

---

## NPM Scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Start Vite dev server with HMR |
| `npm run build` | Build production assets |
| `npm run watch` | Build and watch for changes |
| `npm run preview` | Preview production build |

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `php artisan serve` | Start dev server on localhost:8000 |
| `php artisan serve --host=0.0.0.0` | Serve on all interfaces |
| `php artisan serve --port=9000` | Use custom port |
| `php artisan list` | List all commands |
| `php artisan sail:install` | Install Laravel Sail |

---

## Dependencies

### NPM Packages (package.json)

**Dev Dependencies:**
- vite ^5.0.0
- @vitejs/plugin-vue ^5.0.0
- autoprefixer ^10.4.16
- postcss ^8.4.32
- tailwindcss ^3.4.0
- axios ^1.6.0
- laravel-vite-plugin ^1.0.0

**Dependencies:**
- vue ^3.4.0

### PHP Packages (composer.json)

**Autoload:**
- `App\\` → `app/` (PSR-4)
- `FOS\\Security\\` → `lib/` (PSR-4)
- Models (classmap)

---

## Configuration Details

### Vite Configuration

```javascript
// vite.config.js
{
  server: {
    host: '0.0.0.0',
    port: 5173,
    hmr: { host: 'localhost' }
  },
  build: {
    outDir: 'public/build',
    manifest: true
  },
  resolve: {
    alias: {
      '@': 'resources/js',
      '~': 'resources'
    }
  }
}
```

### Tailwind Configuration

```javascript
// tailwind.config.js
{
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
    "./views/**/*.blade.php",
    "./views/**/*.php",
    "./*.php"
  ]
}
```

---

## Environment Variables

### Development (.env)

```bash
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Cache (use file for development)
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file

# Logging
LOG_LEVEL=debug
```

### Production (.env)

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Cache (use redis for production)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Logging
LOG_LEVEL=info
```

---

## Usage Examples

### Starting Development

```bash
# Install dependencies (first time only)
composer install
npm install

# Start development servers
npm run dev          # Terminal 1
php artisan serve    # Terminal 2

# Access application
open http://localhost:8000
```

### Building for Production

```bash
# Build assets
npm run build

# Assets output to public/build/
ls -la public/build/
```

### Adding a Vue Component

```bash
# Create component
touch resources/js/components/MyComponent.vue
```

```vue
<!-- MyComponent.vue -->
<template>
    <div class="my-component">
        <h1>{{ title }}</h1>
    </div>
</template>

<script>
export default {
    props: ['title'],
}
</script>
```

Component auto-imported via glob pattern in `app.js`.

### Using Tailwind Classes

```html
<div class="card">
    <h1 class="card-header">Title</h1>
    <button class="btn btn-primary">Click Me</button>
</div>
```

Custom classes defined in `resources/css/app.css`.

---

## Troubleshooting

### Port Already in Use

```bash
# Change port
php artisan serve --port=9000

# Or kill process
lsof -ti:8000 | xargs kill -9
```

### Vite Not Loading

```bash
# Check Vite is running
npm run dev

# Test Vite directly
curl http://localhost:5173
```

### Composer Autoload Errors

```bash
# Regenerate autoload
composer dump-autoload
```

### NPM Install Fails

```bash
# Clear cache and reinstall
npm cache clean --force
rm -rf node_modules package-lock.json
npm install
```

---

## Next Steps

### After Setup

1. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

2. **Configure database:**
   ```bash
   cp .env.example .env
   nano .env
   ```

3. **Start developing:**
   ```bash
   npm run dev
   php artisan serve
   ```

### Read Documentation

- **Local Development Guide:** [LOCAL_DEVELOPMENT_GUIDE.md](LOCAL_DEVELOPMENT_GUIDE.md)
- **Laravel Components:** [LARAVEL_COMPONENTS_USAGE.md](LARAVEL_COMPONENTS_USAGE.md)
- **Environment Config:** [ENV_CONFIGURATION_GUIDE.md](ENV_CONFIGURATION_GUIDE.md)

---

## Summary

### ✅ What You Can Now Do

- Run `npm run dev` for frontend development with HMR
- Run `php artisan serve` for backend PHP server
- Develop with Vue 3 components
- Use Tailwind CSS utility classes
- Hot reload on file changes
- Build production assets with `npm run build`

### 📁 Key Directories

- `resources/` - Frontend source code
- `public/` - Public web root
- `app/` - Application code
- `docs/guides/` - Development guides

### 🚀 Quick Commands

```bash
npm run dev          # Start Vite
php artisan serve    # Start PHP server
npm run build        # Build for production
composer dump-autoload  # Regenerate autoload
```

---

**Setup Complete!** 🎉

You can now develop FOS-Streaming v70 locally with modern tools and hot reloading.

---

**Last Updated:** 2025-11-22
**Version:** v70
**Status:** Ready to Use
