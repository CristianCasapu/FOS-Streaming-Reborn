#!/bin/bash
# unprotect-configs.sh
# Run this script to allow git pull to update config files again
# Usage: bash install/scripts/unprotect-configs.sh

set -e

echo "=== FOS-Streaming Config Unprotect Script ==="
echo ""

# Get the project root directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

cd "$PROJECT_ROOT"

# List of config files to unprotect
CONFIG_FILES=(
    # FOS Pack nginx configs
    "fospackv69/fos/nginx/conf/nginx.conf"
    "fospackv69/fos/nginx/conf/nginx-streaming.conf"

    # FOS Pack PHP-FPM configs
    "fospackv69/fos/php/etc/php-fpm.conf"
    "fospackv69/fos/php/etc/php-fpm-streaming.conf"
    "fospackv69/fos/php/etc/pool.d/www.conf"

    # Install config templates (nginx)
    "install/config/nginx/nginx.conf"
    "install/config/nginx/fos-admin.conf"
    "install/config/nginx/fos-streaming.conf"
    "install/config/nginx/fos-rtmp.conf"

    # Install config templates (PHP-FPM)
    "install/config/php-fpm/fos-admin.conf"
    "install/config/php-fpm/fos-streaming.conf"
)

echo "Removing protection from configuration files..."
echo ""

unprotected_count=0

for file in "${CONFIG_FILES[@]}"; do
    if [ -f "$file" ]; then
        git update-index --no-skip-worktree "$file" 2>/dev/null && {
            echo "  [OK] $file"
            ((unprotected_count++))
        } || {
            echo "  [SKIP] $file (not tracked or not protected)"
        }
    fi
done

echo ""
echo "=== Summary ==="
echo "Unprotected: $unprotected_count files"
echo ""
echo "These files will now be updated during 'git pull'."
echo ""
echo "WARNING: Your local changes may be overwritten!"
echo "Consider backing up modified configs before pulling."
