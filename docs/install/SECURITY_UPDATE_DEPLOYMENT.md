# FOS-Streaming v70 Security Update Deployment Guide

**Version**: 70.0.0 (Security Fortress)
**Date**: 2025-11-24
**Purpose**: Deploy device locking and SRT proxy streaming features

---

## Prerequisites

- Debian 12 (Bookworm) or Ubuntu 22.04 LTS
- MariaDB 11.4+ or MySQL 8.0+
- PHP 8.4+
- Node.js 20.19.0+ LTS
- Nginx 1.26+
- Redis Server
- 4GB RAM minimum (8GB recommended)
- 20GB disk space

---

## Quick Deployment (New Installation)

### 1. Run Automated Installer

```bash
# Clone repository
git clone https://github.com/CristianCasapu/FOS-Streaming-Reborn.git
cd FOS-Streaming-Reborn

# Checkout develop branch
git checkout develop

# Make installer executable
chmod +x install/debian12-installer

# Run installer (includes all security packages)
sudo ./install/debian12-installer
```

The installer now includes:
- SRT libraries (libsrt-openssl-dev, srt-tools)
- QUIC/HTTP3 libraries
- Redis for session management
- Fail2ban for security
- UFW firewall

### 2. Database Setup

After installation, the database will be automatically configured with:

```bash
# Apply security migration
mysql -u fos -p fos_streaming < database/migrations/2025-11-24_full_security_update.sql

# Seed initial data
mysql -u fos -p fos_streaming < database/seeds/security_update_seeder.sql
```

---

## Upgrade Existing Installation

### 1. Backup Current System

```bash
# Backup database
mysqldump -u fos -p fos_streaming > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf fos_backup_$(date +%Y%m%d).tar.gz /home/fos-streaming/fos/
```

### 2. Install New Dependencies

```bash
# Install SRT packages
sudo apt-get update
sudo apt-get install -y \
    libsrt-openssl-dev \
    srt-tools \
    pkg-config \
    redis-server

# Install QUIC libraries (optional for future)
sudo apt-get install -y \
    libngtcp2-dev \
    libnghttp3-dev

# Start Redis
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 3. Update Code

```bash
cd /home/fos-streaming/fos
git pull origin develop

# Install Node.js dependencies
npm install

# Build frontend
npm run build

# Update composer packages
composer install --no-dev
```

### 4. Apply Database Migration

```bash
# Run migration
mysql -u fos -p fos_streaming < database/migrations/2025-11-24_full_security_update.sql

# Seed new data
mysql -u fos -p fos_streaming < database/seeds/security_update_seeder.sql
```

### 5. Deploy Workers

```bash
# Copy new SRT proxy worker
cp workers/srt-proxy-worker.js /home/fos-streaming/fos/workers/

# Regenerate PM2 ecosystem
php artisan pm2:generate

# Restart PM2
pm2 delete all
pm2 start ecosystem.config.js
pm2 save
pm2 startup
```

---

## Configuration

### 1. Environment Variables (.env)

Add these new variables:

```env
# Redis Configuration
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0

# SRT Configuration
SRT_PROXY_PORT=9000
SRT_ENCRYPTION_KEY=your-secure-key-here

# Device Locking
DEVICE_LOCK_ENABLED=true
DEVICE_MAX_PER_SUBSCRIPTION=3
DEVICE_SESSION_TIMEOUT=30
```

### 2. SRT Proxy Configuration

The SRT proxy worker listens on ports starting from 9000:
- Stream 1: Port 9001
- Stream 2: Port 9002
- Stream N: Port 9000 + N

### 3. Nginx Configuration

Add SRT proxy upstream (if using Nginx for load balancing):

```nginx
upstream srt_proxy {
    server 127.0.0.1:9001;
    server 127.0.0.1:9002;
    # Add more as needed
}
```

---

## Testing

### 1. Test Device Locking

```bash
php test_device_locking.php
```

Expected output:
```
✓ Database tables
✓ Models loaded
✓ Fingerprint created
✓ Binding created
✓ Session created
✓ Violation logged
Results: 6 passed, 1 failed
```

### 2. Test SRT Configuration

```bash
php test_srt_stream.php
```

Expected output:
```
✓ Test stream configured successfully!
Proxy will listen on port: 9001
```

### 3. Verify Workers

```bash
pm2 status
```

Should show:
```
┌─────┬──────────────────────┬─────────┬─────────┬───────┬──────────┐
│ id  │ name                 │ mode    │ status  │ cpu   │ memory   │
├─────┼──────────────────────┼─────────┼─────────┼───────┼──────────┤
│ 0   │ srt-proxy-worker     │ fork    │ online  │ 0%    │ 85.2mb   │
│ 1   │ stream-import-worker │ fork    │ online  │ 0%    │ 65.4mb   │
│ ... │ ...                  │ ...     │ ...     │ ...   │ ...      │
└─────┴──────────────────────┴─────────┴─────────┴───────┴──────────┘
```

---

## Security Features

### Device Locking System

1. **Multi-Factor Identification**:
   - Canvas fingerprinting
   - WebGL fingerprinting
   - Audio fingerprinting
   - Hardware ID tracking
   - IP range validation
   - Geolocation verification

2. **Automatic Protection**:
   - Blocks after 3 violations in 24 hours
   - Detects device evasion (80% similarity threshold)
   - IP jump detection
   - Concurrent stream limiting

3. **Session Management**:
   - 30-minute default timeout
   - Redis-cached sessions
   - Token-based authentication

### SRT Proxy Streaming

1. **PROXY-ONLY Mode**:
   - No transcoding (resource efficient)
   - Direct stream relay
   - Minimal latency

2. **Encryption**:
   - AES-256 encryption
   - Configurable passphrase
   - Per-stream keys

3. **Access Control**:
   - Device validation before streaming
   - Session-based authorization
   - Concurrent stream enforcement

---

## Monitoring

### Check Logs

```bash
# SRT Proxy logs
pm2 logs srt-proxy-worker

# Device violations
mysql -u fos -p fos_streaming -e "SELECT * FROM device_violations ORDER BY created_at DESC LIMIT 10;"

# Active sessions
mysql -u fos -p fos_streaming -e "SELECT * FROM device_sessions WHERE is_active = 1;"
```

### Redis Monitoring

```bash
# Connect to Redis
redis-cli

# Check keys
KEYS *

# Monitor commands
MONITOR
```

### System Health

```bash
# Check services
systemctl status nginx
systemctl status mysql
systemctl status redis-server

# Check ports
netstat -tlnp | grep -E "9000|9001|6379"

# Resource usage
htop
```

---

## Troubleshooting

### Issue: SRT tools not found

```bash
# Verify installation
which srt-live-transmit

# Reinstall if needed
sudo apt-get install --reinstall srt-tools
```

### Issue: Redis connection failed

```bash
# Check Redis status
systemctl status redis-server

# Test connection
redis-cli ping

# Check config
cat /etc/redis/redis.conf | grep -E "bind|port"
```

### Issue: Device fingerprint not working

1. Clear browser cache
2. Check JavaScript console for errors
3. Verify HTTPS is enabled (required for some APIs)

### Issue: Database migration fails

```bash
# Check for locks
mysql -u fos -p fos_streaming -e "SHOW PROCESSLIST;"

# Kill stuck queries
mysql -u fos -p fos_streaming -e "KILL <process_id>;"

# Retry migration
mysql -u fos -p fos_streaming < database/migrations/2025-11-24_full_security_update.sql
```

---

## Performance Optimization

### 1. Redis Tuning

Edit `/etc/redis/redis.conf`:

```conf
maxmemory 256mb
maxmemory-policy allkeys-lru
save ""  # Disable persistence for cache-only mode
```

### 2. Database Indexes

Already included in migration:
- Device fingerprint lookups
- Session queries
- Violation searches
- Stream status checks

### 3. PM2 Worker Limits

Edit in PM2 Manager UI or database:
- SRT Proxy: 1GB max memory
- Stream workers: 500MB max memory
- Monitor workers: 300MB max memory

---

## Security Checklist

- [ ] Changed default admin password
- [ ] Configured firewall (UFW)
- [ ] Enabled fail2ban
- [ ] Set unique SRT encryption key
- [ ] Restricted database access
- [ ] Configured SSL certificates
- [ ] Limited Redis to localhost
- [ ] Set appropriate file permissions
- [ ] Enabled audit logging
- [ ] Configured backup schedule

---

## Next Steps

After successful deployment:

1. **Configure Streams**: Add SRT sources through admin panel
2. **Test Device Locking**: Create test subscriber and verify fingerprinting
3. **Monitor Performance**: Check PM2 metrics and logs
4. **Plan Scaling**: Prepare for multi-node deployment (Phase 6)
5. **Implement QUIC**: Continue with Phase 3 protocols

---

## Support

- **Documentation**: `/docs/guides/`
- **Issues**: [GitHub Issues](https://github.com/CristianCasapu/FOS-Streaming-Reborn/issues)
- **Logs**: `/home/fos-streaming/fos/storage/logs/`

---

**Important**: This is a PROXY-ONLY streaming platform. No transcoding occurs, ensuring minimal resource usage and maximum scalability.

---

**Document Version**: 1.0
**Last Updated**: 2025-11-24