#!/bin/bash

# ============================================================================
# Nginx with HTTP/3 (QUIC) Build Script
# FOS-Streaming v70
#
# This script builds nginx with HTTP/3 support using BoringSSL
# ============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
NGINX_VERSION="1.25.3"
NGINX_QUIC_COMMIT="stable-quic"
BUILD_DIR="/tmp/nginx-http3-build"
INSTALL_PREFIX="/usr/local/nginx-http3"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root"
    exit 1
fi

echo "=========================================="
echo "Nginx HTTP/3 (QUIC) Build Script"
echo "=========================================="
echo

# Step 1: Install dependencies
log_info "Installing build dependencies..."
apt-get update
apt-get install -y \
    build-essential \
    cmake \
    git \
    golang \
    libpcre3 \
    libpcre3-dev \
    zlib1g \
    zlib1g-dev \
    libssl-dev \
    libgd-dev \
    libxml2 \
    libxml2-dev \
    libxslt1-dev \
    libgeoip-dev \
    libgoogle-perftools-dev \
    libperl-dev \
    libunwind-dev \
    mercurial \
    ninja-build \
    patch

# Step 2: Create build directory
log_info "Creating build directory..."
rm -rf $BUILD_DIR
mkdir -p $BUILD_DIR
cd $BUILD_DIR

# Step 3: Clone and build BoringSSL
log_info "Cloning and building BoringSSL..."
git clone https://github.com/google/boringssl.git
cd boringssl
mkdir build
cd build
cmake -GNinja ..
ninja
cd $BUILD_DIR

# Step 4: Clone nginx with QUIC patches
log_info "Cloning nginx with QUIC support..."
hg clone -b quic https://hg.nginx.org/nginx-quic nginx-quic
cd nginx-quic

# Alternative: Use nginx-quic from GitHub
# git clone --branch stable-quic https://github.com/nginx/nginx-quic.git
# cd nginx-quic

# Step 5: Download additional modules
log_info "Downloading additional nginx modules..."
cd $BUILD_DIR

# Headers More module
git clone https://github.com/openresty/headers-more-nginx-module.git

# Brotli compression
git clone --recurse-submodules -j8 https://github.com/google/ngx_brotli.git

# Cache Purge module
git clone https://github.com/FRiCKLE/ngx_cache_purge.git

# HTTP FLV module (for streaming)
git clone https://github.com/winshining/nginx-http-flv-module.git

# Step 6: Configure nginx
log_info "Configuring nginx with HTTP/3..."
cd $BUILD_DIR/nginx-quic

./auto/configure \
    --prefix=$INSTALL_PREFIX \
    --sbin-path=$INSTALL_PREFIX/sbin/nginx \
    --conf-path=$INSTALL_PREFIX/conf/nginx.conf \
    --error-log-path=/var/log/nginx/error.log \
    --http-log-path=/var/log/nginx/access.log \
    --pid-path=/var/run/nginx.pid \
    --lock-path=/var/run/nginx.lock \
    --http-client-body-temp-path=/var/cache/nginx/client_temp \
    --http-proxy-temp-path=/var/cache/nginx/proxy_temp \
    --http-fastcgi-temp-path=/var/cache/nginx/fastcgi_temp \
    --http-uwsgi-temp-path=/var/cache/nginx/uwsgi_temp \
    --http-scgi-temp-path=/var/cache/nginx/scgi_temp \
    --user=nginx \
    --group=nginx \
    --with-compat \
    --with-file-aio \
    --with-threads \
    --with-http_addition_module \
    --with-http_auth_request_module \
    --with-http_dav_module \
    --with-http_flv_module \
    --with-http_gunzip_module \
    --with-http_gzip_static_module \
    --with-http_mp4_module \
    --with-http_random_index_module \
    --with-http_realip_module \
    --with-http_secure_link_module \
    --with-http_slice_module \
    --with-http_ssl_module \
    --with-http_stub_status_module \
    --with-http_sub_module \
    --with-http_v2_module \
    --with-http_v3_module \
    --with-mail \
    --with-mail_ssl_module \
    --with-stream \
    --with-stream_realip_module \
    --with-stream_ssl_module \
    --with-stream_ssl_preread_module \
    --with-stream_quic_module \
    --with-cc-opt="-I$BUILD_DIR/boringssl/include" \
    --with-ld-opt="-L$BUILD_DIR/boringssl/build/ssl -L$BUILD_DIR/boringssl/build/crypto" \
    --add-module=$BUILD_DIR/headers-more-nginx-module \
    --add-module=$BUILD_DIR/ngx_brotli \
    --add-module=$BUILD_DIR/ngx_cache_purge \
    --add-module=$BUILD_DIR/nginx-http-flv-module

# Step 7: Build nginx
log_info "Building nginx (this may take a while)..."
make -j$(nproc)

# Step 8: Install nginx
log_info "Installing nginx..."
make install

# Step 9: Create necessary directories
log_info "Creating directories..."
mkdir -p /var/cache/nginx/{client_temp,proxy_temp,fastcgi_temp,uwsgi_temp,scgi_temp}
mkdir -p /var/log/nginx
mkdir -p $INSTALL_PREFIX/conf/sites-available
mkdir -p $INSTALL_PREFIX/conf/sites-enabled
mkdir -p $INSTALL_PREFIX/conf/ssl

# Step 10: Create nginx user if not exists
if ! id -u nginx > /dev/null 2>&1; then
    log_info "Creating nginx user..."
    useradd -r -s /bin/false nginx
fi

# Step 11: Set permissions
chown -R nginx:nginx /var/cache/nginx
chown -R nginx:nginx /var/log/nginx

# Step 12: Create systemd service
log_info "Creating systemd service..."
cat > /etc/systemd/system/nginx-http3.service <<EOF
[Unit]
Description=Nginx HTTP/3 (QUIC) Server
Documentation=https://nginx.org/en/docs/
After=network.target network-online.target syslog.target
Wants=network-online.target

[Service]
Type=forking
PIDFile=/var/run/nginx.pid
ExecStartPre=$INSTALL_PREFIX/sbin/nginx -t -c $INSTALL_PREFIX/conf/nginx.conf
ExecStart=$INSTALL_PREFIX/sbin/nginx -c $INSTALL_PREFIX/conf/nginx.conf
ExecReload=/bin/kill -s HUP \$MAINPID
ExecStop=/bin/kill -s QUIT \$MAINPID
PrivateTmp=true
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
EOF

# Step 13: Generate ECH keys
log_info "Generating ECH keys..."
mkdir -p $INSTALL_PREFIX/conf/ech
openssl rand -out $INSTALL_PREFIX/conf/ech/ech.key 32

# Step 14: Generate self-signed certificate for testing
log_info "Generating self-signed certificate..."
openssl req -x509 -nodes -days 365 -newkey rsa:4096 \
    -keyout $INSTALL_PREFIX/conf/ssl/server.key \
    -out $INSTALL_PREFIX/conf/ssl/server.crt \
    -subj "/C=US/ST=State/L=City/O=FOS-Streaming/CN=localhost"

# Step 15: Create basic HTTP/3 configuration
log_info "Creating basic HTTP/3 configuration..."
cat > $INSTALL_PREFIX/conf/nginx.conf <<'EOF'
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 10240;
    use epoll;
    multi_accept on;
}

http {
    include       mime.types;
    default_type  application/octet-stream;

    # Logging
    log_format quic '$remote_addr - $remote_user [$time_local] '
                    '"$request" $status $body_bytes_sent '
                    '"$http_referer" "$http_user_agent" '
                    'h3=$http3 quic=$quic';

    access_log /var/log/nginx/access.log quic;

    # Basic settings
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 4096;

    # HTTP/3 settings
    http3 on;
    http3_stream_buffer_size 128K;
    http3_max_concurrent_streams 128;

    # SSL settings
    ssl_protocols TLSv1.3;
    ssl_early_data on;
    ssl_ciphers TLS_AES_128_GCM_SHA256:TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256;
    ssl_prefer_server_ciphers off;

    # Default server
    server {
        listen 443 quic reuseport;
        listen 443 ssl http2;

        server_name localhost;

        ssl_certificate /usr/local/nginx-http3/conf/ssl/server.crt;
        ssl_certificate_key /usr/local/nginx-http3/conf/ssl/server.key;

        # Alt-Svc header to advertise HTTP/3
        add_header Alt-Svc 'h3=":443"; ma=86400';

        # Test endpoint
        location / {
            add_header Content-Type text/plain;
            return 200 "HTTP/3 is working!\n\nProtocol: $server_protocol\nHTTP/3: $http3\n";
        }

        # QUIC stats
        location /quic-stats {
            add_header Content-Type text/plain;
            return 200 "QUIC Stats\n==========\nHTTP/3: $http3\nQUIC: $quic\nConnection: $quic_connection\n";
        }
    }

    # Include additional configurations
    include sites-enabled/*.conf;
}
EOF

# Step 16: Test configuration
log_info "Testing nginx configuration..."
$INSTALL_PREFIX/sbin/nginx -t

if [ $? -eq 0 ]; then
    log_success "Nginx configuration test passed"
else
    log_error "Nginx configuration test failed"
    exit 1
fi

# Step 17: Enable and start service
log_info "Enabling and starting nginx-http3 service..."
systemctl daemon-reload
systemctl enable nginx-http3
systemctl start nginx-http3

# Step 18: Check status
if systemctl is-active --quiet nginx-http3; then
    log_success "Nginx HTTP/3 is running"
else
    log_error "Failed to start nginx-http3"
    exit 1
fi

# Step 19: Open firewall ports
log_info "Opening firewall ports..."
if command -v ufw > /dev/null; then
    ufw allow 443/tcp
    ufw allow 443/udp
    log_success "Firewall rules added"
fi

# Summary
echo
echo "=========================================="
echo -e "${GREEN}Nginx HTTP/3 Build Complete!${NC}"
echo "=========================================="
echo
echo "Installation directory: $INSTALL_PREFIX"
echo "Configuration: $INSTALL_PREFIX/conf/nginx.conf"
echo "Service: nginx-http3"
echo
echo "Test HTTP/3 with:"
echo "  curl --http3 https://localhost/"
echo "  curl --http3 https://localhost/quic-stats"
echo
echo "Chrome flags: chrome://flags/#enable-quic"
echo "Firefox: about:config → network.http.http3.enabled"
echo
log_success "Build completed successfully!"