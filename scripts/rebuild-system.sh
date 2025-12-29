#!/usr/bin/env bash

# FOS-Streaming Enhanced System Rebuild Script
# This script handles proper cache management with correct ownership and permissions
# to prevent 403 Forbidden errors in production environments.

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

debug() {
    echo -e "${BLUE}[DEBUG]${NC} $1"
}

# Exit immediately if a command exits with a non-zero status.
set -e

echo "🚀 Starting Enhanced FOS-Streaming System Rebuild..."
echo "=================================================="

# Read .env file for cache and redis settings
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
    info "Environment variables loaded from .env"
else
    warn ".env file not found, using system defaults"
fi

# Detect users and groups
detect_users() {
    info "Detecting system users and groups..."
    
    # Get current user (application owner)
    CURRENT_USER=$(whoami)
    CURRENT_GROUP=$(groups $CURRENT_USER | cut -d' ' -f1)
    
    # Detect web server user
    if id nginx &>/dev/null; then
        WEB_USER="nginx"
    elif id www-data &>/dev/null; then
        WEB_USER="www-data"
    elif id apache &>/dev/null; then
        WEB_USER="apache"
    else
        WEB_USER="www-data"  # Default fallback
        warn "Could not detect web server user, using www-data as default"
    fi
    
    # Detect web server group
    WEB_GROUP=$(id -gn $WEB_USER 2>/dev/null || echo "$WEB_USER")
    
    # For production deployments, prefer fosadmin user
    if id fosadmin &>/dev/null; then
        APP_USER="fosadmin"
        APP_GROUP="fosadmin"
    else
        APP_USER="$CURRENT_USER"
        APP_GROUP="$CURRENT_GROUP"
    fi
    
    debug "Current user: $CURRENT_USER ($CURRENT_GROUP)"
    debug "Application user: $APP_USER ($APP_GROUP)"
    debug "Web server user: $WEB_USER ($WEB_GROUP)"
}

# Ensure proper directory structure exists
ensure_directories() {
    info "Ensuring required directories exist..."
    
    # Create cache directories if they don't exist
    local cache_dirs=(
        "cache"
        "storage/framework/cache/data"
        "storage/framework/sessions"
        "storage/framework/views"
        "storage/logs"
        "bootstrap/cache"
    )
    
    for dir in "${cache_dirs[@]}"; do
        if [ ! -d "$dir" ]; then
            mkdir -p "$dir"
            info "Created directory: $dir"
        fi
    done
}

# Set proper ownership for all relevant directories
set_ownership() {
    info "Setting proper ownership for directories and files..."
    
    # Directories that should be owned by application user but writable by web server
    local app_dirs=(
        "cache"
        "storage"
        "bootstrap/cache"
        "public"
    )
    
    # Set ownership for application directories
    for dir in "${app_dirs[@]}"; do
        if [ -d "$dir" ]; then
            chown -R $APP_USER:$APP_GROUP "$dir"
            info "Set ownership of $dir to $APP_USER:$APP_GROUP"
        fi
    done
    
    # Set ownership for vendor directory (read-only for web server)
    if [ -d "vendor" ]; then
        chown -R $APP_USER:$APP_GROUP "vendor"
        chmod -R 755 vendor
        info "Set ownership of vendor directory to $APP_USER:$APP_GROUP"
    fi
}

# Set secure but functional permissions
set_permissions() {
    info "Setting secure permissions..."
    
    # Cache directories: owner can read/write/execute, group can read/execute, others can read
    find cache -type d -exec chmod 775 {} \; 2>/dev/null || true
    find cache -type f -exec chmod 664 {} \; 2>/dev/null || true
    
    # Storage directories: owner can read/write/execute, group can read/write/execute, others no access
    find storage -type d -exec chmod 775 {} \; 2>/dev/null || true
    find storage -type f -exec chmod 664 {} \; 2>/dev/null || true
    
    # Bootstrap cache: same as storage
    if [ -d "bootstrap/cache" ]; then
        find bootstrap/cache -type d -exec chmod 775 {} \; 2>/dev/null || true
        find bootstrap/cache -type f -exec chmod 664 {} \; 2>/dev/null || true
    fi
    
    # Public directory: owner can read/write/execute, group can read/execute, others can read
    if [ -d "public" ]; then
        find public -type d -exec chmod 755 {} \; 2>/dev/null || true
        find public -type f -exec chmod 644 {} \; 2>/dev/null || true
    fi
    
    # Log files: owner and group can read/write, others no access
    if [ -d "storage/logs" ]; then
        chmod 775 storage/logs
        find storage/logs -type f -exec chmod 664 {} \; 2>/dev/null || true
    fi
    
    info "Permissions set successfully"
}

# Clear all caches with proper handling
clear_caches() {
    info "🧹 Clearing all caches..."
    
    # Clear application cache using artisan if available
    if php artisan list 2>/dev/null | grep -q "cache:clear"; then
        php artisan cache:clear 2>/dev/null || true
        info "✅ Application cache cleared via artisan"
    else
        # Manual cache clearing fallback
        php -r "array_map('unlink', glob('cache/*.php'));"
        info "✅ Application cache cleared manually"
    fi
    
    # Clear config cache
    if php artisan list 2>/dev/null | grep -q "config:clear"; then
        php artisan config:clear 2>/dev/null || true
        info "✅ Config cache cleared via artisan"
    fi
    
    # Clear route cache
    if php artisan list 2>/dev/null | grep -q "route:clear"; then
        php artisan route:clear 2>/dev/null || true
        info "✅ Route cache cleared via artisan"
    fi
    
    # Clear view cache
    if php artisan list 2>/dev/null | grep -q "view:clear"; then
        php artisan view:clear 2>/dev/null || true
        info "✅ View cache cleared via artisan"
    fi
    
    # Manual fallback for cache files
    local cache_files=(
        "bootstrap/cache/config.php"
        "bootstrap/cache/routes.php"
        "bootstrap/cache/packages.php"
        "bootstrap/cache/events.php"
    )
    
    for file in "${cache_files[@]}"; do
        if [ -f "$file" ]; then
            rm -f "$file"
            info "✅ Removed cache file: $file"
        fi
    done
    
    # Clear framework cache data
    if [ -d "storage/framework/cache/data" ]; then
        find storage/framework/cache/data -mindepth 2 -maxdepth 2 -type f -delete 2>/dev/null || true
        info "✅ Framework cache files cleared"
    fi
    
    # Clear Redis cache if configured
    if [ -n "$REDIS_HOST" ] && [ -n "$REDIS_PREFIX" ]; then
        info "🔄 Flushing Redis cache for prefix: '${REDIS_PREFIX}'"
        redis-cli --scan --pattern "${REDIS_PREFIX}*" 2>/dev/null | xargs -r redis-cli del 2>/dev/null || true
        info "✅ Redis cache flushed"
    else
        info "ℹ️ Redis configuration not found, skipping Redis cache clearing"
    fi
}

# Rebuild caches with proper permissions
rebuild_caches() {
    info "⚙️ Rebuilding caches..."
    
    # Rebuild config cache
    if php artisan list 2>/dev/null | grep -q "config:cache"; then
        php artisan config:cache 2>/dev/null || true
        info "✅ Config cache rebuilt"
    fi
    
    # Rebuild route cache
    if php artisan list 2>/dev/null | grep -q "route:cache"; then
        php artisan route:cache 2>/dev/null || true
        info "✅ Route cache rebuilt"
    fi
    
    # Create cache rebuild marker
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Enhanced cache rebuild completed" > "cache/rebuild_$(date '+%Y%m%d_%H%M%S').txt"
    info "✅ Cache rebuild marker created"
}

# Final permission and ownership fix
final_fix() {
    info "🔧 Applying final ownership and permission fixes..."
    
    # Ensure cache directories have correct ownership and permissions
    local critical_dirs=(
        "cache"
        "storage/framework/cache/data"
        "storage/framework/sessions"
        "storage/framework/views"
        "bootstrap/cache"
    )
    
    for dir in "${critical_dirs[@]}"; do
        if [ -d "$dir" ]; then
            # Set ownership
            chown -R $APP_USER:$WEB_GROUP "$dir" 2>/dev/null || chown -R $APP_USER:$APP_GROUP "$dir"
            
            # Set permissions
            find "$dir" -type d -exec chmod 775 {} \; 2>/dev/null || true
            find "$dir" -type f -exec chmod 664 {} \; 2>/dev/null || true
            
            info "✅ Fixed permissions for $dir"
        fi
    done
    
    # Fix log directory specifically
    if [ -d "storage/logs" ]; then
        chown -R $APP_USER:$WEB_GROUP storage/logs 2>/dev/null || chown -R $APP_USER:$APP_GROUP storage/logs
        chmod 775 storage/logs
        find storage/logs -type f -exec chmod 664 {} \; 2>/dev/null || true
        info "✅ Fixed permissions for logs directory"
    fi
}

# Validate cache accessibility
validate_cache() {
    info "🔍 Validating cache accessibility..."
    
    # Test if web server can read cache files
    local test_file="cache/.permission_test"
    echo "test" > "$test_file"
    
    if sudo -u $WEB_USER test -r "$test_file" 2>/dev/null; then
        info "✅ Cache is accessible by web server user ($WEB_USER)"
        rm -f "$test_file"
    else
        warn "⚠️ Cache may not be accessible by web server user ($WEB_USER)"
        warn "This might cause 403 Forbidden errors"
        rm -f "$test_file"
    fi
}

# Reload system services
reload_services() {
    info "🔄 Reloading system services..."
    
    # Reload nginx if available
    if command -v nginx >/dev/null 2>&1; then
        if sudo nginx -t >/dev/null 2>&1; then
            sudo systemctl reload nginx 2>/dev/null || sudo nginx -s reload 2>/dev/null || true
            info "✅ Nginx reloaded"
        else
            warn "⚠️ Nginx configuration test failed"
        fi
    fi
    
    # Reload PHP-FPM
    if command -v php-fpm >/dev/null 2>&1 || systemctl list-unit-files | grep -q php.*fpm; then
        sudo systemctl reload php8.4-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
        info "✅ PHP-FPM reloaded"
    fi
}

# Main execution
main() {
    echo "Starting enhanced rebuild process..."
    echo "Timestamp: $(date)"
    echo ""
    
    # Step 1: Detect users and setup
    detect_users
    echo ""
    
    # Step 2: Ensure directories exist
    ensure_directories
    echo ""
    
    # Step 3: Set initial ownership
    set_ownership
    echo ""
    
    # Step 4: Set initial permissions
    set_permissions
    echo ""
    
    # Step 5: Clear all caches
    clear_caches
    echo ""
    
    # Step 6: Rebuild caches
    rebuild_caches
    echo ""
    
    # Step 7: Apply final fixes
    final_fix
    echo ""
    
    # Step 8: Validate accessibility
    validate_cache
    echo ""
    
    # Step 9: Reload services
    reload_services
    echo ""
    
    echo "=================================================="
    echo "🎉 Enhanced System Rebuild Completed Successfully!"
    echo "=================================================="
    echo ""
    echo "Summary:"
    echo "  Application User: $APP_USER"
    echo "  Web Server User: $WEB_USER"
    echo "  Cache directories secured and accessible"
    echo "  All caches cleared and rebuilt"
    echo "  System services reloaded"
    echo ""
    echo "If you still experience 403 errors, check:"
    echo "  1. Web server error logs: sudo tail -f /var/log/nginx/error.log"
    echo "  2. PHP-FPM error logs: sudo tail -f /var/log/php8.4-fpm.log"
    echo "  3. Application logs: storage/logs/"
    echo ""
}

# Run main function
main "$@"