#!/usr/bin/env bash

# Exit immediately if a command exits with a non-zero status.
set -e

# Read .env file for cache and redis settings.
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
fi

echo "🚀 Starting FOS-Streaming system rebuild..."

# 1. Clear All Standard Caches using Artisan (where available)
echo "🧹 Clearing standard Laravel caches..."

# Try artisan cache clear commands first
if php artisan list | grep -q "cache:clear"; then
    php artisan cache:clear
    echo "✅ Application cache cleared via artisan."
else
    echo "ℹ️ Artisan cache:clear not available, using manual cache clearing."
    php -r "array_map('unlink', glob('cache/*.php'));"
fi

if php artisan list | grep -q "config:clear"; then
    php artisan config:clear
    echo "✅ Config cache cleared via artisan."
fi

if php artisan list | grep -q "route:clear"; then
    php artisan route:clear
    echo "✅ Route cache cleared via artisan."
fi

if php artisan list | grep -q "view:clear"; then
    php artisan view:clear
    echo "✅ View cache cleared via artisan."
fi

# Manual fallback for any cache files that might not be cleared by artisan
if [ -f "bootstrap/cache/config.php" ]; then
    rm -f bootstrap/cache/config.php
    echo "✅ Config cache file removed manually."
fi

if [ -f "bootstrap/cache/routes.php" ]; then
    rm -f bootstrap/cache/routes.php
    echo "✅ Route cache file removed manually."
fi

if [ -d "storage/framework/cache/data" ]; then
    find storage/framework/cache/data -mindepth 2 -maxdepth 2 -type f -delete
    echo "✅ Framework cache files cleared manually."
fi

# 2. Clear Redis Cache (if configured)
if [ -n "$REDIS_PREFIX" ]; then
    echo "🔄 Flushing Redis cache for prefix: '${REDIS_PREFIX}'"
    redis-cli --scan --pattern "${REDIS_PREFIX}*" | xargs -r redis-cli del
    echo "✅ Redis cache flushed."
else
    echo "ℹ️ No REDIS_PREFIX found, skipping Redis cache clearing."
fi

# 3. Rebuild Frontend Assets
echo "📦 Installing npm dependencies and rebuilding assets..."
npm ci
npm run build
echo "✅ Frontend assets rebuilt."

# 4. Rebuild Caches using Artisan (where available)
echo "⚙️ Rebuilding Laravel caches..."

if php artisan list | grep -q "config:cache"; then
    php artisan config:cache
    echo "✅ Config cache rebuilt via artisan."
else
    echo "ℹ️ Artisan config:cache not available."
fi

if php artisan list | grep -q "route:cache"; then
    php artisan route:cache
    echo "✅ Route cache rebuilt via artisan."
else
    echo "ℹ️ Artisan route:cache not available."
fi

# Create Cache Rebuild Marker
echo "📝 Creating cache rebuild marker..."
php -r "file_put_contents('cache/rebuild_'.time().'.txt', date('Y-m-d H:i:s').' - Cache rebuilt\n');"
echo "✅ Cache rebuild marker created."

# 5. Reload System Services
echo "🔄 Reloading system services..."
sudo systemctl reload fos-nginx
sudo systemctl reload php8.4-fpm

echo "🎉 System rebuild completed successfully!"