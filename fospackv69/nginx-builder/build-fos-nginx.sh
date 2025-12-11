#!/bin/bash
# =============================================================================
# FOS-Streaming Nginx Build Script
# =============================================================================
# Builds nginx with HTTP-FLV module for RTMP/HLS streaming support
#
# Supported OS: Debian 12 (Bookworm), Ubuntu 24.04 LTS (Noble)
# Nginx Version: 1.26.2 (mainline)
#
# Usage:
#   sudo bash build-fos-nginx.sh [OPTIONS]
#
# Options:
#   --fos-dir DIR      FOS-Streaming installation directory
#   --user USER        User to run nginx as (default: current user)
#   --clean            Clean build artifacts before building
#   --help             Show this help message
#
# Example:
#   sudo bash build-fos-nginx.sh --fos-dir /home/fosadmin/FOS-Streaming --user fosadmin
# =============================================================================

set -e

# =============================================================================
# Configuration
# =============================================================================
NGINX_VERSION="1.26.2"

# Get script directory (works with sudo and symlinks)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Resolve symlinks to get real path
SCRIPT_DIR="$(cd "$SCRIPT_DIR" && pwd -P)"
BUILD_DIR="${SCRIPT_DIR}"
NGINX_SRC_DIR="${BUILD_DIR}/nginx-${NGINX_VERSION}"

# Default values (can be overridden by command line args)
FOS_DIR=""
FOS_USER="${SUDO_USER:-$(whoami)}"
FOS_GROUP="${FOS_USER}"
CLEAN_BUILD=false

# Auto-detect FOS_USER's home directory (works with sudo)
if [ -n "$SUDO_USER" ]; then
    FOS_USER_HOME=$(getent passwd "$SUDO_USER" | cut -d: -f6)
else
    FOS_USER_HOME="$HOME"
fi

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# =============================================================================
# Functions
# =============================================================================
log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step() { echo -e "\n${BLUE}===${NC} $1 ${BLUE}===${NC}\n"; }

show_help() {
    head -30 "$0" | tail -25
    exit 0
}

detect_fos_dir() {
    # Try to auto-detect FOS directory
    if [ -z "$FOS_DIR" ]; then
        # Method 1: Script is in fospackv69/nginx-builder, go up two levels
        local parent_dir="$(dirname "$(dirname "$SCRIPT_DIR")")"
        if [ -f "${parent_dir}/config.php" ] && [ -f "${parent_dir}/composer.json" ]; then
            FOS_DIR="$parent_dir"
            log_info "Auto-detected FOS directory from script location"
        else
            # Method 2: Look for common installation paths
            local common_paths=(
                "${FOS_USER_HOME}/FOS-Streaming"
                "${FOS_USER_HOME}/FOS-Streaming-v69"
                "${FOS_USER_HOME}/fos-streaming"
                "/opt/FOS-Streaming"
                "/var/www/FOS-Streaming"
            )

            for path in "${common_paths[@]}"; do
                if [ -f "${path}/config.php" ] && [ -f "${path}/composer.json" ]; then
                    FOS_DIR="$path"
                    log_info "Found FOS directory at: $path"
                    break
                fi
            done
        fi

        if [ -z "$FOS_DIR" ]; then
            log_error "Could not auto-detect FOS directory."
            log_error "Use --fos-dir option to specify the installation path."
            log_error "Example: sudo bash $0 --fos-dir /home/${FOS_USER}/FOS-Streaming"
            exit 1
        fi
    fi

    # Verify FOS directory
    if [ ! -f "${FOS_DIR}/config.php" ]; then
        log_error "Invalid FOS directory: ${FOS_DIR}"
        log_error "config.php not found"
        exit 1
    fi
}

check_existing_nginx() {
    # Check if there's an existing nginx binary with wrong paths
    local existing_nginx="${FOS_DIR}/fospackv69/fos/nginx/sbin/nginx"

    if [ -f "$existing_nginx" ]; then
        log_info "Found existing nginx binary"

        # Check if it has paths that don't match current FOS_DIR
        local binary_paths=$("$existing_nginx" -V 2>&1 | grep -oP '(?<=--prefix=|--error-log-path=|--http-log-path=)[^\s]+' | head -1)

        if [ -n "$binary_paths" ] && [[ "$binary_paths" != "${FOS_DIR}"* ]]; then
            log_warn "Existing nginx binary has incorrect paths hardcoded!"
            log_warn "Binary expects: $binary_paths"
            log_warn "Your FOS_DIR:   ${FOS_DIR}"
            log_warn ""
            log_warn "This happens when using a pre-built binary from another machine."
            log_warn "The nginx binary MUST be compiled on each server with correct paths."
            log_info ""
            log_info "Proceeding with rebuild to fix this..."
            echo ""
        fi
    fi
}

check_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_ID="$ID"
        OS_VERSION="$VERSION_ID"
        OS_CODENAME="$VERSION_CODENAME"
    else
        log_error "Cannot detect OS. /etc/os-release not found."
        exit 1
    fi

    case "$OS_ID" in
        debian)
            if [[ "$OS_VERSION" != "12" ]]; then
                log_warn "This script is tested on Debian 12. You have Debian $OS_VERSION"
            fi
            ;;
        ubuntu)
            if [[ "$OS_VERSION" != "24.04" ]]; then
                log_warn "This script is tested on Ubuntu 24.04. You have Ubuntu $OS_VERSION"
            fi
            ;;
        *)
            log_error "Unsupported OS: $OS_ID"
            log_error "Supported: Debian 12, Ubuntu 24.04"
            exit 1
            ;;
    esac

    log_info "Detected OS: $OS_ID $OS_VERSION ($OS_CODENAME)"
}

# =============================================================================
# Parse Arguments
# =============================================================================
while [[ $# -gt 0 ]]; do
    case $1 in
        --fos-dir)
            FOS_DIR="$2"
            shift 2
            ;;
        --user)
            FOS_USER="$2"
            FOS_GROUP="$2"
            shift 2
            ;;
        --clean)
            CLEAN_BUILD=true
            shift
            ;;
        --help|-h)
            show_help
            ;;
        *)
            log_error "Unknown option: $1"
            show_help
            ;;
    esac
done

# =============================================================================
# Pre-flight Checks
# =============================================================================
echo -e "${CYAN}"
echo "============================================================================="
echo "  FOS-Streaming Nginx Build Script"
echo "============================================================================="
echo -e "${NC}"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    log_error "This script must be run as root (use sudo)"
    exit 1
fi

check_os
detect_fos_dir
check_existing_nginx

# Set paths based on FOS_DIR
INSTALL_PREFIX="${FOS_DIR}/fospackv69/fos/nginx"
LOG_DIR="${FOS_DIR}/logs"
HLS_DIR="${FOS_DIR}/hl"
STREAMS_DIR="${FOS_DIR}/fospackv69/fos/streams"

log_info "Build Configuration:"
log_info "  FOS Directory:   ${FOS_DIR}"
log_info "  Install Prefix:  ${INSTALL_PREFIX}"
log_info "  User/Group:      ${FOS_USER}:${FOS_GROUP}"
log_info "  Nginx Version:   ${NGINX_VERSION}"
log_info "  Log Directory:   ${LOG_DIR}"

# =============================================================================
# Step 1: Install Build Dependencies
# =============================================================================
log_step "Installing Build Dependencies"

apt-get update -y

DEPS=(
    build-essential
    libssl-dev
    libpcre3
    libpcre3-dev
    libpcre2-dev
    zlib1g-dev
    libxslt1-dev
    libgd-dev
    libgeoip-dev
    libxml2-dev
    wget
    git
    curl
    ca-certificates
)

for dep in "${DEPS[@]}"; do
    if dpkg -l "$dep" &>/dev/null; then
        echo "  ✓ $dep (already installed)"
    else
        echo "  → Installing $dep..."
        apt-get install -y "$dep" >/dev/null 2>&1
    fi
done

log_info "Build dependencies installed"

# =============================================================================
# Step 2: Download Nginx Source
# =============================================================================
log_step "Preparing Nginx Source"

cd "${BUILD_DIR}"

if [ "$CLEAN_BUILD" = true ] && [ -d "${NGINX_SRC_DIR}" ]; then
    log_info "Cleaning previous build..."
    rm -rf "${NGINX_SRC_DIR}"
fi

if [ ! -d "${NGINX_SRC_DIR}" ]; then
    log_info "Downloading nginx ${NGINX_VERSION}..."
    wget -q "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -O "nginx-${NGINX_VERSION}.tar.gz"
    tar -xzf "nginx-${NGINX_VERSION}.tar.gz"
    rm -f "nginx-${NGINX_VERSION}.tar.gz"
    log_info "Nginx source extracted"
else
    log_info "Nginx source directory already exists"
fi

# =============================================================================
# Step 3: Prepare Modules
# =============================================================================
log_step "Preparing Nginx Modules"

MODS_DIR="${BUILD_DIR}/mods"
mkdir -p "${MODS_DIR}"

# nginx-http-flv-module (RTMP + HTTP-FLV support)
if [ ! -d "${MODS_DIR}/nginx-http-flv-module" ]; then
    log_info "Cloning nginx-http-flv-module..."
    cd "${MODS_DIR}"
    git clone --depth 1 https://github.com/winshining/nginx-http-flv-module.git
else
    log_info "nginx-http-flv-module already exists"
    cd "${MODS_DIR}/nginx-http-flv-module"
    git pull --quiet 2>/dev/null || true
fi

# headers-more-nginx-module
if [ ! -d "${MODS_DIR}/headers-more-nginx-module" ]; then
    log_info "Cloning headers-more-nginx-module..."
    cd "${MODS_DIR}"
    git clone --depth 1 https://github.com/openresty/headers-more-nginx-module.git
else
    log_info "headers-more-nginx-module already exists"
fi

# ngx_devel_kit (required by some modules)
if [ ! -d "${MODS_DIR}/ngx_devel_kit" ]; then
    log_info "Cloning ngx_devel_kit..."
    cd "${MODS_DIR}"
    git clone --depth 1 https://github.com/vision5/ngx_devel_kit.git
else
    log_info "ngx_devel_kit already exists"
fi

# =============================================================================
# Step 4: Configure Nginx
# =============================================================================
log_step "Configuring Nginx Build"

cd "${NGINX_SRC_DIR}"

# Clean previous build if exists
if [ -f "Makefile" ]; then
    make clean 2>/dev/null || true
fi

./configure \
    --prefix="${INSTALL_PREFIX}" \
    --user="${FOS_USER}" \
    --group="${FOS_GROUP}" \
    --sbin-path="${INSTALL_PREFIX}/sbin/nginx" \
    --conf-path="${INSTALL_PREFIX}/conf/nginx.conf" \
    --pid-path="${INSTALL_PREFIX}/logs/nginx.pid" \
    --lock-path="${INSTALL_PREFIX}/logs/nginx.lock" \
    --error-log-path="${LOG_DIR}/nginx-error.log" \
    --http-log-path="${LOG_DIR}/nginx-access.log" \
    --http-client-body-temp-path="${INSTALL_PREFIX}/client_body_temp" \
    --http-fastcgi-temp-path="${INSTALL_PREFIX}/fastcgi_temp" \
    --http-proxy-temp-path="${INSTALL_PREFIX}/proxy_temp" \
    --http-scgi-temp-path="${INSTALL_PREFIX}/scgi_temp" \
    --http-uwsgi-temp-path="${INSTALL_PREFIX}/uwsgi_temp" \
    --with-pcre \
    --with-pcre-jit \
    --with-file-aio \
    --with-threads \
    --with-http_ssl_module \
    --with-http_v2_module \
    --with-http_realip_module \
    --with-http_addition_module \
    --with-http_sub_module \
    --with-http_flv_module \
    --with-http_mp4_module \
    --with-http_gunzip_module \
    --with-http_gzip_static_module \
    --with-http_random_index_module \
    --with-http_secure_link_module \
    --with-http_stub_status_module \
    --with-http_auth_request_module \
    --with-http_slice_module \
    --with-stream \
    --with-stream_ssl_module \
    --with-stream_realip_module \
    --with-stream_ssl_preread_module \
    --with-compat \
    --with-cc-opt='-O2 -fstack-protector-strong -Wformat -Werror=format-security -fPIC' \
    --with-ld-opt='-Wl,-z,relro -Wl,-z,now -Wl,--as-needed -pie' \
    --add-module="${MODS_DIR}/ngx_devel_kit" \
    --add-module="${MODS_DIR}/nginx-http-flv-module" \
    --add-module="${MODS_DIR}/headers-more-nginx-module"

log_info "Configuration complete"

# =============================================================================
# Step 5: Compile Nginx
# =============================================================================
log_step "Compiling Nginx (this may take several minutes)"

CPUS=$(nproc)
log_info "Using ${CPUS} CPU cores for compilation"

make -j${CPUS}

log_info "Compilation complete"

# =============================================================================
# Step 6: Install Nginx
# =============================================================================
log_step "Installing Nginx"

# Check if nginx is currently running and stop it
NGINX_RUNNING=false
if pgrep -f "${INSTALL_PREFIX}/sbin/nginx" > /dev/null 2>&1; then
    NGINX_RUNNING=true
    log_warn "Nginx is currently running - stopping for upgrade..."

    # Try systemd first, then direct kill
    if systemctl is-active --quiet fos-nginx 2>/dev/null; then
        systemctl stop fos-nginx
    else
        "${INSTALL_PREFIX}/sbin/nginx" -s stop 2>/dev/null || true
        sleep 2
        pkill -f "${INSTALL_PREFIX}/sbin/nginx" 2>/dev/null || true
    fi

    log_info "Nginx stopped"
fi

# Backup existing binary if present
if [ -f "${INSTALL_PREFIX}/sbin/nginx" ]; then
    BACKUP_NAME="nginx.backup.$(date +%Y%m%d%H%M%S)"
    log_info "Backing up existing binary to ${BACKUP_NAME}"
    cp "${INSTALL_PREFIX}/sbin/nginx" "${INSTALL_PREFIX}/sbin/${BACKUP_NAME}"
fi

# Backup existing config if present (don't overwrite user customizations)
if [ -f "${INSTALL_PREFIX}/conf/nginx.conf" ]; then
    CONFIG_BACKUP="nginx.conf.backup.$(date +%Y%m%d%H%M%S)"
    log_info "Backing up existing config to ${CONFIG_BACKUP}"
    cp "${INSTALL_PREFIX}/conf/nginx.conf" "${INSTALL_PREFIX}/conf/${CONFIG_BACKUP}"
fi

make install

# Create symbolic link for nginx_fos
rm -f "${INSTALL_PREFIX}/sbin/nginx_fos"
ln -sf "${INSTALL_PREFIX}/sbin/nginx" "${INSTALL_PREFIX}/sbin/nginx_fos"

log_info "Nginx installed to ${INSTALL_PREFIX}"

# =============================================================================
# Step 7: Create Directories
# =============================================================================
log_step "Creating Required Directories"

DIRS=(
    "${INSTALL_PREFIX}/client_body_temp"
    "${INSTALL_PREFIX}/fastcgi_temp"
    "${INSTALL_PREFIX}/proxy_temp"
    "${INSTALL_PREFIX}/scgi_temp"
    "${INSTALL_PREFIX}/uwsgi_temp"
    "${INSTALL_PREFIX}/logs"
    "${LOG_DIR}"
    "${HLS_DIR}"
    "${STREAMS_DIR}/hls"
    "${STREAMS_DIR}/dash"
)

for dir in "${DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        log_info "Created: $dir"
    fi
done

# =============================================================================
# Step 8: Set Permissions
# =============================================================================
log_step "Setting Permissions"

chown -R "${FOS_USER}:${FOS_GROUP}" "${INSTALL_PREFIX}"
chown -R "${FOS_USER}:${FOS_GROUP}" "${LOG_DIR}"
chown -R "${FOS_USER}:${FOS_GROUP}" "${HLS_DIR}"
chown -R "${FOS_USER}:${FOS_GROUP}" "${STREAMS_DIR}"

# Make nginx executable
chmod 755 "${INSTALL_PREFIX}/sbin/nginx"
chmod 755 "${INSTALL_PREFIX}/sbin/nginx_fos"

log_info "Permissions set for user ${FOS_USER}"

# =============================================================================
# Step 9: Verify Installation
# =============================================================================
log_step "Verifying Installation"

echo ""
"${INSTALL_PREFIX}/sbin/nginx" -V 2>&1 | head -20
echo ""

# Check for required modules
if "${INSTALL_PREFIX}/sbin/nginx" -V 2>&1 | grep -q "nginx-http-flv-module"; then
    log_info "✓ HTTP-FLV module installed (RTMP/HLS streaming ready)"
else
    log_warn "✗ HTTP-FLV module not detected"
fi

if "${INSTALL_PREFIX}/sbin/nginx" -V 2>&1 | grep -q "headers-more"; then
    log_info "✓ Headers-more module installed"
else
    log_warn "✗ Headers-more module not detected"
fi

# =============================================================================
# Step 10: Restart Nginx (if it was running before)
# =============================================================================
if [ "$NGINX_RUNNING" = true ]; then
    log_step "Restarting Nginx"

    # Test config before starting
    if "${INSTALL_PREFIX}/sbin/nginx" -t 2>/dev/null; then
        if systemctl is-enabled --quiet fos-nginx 2>/dev/null; then
            systemctl start fos-nginx
            log_success "Nginx restarted via systemd"
        else
            "${INSTALL_PREFIX}/sbin/nginx"
            log_success "Nginx started directly"
        fi
    else
        log_error "Nginx config test failed - not restarting automatically"
        log_info "Fix the configuration and start manually"
    fi
fi

# =============================================================================
# Complete
# =============================================================================
echo ""
echo -e "${GREEN}=============================================================================${NC}"
echo -e "${GREEN}  Nginx Build Complete!${NC}"
echo -e "${GREEN}=============================================================================${NC}"
echo ""
echo "  Binary:      ${INSTALL_PREFIX}/sbin/nginx"
echo "  Symlink:     ${INSTALL_PREFIX}/sbin/nginx_fos"
echo "  Config:      ${INSTALL_PREFIX}/conf/nginx.conf"
echo "  Logs:        ${LOG_DIR}/"
echo "  HLS Output:  ${HLS_DIR}/"
echo ""

if [ "$NGINX_RUNNING" = true ]; then
    echo -e "${CYAN}Reinstall completed - nginx was automatically restarted${NC}"
    echo ""
else
    echo -e "${CYAN}Next Steps:${NC}"
    echo "  1. Copy configuration files (if not already done):"
    echo "     cp ${FOS_DIR}/install/config/nginx/nginx.conf ${INSTALL_PREFIX}/conf/"
    echo ""
    echo "  2. Update paths in configuration (replace placeholders):"
    echo "     sed -i 's/__FOS_USER__/${FOS_USER}/g; s|__FOS_DIR__|${FOS_DIR}|g' ${INSTALL_PREFIX}/conf/nginx.conf"
    echo ""
    echo "  3. Test configuration:"
    echo "     ${INSTALL_PREFIX}/sbin/nginx -t"
    echo ""
    echo "  4. Start nginx:"
    echo "     sudo systemctl start fos-nginx"
    echo ""
fi
echo -e "${GREEN}=============================================================================${NC}"
