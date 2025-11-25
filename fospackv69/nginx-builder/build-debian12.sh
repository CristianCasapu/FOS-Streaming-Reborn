#!/bin/bash
# FOS-Streaming Nginx Build Script for Debian 12 (Bookworm)
# Updated for nginx 1.26.x (mainline) with modern security features
# Date: 2025-11-25

set -e  # Exit on error

NGINX_VERSION="1.26.2"
BUILD_DIR="$(pwd)"
NGINX_DIR="${BUILD_DIR}/nginx-${NGINX_VERSION}"
INSTALL_PREFIX="${FOS_NGINX_PREFIX:-/home/fos-streaming/fos/nginx}"
FOS_USER="${FOS_USER:-fosstreaming}"
FOS_GROUP="${FOS_GROUP:-fosstreaming}"
LOG_DIR="${FOS_LOG_DIR:-/home/fos-streaming/fos/logs}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    log_error "Please run as root"
    exit 1
fi

log_info "Starting nginx build for Debian 12 (Bookworm)"
log_info "Nginx version: ${NGINX_VERSION}"
log_info "Install prefix: ${INSTALL_PREFIX}"
log_info "User/Group: ${FOS_USER}:${FOS_GROUP}"

# ============================================================================
# Install Build Dependencies
# ============================================================================
log_info "Installing build dependencies..."

apt-get update -y

apt-get install -y \
    build-essential \
    libssl-dev \
    libpcre3 \
    libpcre3-dev \
    libxslt1-dev \
    zlib1g-dev \
    libgd-dev \
    libgeoip-dev \
    libxml2-dev \
    wget \
    git \
    curl

log_info "Build dependencies installed"

# Download nginx if not present
if [ ! -d "${NGINX_DIR}" ]; then
    log_info "Downloading nginx ${NGINX_VERSION}..."
    wget -q "http://nginx.org/download/nginx-${NGINX_VERSION}.tar.gz" -O "nginx-${NGINX_VERSION}.tar.gz"
    tar -xzf "nginx-${NGINX_VERSION}.tar.gz"
    rm "nginx-${NGINX_VERSION}.tar.gz"
else
    log_info "Nginx source directory already exists"
fi

# Update submodules
log_info "Updating nginx modules..."
cd "${BUILD_DIR}"
git submodule update --init --recursive

# Check if headers-more module exists, if not clone it
if [ ! -d "${BUILD_DIR}/mods/headers-more-nginx-module" ]; then
    log_info "Cloning headers-more-nginx-module..."
    cd "${BUILD_DIR}/mods"
    git clone https://github.com/openresty/headers-more-nginx-module.git
fi

# Check if nginx-http-flv-module exists, if not clone it
if [ ! -d "${BUILD_DIR}/mods/nginx-http-flv-module" ]; then
    log_info "Cloning nginx-http-flv-module (actively maintained, includes all RTMP features + HTTP-FLV)..."
    cd "${BUILD_DIR}/mods"
    git clone https://github.com/winshining/nginx-http-flv-module.git
fi

# Enter nginx directory
cd "${NGINX_DIR}"

log_info "Configuring nginx build..."

# Ensure user and group exist
if ! id "${FOS_USER}" &>/dev/null; then
    log_info "Creating user ${FOS_USER}..."
    useradd -r -s /sbin/nologin -d "${INSTALL_PREFIX%/nginx}" "${FOS_USER}" || true
fi

# Configure nginx with optimized settings for Debian 12
./configure \
--user="${FOS_USER}" \
--group="${FOS_GROUP}" \
--prefix="${INSTALL_PREFIX}" \
--sbin-path="${INSTALL_PREFIX}/sbin/nginx" \
--conf-path="${INSTALL_PREFIX}/conf/nginx.conf" \
--pid-path="${INSTALL_PREFIX}/pid/nginx.pid" \
--lock-path=/var/lock/nginx.lock \
--error-log-path="${LOG_DIR}/error.log" \
--http-log-path="${LOG_DIR}/access.log" \
--http-client-body-temp-path="${INSTALL_PREFIX}/client_body_temp" \
--http-fastcgi-temp-path="${INSTALL_PREFIX}/fastcgi_temp" \
--http-proxy-temp-path="${INSTALL_PREFIX}/proxy_temp" \
--http-scgi-temp-path="${INSTALL_PREFIX}/scgi_temp" \
--http-uwsgi-temp-path="${INSTALL_PREFIX}/uwsgi_temp" \
--with-pcre \
--with-pcre-jit \
--with-file-aio \
--with-threads \
--with-http_v2_module \
--with-http_v3_module \
--with-http_ssl_module \
--with-http_realip_module \
--with-http_addition_module \
--with-http_sub_module \
--with-http_dav_module \
--with-http_flv_module \
--with-http_mp4_module \
--with-http_gunzip_module \
--with-http_gzip_static_module \
--with-http_random_index_module \
--with-http_secure_link_module \
--with-http_stub_status_module \
--with-http_auth_request_module \
--with-http_xslt_module \
--with-http_image_filter_module=dynamic \
--with-http_geoip_module \
--with-http_slice_module \
--with-stream \
--with-stream_ssl_module \
--with-stream_realip_module \
--with-stream_ssl_preread_module \
--with-mail \
--with-mail_ssl_module \
--with-compat \
--with-debug \
--with-cc-opt='-O2 -fstack-protector-strong -Wformat -Werror=format-security -Wp,-D_FORTIFY_SOURCE=2 -fPIC' \
--with-ld-opt='-Wl,-z,relro -Wl,-z,now -Wl,--as-needed -pie' \
--add-module=../mods/ngx_devel_kit \
--add-module=../mods/nginx-http-flv-module \
--add-module=../mods/headers-more-nginx-module

log_info "Compiling nginx (this may take several minutes)..."
make -j$(nproc)

log_info "Installing nginx..."
make install

log_info "Cleaning build artifacts..."
make clean

# Create symbolic link
log_info "Creating nginx_fos symbolic link..."
rm -f "${INSTALL_PREFIX}/sbin/nginx_fos"
ln -sf "${INSTALL_PREFIX}/sbin/nginx" "${INSTALL_PREFIX}/sbin/nginx_fos"

# Create necessary directories
log_info "Creating required directories..."
mkdir -p "${INSTALL_PREFIX}/client_body_temp"
mkdir -p "${INSTALL_PREFIX}/fastcgi_temp"
mkdir -p "${INSTALL_PREFIX}/proxy_temp"
mkdir -p "${INSTALL_PREFIX}/scgi_temp"
mkdir -p "${INSTALL_PREFIX}/uwsgi_temp"
mkdir -p "${INSTALL_PREFIX}/pid"
mkdir -p "${LOG_DIR}"

# Set proper permissions
log_info "Setting permissions..."
chown -R "${FOS_USER}:${FOS_GROUP}" "${INSTALL_PREFIX}"
chown -R "${FOS_USER}:${FOS_GROUP}" "${LOG_DIR}"

log_info "Nginx build completed successfully!"
log_info "Nginx binary: ${INSTALL_PREFIX}/sbin/nginx"
log_info "Nginx symlink: ${INSTALL_PREFIX}/sbin/nginx_fos"
log_info "Configuration: ${INSTALL_PREFIX}/conf/nginx.conf"
log_info "Log directory: ${LOG_DIR}"

# Display version
"${INSTALL_PREFIX}/sbin/nginx" -V

log_info "Build script finished"

# Print usage information
echo ""
echo "=============================================="
echo "  Build Complete!"
echo "=============================================="
echo ""
echo "To use custom paths, set environment variables before running:"
echo "  export FOS_NGINX_PREFIX=/custom/path/to/nginx"
echo "  export FOS_USER=myuser"
echo "  export FOS_GROUP=mygroup"
echo "  export FOS_LOG_DIR=/custom/path/to/logs"
echo ""
echo "Then run: sudo -E bash build-debian12.sh"
echo "=============================================="
