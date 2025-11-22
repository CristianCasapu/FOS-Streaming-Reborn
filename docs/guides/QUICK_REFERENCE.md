# FOS-Streaming v70 - Quick Reference Card

**Version**: 70.0.0 | **Status**: Production Ready | **Updated**: 2025-11-21

---

## 🚀 One-Command Installation

```bash
# Fresh Debian 12 installation (recommended)
curl -s https://raw.githubusercontent.com/theraw/FOS-Streaming-v70/master/install/debian12 | bash
```

**Installation time**: 10-20 minutes
**Includes**: PHP 8.4, MariaDB 11.4, Nginx 1.26, fail2ban, UFW, all security features

---

## 🔐 Default Credentials

**After installation, access:**
- Web Panel: `http://YOUR_IP:7777/`
- Username: `admin`
- Password: `admin`

⚠️ **CHANGE IMMEDIATELY AFTER FIRST LOGIN!**

---

## 📋 Essential Commands

### Service Management
```bash
# Restart all services
systemctl restart fos-nginx php8.4-fpm mariadb fail2ban

# Check service status
systemctl status fos-nginx php8.4-fpm mariadb fail2ban

# View service logs
journalctl -u fos-nginx -f
journalctl -u php8.4-fpm -f
```

### Security Monitoring
```bash
# Check fail2ban status
fail2ban-client status

# View specific jail
fail2ban-client status fos-auth

# See banned IPs
fail2ban-client get fos-auth banned

# Unban an IP
fail2ban-client set fos-auth unbanip 192.0.2.100

# Check UFW firewall
sudo ufw status verbose
```

### Database Operations
```bash
# Access database
mysql -u root -p fos

# View security dashboard
mysql -u root -p fos -e "SELECT * FROM security_dashboard;"

# View banned IPs
mysql -u root -p fos -e "SELECT * FROM banned_ips WHERE type='banned';"

# View recent security events
mysql -u root -p fos -e "SELECT * FROM security_events ORDER BY created_at DESC LIMIT 10;"

# View failed logins
mysql -u root -p fos -e "SELECT ip_address, COUNT(*) as attempts FROM failed_login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR) GROUP BY ip_address;"
```

### Log Management
```bash
# View authentication logs
tail -f /home/fos-streaming/fos/logs/auth.log

# View security logs
tail -f /home/fos-streaming/fos/logs/security.log

# View fail2ban logs
tail -f /var/log/fail2ban.log

# View nginx error logs
tail -f /var/log/nginx/error.log

# View nginx access logs
tail -f /var/log/nginx/access.log
```

### System Info
```bash
# Check versions
php -v                    # PHP 8.4
mysql --version           # MariaDB 11.4
nginx -v                  # Nginx 1.26
cat /home/fos-streaming/fos/www/VERSION  # FOS version

# Check system resources
free -h                   # Memory usage
df -h                     # Disk usage
top                       # CPU usage
```

---

## 🔧 Configuration File Locations

### Main Configuration
- **FOS Config**: `/home/fos-streaming/fos/www/config.php`
- **PHP-FPM**: `/etc/php/8.4/fpm/pool.d/fos.conf`
- **Nginx**: `/etc/nginx/nginx.conf`
- **MariaDB**: `/etc/mysql/mariadb.conf.d/50-server.cnf`

### Security Configuration
- **fail2ban Jails**: `/etc/fail2ban/jail.d/fos-streaming.local`
- **fail2ban Filters**: `/etc/fail2ban/filter.d/fos-*.conf`
- **UFW Rules**: `/etc/ufw/applications.d/fos-streaming`

### Logs
- **FOS Logs**: `/home/fos-streaming/fos/logs/`
- **fail2ban**: `/var/log/fail2ban.log`
- **Nginx**: `/var/log/nginx/`
- **PHP**: `/var/log/php8.4-fpm.log`
- **MariaDB**: `/var/log/mysql/`

---

## 🛡️ Security Quick Tasks

### IP Management
```bash
# Ban IP via UFW
sudo ufw deny from 192.0.2.100

# Unban IP via UFW
sudo ufw delete deny from 192.0.2.100

# Whitelist IP
sudo ufw allow from 192.0.2.50

# Via fail2ban
fail2ban-client set fos-auth banip 192.0.2.100
fail2ban-client set fos-auth unbanip 192.0.2.100
```

### fail2ban Jail Management
```bash
# Stop a jail
fail2ban-client stop fos-auth

# Start a jail
fail2ban-client start fos-auth

# Reload fail2ban
fail2ban-client reload

# Get jail configuration
fail2ban-client get fos-auth maxretry
fail2ban-client get fos-auth bantime
```

### View Security Status
```bash
# Quick security overview
mysql -u root -p fos << SQL
SELECT 
    (SELECT COUNT(*) FROM banned_ips WHERE type='banned') as banned_ips,
    (SELECT COUNT(*) FROM security_events WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as events_24h,
    (SELECT COUNT(*) FROM failed_login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)) as failed_logins_1h;
SQL
```

---

## 🔄 Common Maintenance Tasks

### Daily
```bash
# Check security events (via web)
# http://YOUR_IP:7777/security_settings.php

# Or via CLI
mysql -u root -p fos -e "SELECT event_type, COUNT(*) as count FROM security_events WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY event_type;"
```

### Weekly
```bash
# Review banned IPs
fail2ban-client status fos-auth | grep "Banned IP"

# Check log sizes
du -sh /home/fos-streaming/fos/logs/*
du -sh /var/log/fail2ban.log
```

### Monthly
```bash
# Clean old logs (automatic, but verify)
ls -lh /home/fos-streaming/fos/logs/*.old

# Review security settings
cat /etc/fail2ban/jail.d/fos-streaming.local
```

---

## 🚨 Troubleshooting Quick Fixes

### Service Won't Start
```bash
# Check syntax
nginx -t
php-fpm8.4 -t

# Check logs
journalctl -xe
tail -50 /var/log/nginx/error.log
```

### Can't Login (Banned Yourself)
```bash
# Check if your IP is banned
fail2ban-client get fos-auth banned | grep YOUR_IP

# Unban yourself
fail2ban-client set fos-auth unbanip YOUR_IP
sudo ufw delete deny from YOUR_IP
```

### High Memory Usage
```bash
# Restart PHP-FPM
systemctl restart php8.4-fpm

# Check pool status
systemctl status php8.4-fpm

# Adjust pool settings if needed
nano /etc/php/8.4/fpm/pool.d/fos.conf
# Modify: pm.max_children, pm.start_servers, etc.
```

### Database Issues
```bash
# Restart MariaDB
systemctl restart mariadb

# Check database status
mysql -u root -p -e "SHOW PROCESSLIST;"

# Optimize tables
mysql -u root -p fos -e "OPTIMIZE TABLE security_events, failed_login_attempts;"
```

---

## 📊 Quick Performance Check

```bash
# Check PHP OPcache status
php -r "print_r(opcache_get_status());"

# Check MariaDB connections
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected';"

# Check Nginx connections
ss -s

# Check fail2ban processing time
fail2ban-client ping
```

---

## 🔐 Security Health Check

```bash
# All-in-one security check
cat << 'SCRIPT' > /tmp/security_check.sh
#!/bin/bash
echo "=== FOS-Streaming v70 Security Health Check ==="
echo ""
echo "1. fail2ban Status:"
fail2ban-client status | grep "Number of jail"
echo ""
echo "2. Active Bans:"
fail2ban-client status fos-auth | grep "Currently banned"
echo ""
echo "3. UFW Status:"
sudo ufw status | grep "Status:"
echo ""
echo "4. Recent Security Events (last 24h):"
mysql -u root -p fos -e "SELECT COUNT(*) as events FROM security_events WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR);" 2>/dev/null
echo ""
echo "5. Total Banned IPs:"
mysql -u root -p fos -e "SELECT COUNT(*) as banned FROM banned_ips WHERE type='banned';" 2>/dev/null
echo ""
echo "6. Failed Logins (last hour):"
mysql -u root -p fos -e "SELECT COUNT(*) as failed FROM failed_login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR);" 2>/dev/null
SCRIPT
chmod +x /tmp/security_check.sh
/tmp/security_check.sh
```

---

## 📱 Web Interface Quick Access

### Main URLs
- **Dashboard**: `http://YOUR_IP:7777/dashboard.php`
- **Security Settings**: `http://YOUR_IP:7777/security_settings.php`
- **Stream Management**: `http://YOUR_IP:7777/streams.php`
- **User Management**: `http://YOUR_IP:7777/admins.php`
- **Settings**: `http://YOUR_IP:7777/settings.php`

### RTMP URLs
- **RTMP Server**: `rtmp://YOUR_IP:1935/live`
- **Stream Key**: Set in web panel
- **Playback**: `http://YOUR_IP:8000/stream/STREAM_KEY.m3u8`

---

## 🆘 Emergency Procedures

### Stop All fail2ban Jails
```bash
fail2ban-client stop
# Or just one jail:
fail2ban-client stop fos-auth
```

### Disable UFW Firewall
```bash
sudo ufw disable
# Re-enable when ready:
sudo ufw enable
```

### Reset Admin Password
```bash
# Access database
mysql -u root -p fos

# Reset password to "admin123"
UPDATE admins SET password = '$argon2id$v=19$m=65536,t=4,p=2$...' WHERE username = 'admin';
# Note: Better to use the web panel or migrate_passwords.php
```

### Full Service Restart
```bash
systemctl restart fos-nginx php8.4-fpm mariadb fail2ban
systemctl status fos-nginx php8.4-fpm mariadb fail2ban
```

---

## 📖 Documentation Quick Links

- **Full Docs**: [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)
- **Security Guide**: [SECURITY_FEATURES.md](SECURITY_FEATURES.md)
- **Quick Start**: [QUICKSTART.md](QUICKSTART.md)
- **Changelog**: [CHANGELOG.md](CHANGELOG.md)
- **Troubleshooting**: [SECURITY_FEATURES.md](SECURITY_FEATURES.md#troubleshooting)

---

## 🎯 Quick Stats

```bash
# One-liner for all important stats
echo "=== FOS-Streaming v70 Stats ===" && \
echo "Version: $(cat /home/fos-streaming/fos/www/VERSION)" && \
echo "PHP: $(php -r 'echo PHP_VERSION;')" && \
echo "MariaDB: $(mysql --version | awk '{print $5}' | cut -d'-' -f1)" && \
echo "Nginx: $(nginx -v 2>&1 | awk '{print $3}')" && \
echo "Uptime: $(uptime -p)" && \
echo "Memory: $(free -h | awk '/^Mem:/ {print $3 "/" $2}')" && \
echo "Disk: $(df -h / | awk 'NR==2 {print $3 "/" $2 " (" $5 ")"}')"
```

---

## 💡 Pro Tips

1. **Always whitelist your management IP** before testing security features
2. **Check logs first** when troubleshooting - they tell you everything
3. **Use the web dashboard** for IP management - it's easier than CLI
4. **Back up before any major changes** - especially database
5. **Monitor fail2ban logs** regularly to see what's being blocked
6. **Adjust ban times** based on your security requirements
7. **Keep documentation handy** - bookmark [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)

---

**Version**: 70.0.0
**Quick Reference**: v1.0
**Last Updated**: 2025-11-21

**Print this page and keep it handy! 📄**
