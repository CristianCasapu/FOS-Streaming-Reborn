# FOS-Streaming Nginx Setup Guide

This guide covers building and configuring nginx with RTMP/HLS streaming support for FOS-Streaming.

## Overview

FOS-Streaming uses a custom-built nginx with the following modules:
- **nginx-http-flv-module**: RTMP ingest + HTTP-FLV live streaming + HLS output
- **headers-more-nginx-module**: Custom header manipulation
- **ngx_devel_kit**: Development kit required by other modules

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        FOS-Streaming Nginx                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Port 8000 (Admin)         Port 8080 (Streaming)    Port 1935 (RTMP)│
│  ┌─────────────────┐      ┌─────────────────┐      ┌──────────────┐│
│  │ PHP-FPM Admin   │      │ PHP-FPM Stream  │      │ RTMP Server  ││
│  │ - Dashboard     │      │ - Auth only     │      │ - Ingest     ││
│  │ - API           │      │ - HLS delivery  │      │ - HLS output ││
│  │ - Subscriber    │      │ - HTTP-FLV      │      │ - DASH output││
│  └─────────────────┘      └─────────────────┘      └──────────────┘│
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Quick Start

### Option 1: Build Nginx (Recommended)

```bash
cd ~/FOS-Streaming/fospackv69/nginx-builder

# Build with automatic detection
sudo bash build-fos-nginx.sh

# Or specify options
sudo bash build-fos-nginx.sh \
    --fos-dir /home/fosadmin/FOS-Streaming \
    --user fosadmin
```

### Option 2: Use Pre-built Binary

If a pre-built binary exists at `fospackv69/fos/nginx/sbin/nginx`, you can skip building.

## Build Script Options

| Option | Description | Default |
|--------|-------------|---------|
| `--fos-dir DIR` | FOS-Streaming installation directory | Auto-detected |
| `--user USER` | User to run nginx as | Current user |
| `--clean` | Clean build artifacts before building | false |
| `--help` | Show help message | - |

## Configuration

### Configuration File Locations

```
~/FOS-Streaming/
├── fospackv69/fos/nginx/conf/
│   └── nginx.conf              # Main config (includes vhosts)
├── install/config/nginx/
│   ├── nginx.conf              # Main config template
│   ├── fos-admin.conf          # Admin vhost (port 8000)
│   ├── fos-streaming.conf      # Streaming vhost (port 8080)
│   └── fos-rtmp.conf           # RTMP config (port 1935)
└── install/config/php-fpm/
    ├── fos-admin.conf          # Admin PHP-FPM pool
    └── fos-streaming.conf      # Streaming PHP-FPM pool
```

### Installing Configurations

The installation script handles this automatically:

```bash
python3 install/03-setup-services.py
```

Or manually:

```bash
# Set variables
FOS_USER="fosadmin"
FOS_DIR="/home/fosadmin/FOS-Streaming"
PHP_VERSION="8.4"

# Copy and update nginx config
sed -e "s|__FOS_USER__|${FOS_USER}|g" \
    -e "s|__FOS_DIR__|${FOS_DIR}|g" \
    -e "s|__PHP_VERSION__|${PHP_VERSION}|g" \
    install/config/nginx/nginx.conf > fospackv69/fos/nginx/conf/nginx.conf

# Copy PHP-FPM pools
for pool in fos-admin.conf fos-streaming.conf; do
    sed -e "s|__FOS_USER__|${FOS_USER}|g" \
        -e "s|__FOS_DIR__|${FOS_DIR}|g" \
        -e "s|__PHP_VERSION__|${PHP_VERSION}|g" \
        install/config/php-fpm/${pool} | sudo tee /etc/php/${PHP_VERSION}/fpm/pool.d/${pool}
done

# Restart PHP-FPM
sudo systemctl restart php${PHP_VERSION}-fpm
```

## Ports Reference

| Port | Service | Description |
|------|---------|-------------|
| 8000 | Admin Web UI | Dashboard, API, Subscriber Portal |
| 8080 | Streaming | HLS/HTTP-FLV stream delivery |
| 1935 | RTMP | Stream ingestion |

## Testing

### Test Configuration Syntax

```bash
~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx -t
```

### Start Nginx Manually (for testing)

```bash
~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx
```

### Start Nginx via Systemd

```bash
sudo systemctl start fos-nginx
sudo systemctl status fos-nginx
```

### Test Endpoints

```bash
# Admin health check
curl http://localhost:8000/health

# Streaming health check
curl http://localhost:8080/health

# RTMP statistics (JSON)
curl http://localhost:8080/stat
```

## Stream Testing

### Push Test Stream

```bash
# Push test pattern to RTMP
ffmpeg -re -f lavfi -i testsrc=size=1280x720:rate=30 \
    -f lavfi -i sine=frequency=1000:sample_rate=48000 \
    -c:v libx264 -preset veryfast -b:v 2500k \
    -c:a aac -b:a 128k \
    -f flv rtmp://localhost/live/test
```

### Watch Stream

```bash
# HLS URL
http://localhost:8080/hls/test/index.m3u8

# HTTP-FLV URL
http://localhost:8080/live?app=live&stream=test
```

## Troubleshooting

### Nginx Won't Start

1. **Check configuration syntax:**
   ```bash
   ~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx -t
   ```

2. **Check error log:**
   ```bash
   tail -f ~/FOS-Streaming/logs/nginx-error.log
   ```

3. **Check if port is in use:**
   ```bash
   sudo ss -tlnp | grep -E '8000|8080|1935'
   ```

### PHP-FPM Socket Not Found

1. **Verify PHP-FPM is running:**
   ```bash
   sudo systemctl status php8.4-fpm
   ```

2. **Check socket exists:**
   ```bash
   ls -la /run/php/php8.4-fpm-*.sock
   ```

3. **Restart PHP-FPM:**
   ```bash
   sudo systemctl restart php8.4-fpm
   ```

### HLS Files Not Generated

1. **Check RTMP is receiving stream:**
   ```bash
   curl http://localhost:8080/stat
   ```

2. **Check HLS directory exists and is writable:**
   ```bash
   ls -la ~/FOS-Streaming/hl/
   ```

3. **Check RTMP error log:**
   ```bash
   tail -f ~/FOS-Streaming/logs/nginx-error.log | grep rtmp
   ```

### Permission Denied Errors

```bash
# Fix ownership
sudo chown -R $(whoami):$(whoami) ~/FOS-Streaming

# Fix directory permissions
find ~/FOS-Streaming -type d -exec chmod 755 {} \;

# Fix file permissions
find ~/FOS-Streaming -type f -exec chmod 644 {} \;

# Make nginx executable
chmod 755 ~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx
```

## Systemd Service

The installation creates a systemd service at `/etc/systemd/system/fos-nginx.service`:

```ini
[Unit]
Description=FOS-Streaming Nginx
After=network.target

[Service]
Type=forking
User=fosadmin
Group=fosadmin
PIDFile=/home/fosadmin/FOS-Streaming/fospackv69/fos/nginx/logs/nginx.pid
ExecStart=/home/fosadmin/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx
ExecReload=/bin/kill -s HUP $MAINPID
ExecStop=/bin/kill -s QUIT $MAINPID
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

### Service Management

```bash
# Start nginx
sudo systemctl start fos-nginx

# Stop nginx
sudo systemctl stop fos-nginx

# Restart nginx
sudo systemctl restart fos-nginx

# Reload configuration (no downtime)
sudo systemctl reload fos-nginx

# Enable on boot
sudo systemctl enable fos-nginx

# Check status
sudo systemctl status fos-nginx
```

## Advanced Configuration

### Enable HTTPS

1. Obtain SSL certificate (Let's Encrypt recommended):
   ```bash
   sudo certbot certonly --standalone -d your-domain.com
   ```

2. Uncomment SSL sections in `fos-admin.conf`:
   ```nginx
   listen 443 ssl http2;
   ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
   ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
   ```

### Enable Stream Authentication

1. Uncomment callback URLs in `fos-rtmp.conf`:
   ```nginx
   on_publish http://127.0.0.1:8000/api/rtmp/on-publish;
   on_play http://127.0.0.1:8000/api/rtmp/on-play;
   ```

2. Implement the authentication endpoints in your application.

### Adaptive Bitrate Streaming

Uncomment transcoding in `fos-rtmp.conf`:

```nginx
exec_push ffmpeg -i rtmp://localhost/live/$name
    -c:v libx264 -preset veryfast -b:v 3000k -f flv rtmp://localhost/hls/$name_high
    -c:v libx264 -preset veryfast -b:v 1500k -f flv rtmp://localhost/hls/$name_mid
    -c:v libx264 -preset veryfast -b:v 500k -f flv rtmp://localhost/hls/$name_low;
```

## File Locations Summary

| File | Location |
|------|----------|
| Nginx binary | `~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx` |
| Main config | `~/FOS-Streaming/fospackv69/fos/nginx/conf/nginx.conf` |
| Vhost configs | `~/FOS-Streaming/install/config/nginx/` |
| Error log | `~/FOS-Streaming/logs/nginx-error.log` |
| Access log | `~/FOS-Streaming/logs/nginx-access.log` |
| HLS output | `~/FOS-Streaming/hl/` |
| DASH output | `~/FOS-Streaming/fospackv69/fos/streams/dash/` |
| PID file | `~/FOS-Streaming/fospackv69/fos/nginx/logs/nginx.pid` |

---

**Last Updated**: 2025-12-11
