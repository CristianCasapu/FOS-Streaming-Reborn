# Streaming Service Architecture Refactoring Plan

## Overview

This plan outlines the implementation of dedicated **Nginx Streaming Service** and **PHP-FPM Streaming Service** using the bundled binaries from `fospackv69/fos/`. These services will be separate from the main web server and optimized for high-concurrency streaming operations.

## Current State Analysis

### Existing Architecture
- **Main Web Panel**: Uses system PHP 8.4-FPM (from Sury repo) + System Nginx (port 7777)
- **Streaming**: Currently shares the same nginx/php-fpm configuration
- **PM2 Workers**: 8 Node.js workers for background tasks
- **System Services**: Managed via systemd (nginx, mariadb, php8.4-fpm)

### Bundled Binaries Status
- **Nginx**: `fospackv69/fos/nginx/sbin/nginx_fos` (requires libpcre.so.3)
- **PHP-FPM**: `fospackv69/fos/php/sbin/php-fpm` (requires libpq.so.5 and other libs)
- **PHP CLI**: `fospackv69/fos/php/bin/php`

### Issues Identified
1. Bundled binaries require shared library dependencies
2. PHP version in fospackv69 appears to be older than 8.4
3. Current nginx.conf uses outdated socket path: `unix:/var/run/php__5-fpm.sock`
4. Pool configuration needs optimization for high concurrency

---

## Proposed Architecture

### Service Separation
```
┌─────────────────────────────────────────────────────────────────────────┐
│                         FOS-Streaming Platform                          │
├────────────────────────────────┬────────────────────────────────────────┤
│       Admin Web Panel          │         Streaming Services              │
│  (Port 7777 - Main Interface)  │  (Port 8000 - Content Delivery)        │
├────────────────────────────────┼────────────────────────────────────────┤
│ • System Nginx                 │ • Nginx Streaming Service (nginx_fos)  │
│ • System PHP 8.4-FPM           │ • PHP-FPM Streaming Service            │
│ • Vue.js SPA Admin Panel       │ • HLS/HTTP-FLV Streaming               │
│ • API Endpoints                │ • Stream Authentication                │
│ • Settings & Management        │ • High-Concurrency Optimized           │
└────────────────────────────────┴────────────────────────────────────────┘
```

### Service Dependencies
```
                    ┌─────────────────────────────┐
                    │  fos-php-fpm-streaming      │
                    │  (PHP-FPM Streaming Pool)   │
                    └─────────────┬───────────────┘
                                  │ Requires
                    ┌─────────────▼───────────────┐
                    │  fos-nginx-streaming        │
                    │  (Nginx Streaming Server)   │
                    │  Waits for PHP-FPM socket   │
                    └─────────────────────────────┘
```

---

## Implementation Tasks

### Phase 1: Prepare PHP-FPM Streaming Service

#### 1.1 Create PHP-FPM Streaming Pool Configuration
**File**: `fospackv69/fos/php/etc/php-fpm-streaming.conf`

```ini
[global]
pid = /tmp/fos-streaming-php-fpm.pid
error_log = /home/fos-streaming/fos/logs/php-fpm-streaming.log
log_level = warning
emergency_restart_threshold = 10
emergency_restart_interval = 1m
process_control_timeout = 10s
process.max = 5000
rlimit_files = 65535
events.mechanism = epoll
daemonize = yes

[streaming]
user = fosstreaming
group = fosstreaming
listen = /tmp/fos-streaming-php-fpm.sock
listen.owner = fosstreaming
listen.group = fosstreaming
listen.mode = 0660
listen.backlog = 65535

; Process Manager - Optimized for streaming
pm = ondemand
pm.max_children = 500
pm.start_servers = 20
pm.min_spare_servers = 10
pm.max_spare_servers = 50
pm.process_idle_timeout = 10s
pm.max_requests = 10000

; Resource Limits
rlimit_files = 65535
rlimit_core = unlimited

; Performance
request_terminate_timeout = 300
request_slowlog_timeout = 0

; Security
security.limit_extensions = .php
chdir = /
clear_env = no

; PHP Settings for Streaming
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 1M
php_admin_value[post_max_size] = 1M
php_admin_value[output_buffering] = Off
php_admin_flag[display_errors] = Off
php_admin_flag[log_errors] = On
php_admin_value[error_log] = /home/fos-streaming/fos/logs/php-streaming-error.log
```

#### 1.2 Decision: Use System PHP 8.4 vs Bundled PHP

**Recommendation**: Use **System PHP 8.4** for the streaming service.

**Reasons**:
1. The bundled PHP-FPM requires shared libraries that may not be present
2. System PHP 8.4 is maintained and receives security updates
3. Easier deployment and maintenance
4. Same version ensures compatibility across services

**If bundled PHP is required**, the installer must add:
```bash
sudo apt-get install -y libpq5 libpcre3 libxml2 libzip4 libonig5 libargon2-1
```

---

### Phase 2: Configure Nginx Streaming Service

#### 2.1 Create Optimized Nginx Streaming Configuration
**File**: `fospackv69/fos/nginx/conf/nginx-streaming.conf`

```nginx
# Nginx Streaming Service Configuration
# Optimized for high-concurrency HLS/HTTP-FLV streaming

user fosstreaming fosstreaming;
worker_processes auto;
worker_cpu_affinity auto;
worker_rlimit_nofile 1000000;
error_log /home/fos-streaming/fos/logs/nginx-streaming-error.log warn;
pid /tmp/fos-streaming-nginx.pid;

events {
    worker_connections 100000;
    use epoll;
    multi_accept on;
    accept_mutex off;
}

http {
    include mime.types;
    default_type application/octet-stream;

    # Logging - Minimal for performance
    access_log /home/fos-streaming/fos/logs/nginx-streaming-access.log combined buffer=256k flush=5m;

    # Performance Optimizations
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    keepalive_requests 10000;
    reset_timedout_connection on;
    client_body_timeout 60;
    send_timeout 60;

    # Buffers for streaming
    client_body_buffer_size 128k;
    client_max_body_size 10m;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 8k;
    output_buffers 1 512k;
    postpone_output 1460;

    # Gzip disabled for streams
    gzip off;

    # Connection limits
    limit_conn_zone $binary_remote_addr zone=stream_conn:50m;
    limit_req_zone $binary_remote_addr zone=stream_req:50m rate=100r/s;

    # Upstream PHP-FPM
    upstream php_fpm_streaming {
        server unix:/tmp/fos-streaming-php-fpm.sock;
        keepalive 128;
    }

    # Streaming Server
    server {
        listen 8000 reuseport;
        listen [::]:8000 reuseport;

        server_name _;
        server_tokens off;

        root /home/fos-streaming/fos/www;
        index index.php;

        # Connection limits
        limit_conn stream_conn 50;
        limit_req zone=stream_req burst=200 nodelay;

        # Stream URL rewrite
        rewrite ^/live/(.*)/(.*)/(.*)$ /stream.php?username=$1&password=$2&stream=$3 break;

        # HLS Streaming
        location /hl/ {
            alias /home/fos-streaming/fos/www/hl/;

            types {
                application/vnd.apple.mpegurl m3u8;
                video/mp2t ts;
            }

            add_header Cache-Control "no-cache, no-store, must-revalidate";
            add_header Pragma "no-cache";
            add_header Expires "0";
            add_header Access-Control-Allow-Origin *;

            # Performance
            aio on;
            directio 512;
            output_buffers 1 1m;
        }

        # PHP Processing
        location ~ \.php$ {
            try_files $uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass php_fpm_streaming;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;

            # FastCGI Optimizations
            fastcgi_keep_conn on;
            fastcgi_buffers 256 16k;
            fastcgi_buffer_size 128k;
            fastcgi_busy_buffers_size 256k;
            fastcgi_temp_file_write_size 256k;
            fastcgi_connect_timeout 30;
            fastcgi_send_timeout 300;
            fastcgi_read_timeout 300;
        }

        # Health check endpoint
        location /health {
            access_log off;
            return 200 'OK';
            add_header Content-Type text/plain;
        }

        # Error pages
        error_page 500 502 503 504 /50x.html;
        location = /50x.html {
            root html;
        }
    }
}
```

---

### Phase 3: Create Systemd Service Units

#### 3.1 PHP-FPM Streaming Service
**File**: `/etc/systemd/system/fos-php-fpm-streaming.service`

```ini
[Unit]
Description=FOS Streaming PHP-FPM Service
Documentation=https://github.com/CristianCasapu/FOS-Streaming-Reborn
After=network.target
Before=fos-nginx-streaming.service

[Service]
Type=notify
User=root
Group=root
PIDFile=/tmp/fos-streaming-php-fpm.pid
ExecStartPre=/usr/sbin/php-fpm8.4 -t -y /home/fos-streaming/fos/php/etc/php-fpm-streaming.conf
ExecStart=/usr/sbin/php-fpm8.4 -y /home/fos-streaming/fos/php/etc/php-fpm-streaming.conf --nodaemonize
ExecReload=/bin/kill -USR2 $MAINPID
ExecStop=/bin/kill -QUIT $MAINPID
TimeoutStopSec=10
KillMode=mixed
KillSignal=SIGQUIT
PrivateTmp=false
RuntimeDirectory=fos-streaming-php
RuntimeDirectoryMode=0755

# Resource Limits
LimitNOFILE=1000000
LimitNPROC=65535
LimitCORE=infinity

# Restart Policy
Restart=always
RestartSec=5s

[Install]
WantedBy=multi-user.target
```

#### 3.2 Nginx Streaming Service
**File**: `/etc/systemd/system/fos-nginx-streaming.service`

```ini
[Unit]
Description=FOS Nginx Streaming Service
Documentation=https://github.com/CristianCasapu/FOS-Streaming-Reborn
After=network.target fos-php-fpm-streaming.service
Requires=fos-php-fpm-streaming.service
BindsTo=fos-php-fpm-streaming.service

[Service]
Type=forking
User=fosstreaming
Group=fosstreaming
PIDFile=/tmp/fos-streaming-nginx.pid
ExecStartPre=/home/fos-streaming/fos/nginx/sbin/nginx_fos -t -c /home/fos-streaming/fos/nginx/conf/nginx-streaming.conf
ExecStart=/home/fos-streaming/fos/nginx/sbin/nginx_fos -c /home/fos-streaming/fos/nginx/conf/nginx-streaming.conf
ExecReload=/bin/kill -HUP $MAINPID
ExecStop=/bin/kill -QUIT $MAINPID
TimeoutStopSec=10

# Resource Limits
LimitNOFILE=1000000
LimitNPROC=65535
LimitCORE=infinity

# Restart Policy
Restart=always
RestartSec=5s

# Wait for PHP-FPM socket
ExecStartPre=/bin/bash -c 'for i in {1..30}; do [ -S /tmp/fos-streaming-php-fpm.sock ] && exit 0; sleep 1; done; exit 1'

[Install]
WantedBy=multi-user.target
```

---

### Phase 4: Update debian12-installer

#### 4.1 Add Streaming Services Setup Section

```bash
# ============================================================================
# STEP XX: Configure Streaming Services
# ============================================================================
log_step "Step XX: Configuring Dedicated Streaming Services"

# Create streaming PHP-FPM configuration
log_info "Creating PHP-FPM streaming pool configuration..."
sudo tee /home/fos-streaming/fos/php/etc/php-fpm-streaming.conf > /dev/null <<'STREAMING_FPM_EOF'
# ... (configuration from Phase 1)
STREAMING_FPM_EOF

# Create streaming Nginx configuration
log_info "Creating Nginx streaming configuration..."
sudo tee /home/fos-streaming/fos/nginx/conf/nginx-streaming.conf > /dev/null <<'STREAMING_NGINX_EOF'
# ... (configuration from Phase 2)
STREAMING_NGINX_EOF

# Install systemd service units
log_info "Installing streaming service units..."
sudo tee /etc/systemd/system/fos-php-fpm-streaming.service > /dev/null <<'STREAMING_PHP_SERVICE_EOF'
# ... (service unit from Phase 3.1)
STREAMING_PHP_SERVICE_EOF

sudo tee /etc/systemd/system/fos-nginx-streaming.service > /dev/null <<'STREAMING_NGINX_SERVICE_EOF'
# ... (service unit from Phase 3.2)
STREAMING_NGINX_SERVICE_EOF

# Set permissions
sudo chown -R fosstreaming:fosstreaming /home/fos-streaming/fos/nginx
sudo chown -R fosstreaming:fosstreaming /home/fos-streaming/fos/php/etc
sudo chmod 644 /home/fos-streaming/fos/nginx/conf/nginx-streaming.conf
sudo chmod 644 /home/fos-streaming/fos/php/etc/php-fpm-streaming.conf

# Enable and start services
sudo systemctl daemon-reload
sudo systemctl enable fos-php-fpm-streaming
sudo systemctl enable fos-nginx-streaming
sudo systemctl start fos-php-fpm-streaming
sleep 2
sudo systemctl start fos-nginx-streaming
```

---

### Phase 5: Update Admin UI

#### 5.1 Update PM2 API to Include Streaming Services
**File**: `public/admin/api/pm2.php`

Add streaming services to system services array:

```php
$services = [
    [
        'name' => 'nginx',
        'display_name' => 'Nginx Web Server',
        'type' => 'system',
        'status' => getServiceStatus('nginx'),
        'description' => 'Admin panel web server'
    ],
    [
        'name' => 'fos-nginx-streaming',
        'display_name' => 'Nginx Streaming Service',
        'type' => 'system',
        'status' => getServiceStatus('fos-nginx-streaming'),
        'description' => 'High-performance HLS/HTTP-FLV streaming server'
    ],
    [
        'name' => 'fos-php-fpm-streaming',
        'display_name' => 'PHP-FPM Streaming Service',
        'type' => 'system',
        'status' => getServiceStatus('fos-php-fpm-streaming'),
        'description' => 'PHP FastCGI for stream authentication'
    ],
    [
        'name' => 'mariadb',
        'display_name' => 'MariaDB Database',
        'type' => 'system',
        'status' => getServiceStatus('mariadb'),
        'description' => 'Database server'
    ],
    [
        'name' => 'php-fpm',
        'display_name' => 'PHP-FPM Admin',
        'type' => 'system',
        'status' => getServiceStatus('php8.4-fpm'),
        'description' => 'PHP FastCGI for admin panel'
    ]
];
```

---

### Phase 6: Shared Library Dependencies (If Using Bundled Binaries)

#### 6.1 Update Installer to Install Required Libraries

```bash
# Install shared libraries for bundled binaries
log_info "Installing shared libraries for streaming services..."
sudo apt-get install -y \
    libpcre3 \
    libpq5 \
    libxml2 \
    libzip4 \
    libonig5 \
    libargon2-1 \
    libsodium23 \
    libgd3 \
    libcurl4 \
    libssl3
```

---

## File Changes Summary

### New Files to Create
1. `fospackv69/fos/php/etc/php-fpm-streaming.conf` - PHP-FPM streaming pool config
2. `fospackv69/fos/nginx/conf/nginx-streaming.conf` - Nginx streaming config
3. `/etc/systemd/system/fos-php-fpm-streaming.service` - PHP-FPM systemd unit (created by installer)
4. `/etc/systemd/system/fos-nginx-streaming.service` - Nginx systemd unit (created by installer)

### Files to Modify
1. `install/debian12-installer` - Add streaming services setup
2. `public/admin/api/pm2.php` - Add streaming services to admin UI
3. `app/Services/WebsiteHealthMonitorService.php` - Add streaming service monitoring
4. `docs/PM2_MANAGEMENT_UI.md` - Update documentation

---

## Performance Tuning Notes

### System Kernel Parameters
Add to `/etc/sysctl.conf`:

```ini
# Network Performance
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 65535
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.ip_local_port_range = 1024 65535
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 15
net.core.rmem_max = 16777216
net.core.wmem_max = 16777216
net.ipv4.tcp_rmem = 4096 87380 16777216
net.ipv4.tcp_wmem = 4096 65536 16777216

# File Descriptors
fs.file-max = 2000000
fs.nr_open = 2000000
```

### User Limits
Add to `/etc/security/limits.conf`:

```ini
fosstreaming soft nofile 1000000
fosstreaming hard nofile 1000000
fosstreaming soft nproc 65535
fosstreaming hard nproc 65535
nginx soft nofile 1000000
nginx hard nofile 1000000
```

---

## Testing Checklist

- [ ] PHP-FPM streaming service starts successfully
- [ ] Nginx streaming service starts after PHP-FPM is ready
- [ ] Nginx fails gracefully if PHP-FPM is not running
- [ ] Socket file `/tmp/fos-streaming-php-fpm.sock` is created with correct permissions
- [ ] HLS streaming works on port 8000
- [ ] Stream authentication via PHP works
- [ ] Services restart automatically after crashes
- [ ] Admin UI shows correct status for both services
- [ ] High concurrency test passes (10,000+ connections)

---

## Rollback Plan

If issues arise, revert to using the main nginx/php-fpm services:

1. Stop streaming services: `systemctl stop fos-nginx-streaming fos-php-fpm-streaming`
2. Disable streaming services: `systemctl disable fos-nginx-streaming fos-php-fpm-streaming`
3. Update main nginx.conf to handle streaming on port 8000
4. Restart main services: `systemctl restart fos-nginx php8.4-fpm`

---

**Plan Version**: 1.0
**Created**: 2025-11-25
**Author**: Claude Code
**Status**: Ready for Review
