#!/bin/bash
# protect-configs.sh
# Run this script AFTER deployment to prevent git pull from overwriting config files
# Usage: bash install/scripts/protect-configs.sh

set -e

echo "=== FOS-Streaming Config Protection Script ==="
echo ""

# Get the project root directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

cd "$PROJECT_ROOT"

# List of config files to protect
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

echo "Protecting configuration files from git pull overwrites..."
echo ""

protected_count=0
skipped_count=0

for file in "${CONFIG_FILES[@]}"; do
    if [ -f "$file" ]; then
        # Check if already protected
        if git ls-files -v "$file" 2>/dev/null | grep -q '^S'; then
            echo "  [SKIP] $file (already protected)"
            ((skipped_count++))
        else
            git update-index --skip-worktree "$file" 2>/dev/null && {
                echo "  [OK]   $file"
                ((protected_count++))
            } || {
                echo "  [WARN] $file (not tracked by git)"
            }
        fi
    else
        echo "  [MISS] $file (file not found)"
    fi
done

echo ""
echo "=== Summary ==="
echo "Protected: $protected_count files"
echo "Skipped:   $skipped_count files (already protected)"
echo ""
echo "These files will now be preserved during 'git pull'."
echo ""
echo "To UNPROTECT a file (allow git to update it):"
echo "  git update-index --no-skip-worktree <file>"
echo ""
echo "To see all protected files:"
echo "  git ls-files -v | grep '^S'"
