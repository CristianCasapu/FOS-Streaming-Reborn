# FOS-Streaming v70 - Quick Start Guide

**Debian 12 Edition - Fast Deployment in 15 Minutes**

---

## For New Installations (Fresh Debian 12 Server)

### One-Command Install

```bash
curl -s https://raw.githubusercontent.com/theraw/FOS-Streaming-v70/master/install/debian12 | bash
```

**That's it!** Wait 10-20 minutes for installation to complete.

### After Installation

1. **Access Your Panel**
   ```
   URL: http://YOUR_SERVER_IP:7777
   Username: admin
   Password: admin
   ```

2. **IMMEDIATELY Change Password!**
   - Click Admin Management
   - Change default password

3. **Configure IP Address**
   - Go to Settings
   - Change "Web ip: *" to your public IP
   - Save

4. **Done!** You can now add users and streams.

---

## For Upgrades (Existing FOS-Streaming on Debian 11)

### Before You Start

⚠️ **CRITICAL: Backup First!**

```bash
# Backup database
mysqldump -u root -p fos > /root/fos_backup_$(date +%Y%m%d).sql

# Backup files
tar -czf /root/fos_files_backup_$(date +%Y%m%d).tar.gz /home/fos-streaming/
```

### Upgrade Steps

```bash
# 1. Upgrade OS (Debian 11 → 12)
sed -i 's/bullseye/bookworm/g' /etc/apt/sources.list
apt-get update
apt-get upgrade -y
apt-get dist-upgrade -y
reboot

# 2. After reboot, install FOS-Streaming for Debian 12
curl -s https://raw.githubusercontent.com/theraw/FOS-Streaming-v70/master/install/debian12 | bash

# 3. Restore your database
mysql -u root -p fos < /root/fos_backup_YYYYMMDD.sql

# 4. Migrate passwords to secure hashing
cd /home/fos-streaming/fos/www
php migrate_passwords.php

# 5. Activate secure login
mv index.php index.php.old
mv index-secure.php index.php

# 6. Restart everything
systemctl restart fos-nginx php8.4-fpm mariadb

# 7. Test login
# Visit http://YOUR_IP:7777 and login
```

---

## Quick Configuration

### Change Panel Port

```bash
# Edit nginx config
nano /home/fos-streaming/fos/nginx/conf/nginx.conf

# Change line:
listen 7777;
# To your desired port, e.g.:
listen 8080;

# Restart nginx
systemctl restart fos-nginx
```

### Add Firewall Rules

```bash
# Install UFW
apt-get install ufw

# Allow required ports
ufw allow 22/tcp      # SSH
ufw allow 7777/tcp    # Web panel
ufw allow 8000/tcp    # Streaming
ufw allow 1935/tcp    # RTMP

# Enable firewall
ufw enable
```

### Setup SSL/TLS (Optional but Recommended)

```bash
# Install certbot
apt-get install certbot

# Get certificate
certbot certonly --standalone -d yourdomain.com

# Certificates will be in:
# /etc/letsencrypt/live/yourdomain.com/

# Edit nginx config to use SSL
nano /home/fos-streaming/fos/nginx/conf/nginx.conf

# Add SSL configuration (see nginx SSL docs)
```

---

## Adding Your First Stream

### Step 1: Create User

1. Login to panel
2. Navigate to **Users** → **Add User**
3. Fill in:
   - Username: `testuser`
   - Password: `yourpassword`
   - Max Streams: `5`
4. Click **Add User**

### Step 2: Add Stream

1. Navigate to **Streams** → **Add Stream**
2. Fill in:
   - Stream Name: `Test Stream`
   - Stream Source: `http://example.com/stream.m3u8`
   - User: Select `testuser`
   - Transcode Profile: **Default 1** (recommended)
3. Click **Add Stream**

### Step 3: Start Stream

1. Go to **Streams** → **Manage Streams**
2. Find your stream
3. Click **Start**

### Step 4: View Stream

**Web Player:**
```
http://YOUR_IP:7777/play.php?stream=STREAM_ID
```

**Direct URL:**
```
http://YOUR_IP:8000/live/testuser/yourpassword/STREAM_ID
```

**HLS Playlist:**
```
http://YOUR_IP:8000/live/testuser/yourpassword/STREAM_ID/index.m3u8
```

---

## Troubleshooting

### Services Not Running

```bash
# Check service status
systemctl status fos-nginx
systemctl status php8.4-fpm
systemctl status mariadb

# Restart if needed
systemctl restart fos-nginx
systemctl restart php8.4-fpm
systemctl restart mariadb
```

### Can't Access Web Panel

```bash
# Check if nginx is listening
netstat -tlnp | grep 7777

# Check nginx logs
tail -f /home/fos-streaming/fos/logs/error.log

# Check PHP-FPM logs
tail -f /home/fos-streaming/fos/logs/php-fpm.log
```

### Streams Not Starting

```bash
# Check FFmpeg
/usr/local/bin/ffmpeg -version

# Check permissions
ls -la /home/fos-streaming/fos/www/hl/
# Should be: drwxrwxrwx nginx nginx

# Fix permissions
chmod 777 /home/fos-streaming/fos/www/hl
chown -R nginx:nginx /home/fos-streaming/fos/www
```

### Database Connection Issues

```bash
# Check MariaDB
systemctl status mariadb

# Check credentials
cat /home/fos-streaming/fos/www/config.php

# Get MySQL password
cat /root/MYSQL_ROOT_PASSWORD

# Test connection
mysql -u fos -p
# Enter password from /root/MYSQL_ROOT_PASSWORD
```

---

## Security Checklist

After installation, complete these security steps:

- [ ] Change default admin password
- [ ] Setup firewall (UFW)
- [ ] Configure SSL/TLS
- [ ] Review /home/fos-streaming/fos/logs/security.log
- [ ] Disable root SSH (edit /etc/ssh/sshd_config)
- [ ] Setup automatic backups
- [ ] Change MySQL root password
- [ ] Remove default users if any

---

## Service Management

### Check Status

```bash
# All services
systemctl status fos-nginx php8.4-fpm mariadb
```

### Start Services

```bash
systemctl start fos-nginx
systemctl start php8.4-fpm
systemctl start mariadb
```

### Stop Services

```bash
systemctl stop fos-nginx
systemctl stop php8.4-fpm
systemctl stop mariadb
```

### Restart Services

```bash
systemctl restart fos-nginx
systemctl restart php8.4-fpm
systemctl restart mariadb
```

### Enable on Boot

```bash
systemctl enable fos-nginx
systemctl enable php8.4-fpm
systemctl enable mariadb
```

---

## Important File Locations

| Item | Location |
|------|----------|
| **Web Panel** | `/home/fos-streaming/fos/www/` |
| **Nginx Config** | `/home/fos-streaming/fos/nginx/conf/nginx.conf` |
| **PHP Config** | `/etc/php/8.4/fpm/pool.d/www.conf` |
| **Logs** | `/home/fos-streaming/fos/logs/` |
| **HLS Output** | `/home/fos-streaming/fos/www/hl/` |
| **MySQL Password** | `/root/MYSQL_ROOT_PASSWORD` |
| **FFmpeg** | `/usr/local/bin/ffmpeg` |

---

## Default Ports

| Service | Port | Purpose |
|---------|------|---------|
| **Web Panel** | 7777 | Management interface |
| **Streaming** | 8000 | HLS streaming output |
| **RTMP** | 1935 | RTMP ingest |
| **MariaDB** | 3306 | Database (localhost only) |

---

## Performance Tuning

### For High-Traffic Servers

Edit `/home/fos-streaming/fos/nginx/conf/nginx.conf`:

```nginx
worker_processes auto;  # Use all CPU cores
worker_connections 65535;  # Max connections per worker

# In http block:
client_max_body_size 999m;  # Max upload size
keepalive_timeout 10;  # Connection timeout
```

Edit `/etc/php/8.4/fpm/pool.d/www.conf`:

```ini
pm.max_children = 300  # Increase for more traffic
pm.start_servers = 20  # Start with more workers
pm.min_spare_servers = 20
pm.max_spare_servers = 150
```

### For Low-Resource Servers

```nginx
worker_processes 2;  # Limit workers
worker_connections 10000;  # Reduce connections
```

```ini
pm.max_children = 50  # Reduce workers
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 20
```

---

## Backup & Restore

### Manual Backup

```bash
#!/bin/bash
BACKUP_DIR="/root/backups"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u root -p$(cat /root/MYSQL_ROOT_PASSWORD) fos > $BACKUP_DIR/fos_db_$DATE.sql

# Backup files
tar -czf $BACKUP_DIR/fos_files_$DATE.tar.gz /home/fos-streaming/fos/

# Backup configs
tar -czf $BACKUP_DIR/fos_configs_$DATE.tar.gz /etc/php/8.4/ /root/MYSQL_ROOT_PASSWORD

echo "Backup completed: $BACKUP_DIR"
```

### Automated Daily Backup

```bash
# Create backup script
nano /root/backup_fos.sh

# Paste the backup script above, then:
chmod +x /root/backup_fos.sh

# Add to cron (daily at 2 AM)
crontab -e
# Add line:
0 2 * * * /root/backup_fos.sh
```

### Restore from Backup

```bash
# Stop services
systemctl stop fos-nginx php8.4-fpm

# Restore database
mysql -u root -p fos < /root/backups/fos_db_YYYYMMDD_HHMMSS.sql

# Restore files
tar -xzf /root/backups/fos_files_YYYYMMDD_HHMMSS.tar.gz -C /

# Start services
systemctl start fos-nginx php8.4-fpm mariadb
```

---

## Monitoring

### View Logs in Real-Time

```bash
# Error log
tail -f /home/fos-streaming/fos/logs/error.log

# Security log
tail -f /home/fos-streaming/fos/logs/security.log

# Authentication log
tail -f /home/fos-streaming/fos/logs/auth.log

# All logs
tail -f /home/fos-streaming/fos/logs/*.log
```

### Check System Resources

```bash
# CPU and RAM
htop

# Disk space
df -h

# Network usage
iftop

# Active connections
netstat -ant | grep ESTABLISHED | wc -l
```

---

## Getting Help

### Check Documentation

- **README.md** - Full documentation
- **MIGRATION_PLAN.md** - Technical details
- **IMPLEMENTATION_SUMMARY.md** - Implementation overview

### Common Issues

**Issue: "Too many login attempts"**
```bash
# Wait 15 minutes or reset rate limiting:
systemctl restart php8.4-fpm
```

**Issue: "CSRF validation failed"**
```bash
# Clear browser cookies and try again
# Or regenerate session:
rm -rf /home/fos-streaming/fos/sessions/*
```

**Issue: "Database connection failed"**
```bash
# Check credentials
cat /home/fos-streaming/fos/www/config.php
cat /root/MYSQL_ROOT_PASSWORD

# Test connection
mysql -u fos -p
```

### Report Bugs

GitHub Issues: https://github.com/theraw/FOS-Streaming-v70/issues

---

## Updates

### System Updates

```bash
# Update packages
apt-get update
apt-get upgrade

# Update FOS-Streaming (backup first!)
# Re-run installer to get latest version
```

### Stay Informed

Watch the GitHub repository for updates:
https://github.com/theraw/FOS-Streaming-v70

---

**Need more details?** See [README.md](README.md) for complete documentation.

**Technical details?** See [MIGRATION_PLAN.md](MIGRATION_PLAN.md) for architecture and security information.

---

**Quick Start Version**: 1.0
**Last Updated**: 2025-11-21
**Compatible With**: Debian 12 (Bookworm), PHP 8.4, MariaDB 11.4
