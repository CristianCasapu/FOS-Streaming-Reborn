# FOS-Streaming v70 - Advanced Security Features

**Comprehensive Security System with fail2ban + UFW + Admin UI**

---

## Overview

FOS-Streaming v70 now includes enterprise-grade security features to protect against:
- Brute force attacks
- Port scanning
- Web application attacks (SQL injection, XSS)
- DDoS attempts
- Unauthorized access
- Network intrusions

### Security Stack

```
┌─────────────────────────────────────────────────┐
│          FOS-Streaming Admin Panel               │
│      (security_settings.php - Web UI)           │
└────────────────┬────────────────────────────────┘
                 │
     ┌───────────┴───────────┐
     │                       │
┌────▼─────┐         ┌──────▼──────┐
│   UFW    │         │  fail2ban   │
│ Firewall │◄────────┤ (Detection) │
└────┬─────┘         └──────┬──────┘
     │                      │
     │    ┌─────────────────┘
     │    │
┌────▼────▼──────────────────────────────────┐
│         Network Traffic                    │
└────────────────────────────────────────────┘
```

---

## Components

### 1. **UFW (Uncomplicated Firewall)**
- Stateful packet filtering firewall
- Easy-to-manage iptables frontend
- Manages allowed/denied ports
- IP-based access control

### 2. **fail2ban**
- Intrusion prevention system
- Monitors log files for malicious activity
- Automatically bans offending IPs
- Configurable detection rules (jails)

### 3. **Admin Web UI**
- Real-time security monitoring dashboard
- Ban/unban IP addresses
- Manage firewall rules
- View security events
- Configure settings

### 4. **Security Logging**
- Comprehensive audit trails
- Failed login tracking
- Attack pattern detection
- Daily security reports

---

## Installation

### Fresh Installation (Debian 12)

The security features are **automatically installed** with the debian12 installer:

```bash
curl -s https://raw.githubusercontent.com/theraw/FOS-Streaming-v70/master/install/debian12 | bash
```

### Manual Installation (Existing Systems)

If you already have FOS-Streaming installed:

```bash
# 1. Clone repository or download security files
cd /home/fos-streaming/fos/www
git pull  # Update to latest

# 2. Run security features installer
cd /home/fos-streaming/fos/www/security
chmod +x install_security_features.sh
sudo bash install_security_features.sh

# 3. Install database tables
mysql -u root -p fos < database_migration.sql

# 4. Copy security files
cp -r ../security ./
cp ../lib/FirewallManager.php lib/
cp ../models/BannedIP.php models/
cp ../security_settings.php ./
```

---

## Configuration

### fail2ban Jails

FOS-Streaming includes 5 pre-configured fail2ban jails:

#### 1. **fos-auth** - Authentication Protection
Monitors: `/home/fos-streaming/fos/logs/auth.log`

**Triggers:**
- Failed login attempts
- CSRF token violations
- Rate limit violations
- Suspicious activity

**Settings:**
- Max retries: 5
- Find time: 15 minutes
- Ban time: 1 hour

#### 2. **fos-security** - Security Events
Monitors: `/home/fos-streaming/fos/logs/security.log`

**Triggers:**
- SQL injection attempts
- XSS attempts
- Security violations

**Settings:**
- Max retries: 3 (stricter)
- Find time: 10 minutes
- Ban time: 2 hours

#### 3. **fos-nginx** - Web Server Protection
Monitors: nginx access logs

**Triggers:**
- 403 Forbidden responses
- 404 scanner attempts
- Malicious user agents
- Directory traversal
- Script injection

**Settings:**
- Max retries: 10
- Find time: 10 minutes
- Ban time: 30 minutes

#### 4. **fos-portscan** - Port Scan Detection
Monitors: `/var/log/syslog`, UFW logs

**Triggers:**
- UFW BLOCK events
- Multiple SYN packets
- Port scanning behavior

**Settings:**
- Max retries: 5
- Find time: 5 minutes
- Ban time: 24 hours

#### 5. **fos-recidive** - Repeat Offenders
Monitors: `/var/log/fail2ban.log`

**Triggers:**
- Previously banned IPs returning

**Settings:**
- Max retries: 3
- Find time: 24 hours
- Ban time: 1 week

### UFW Firewall Rules

Default rules after installation:

```
Port    Service           Access
22/tcp  SSH              ALLOW
7777/tcp FOS Web Panel    ALLOW
8000/tcp FOS Streaming    ALLOW
1935/tcp RTMP            ALLOW
```

**Default Policies:**
- Incoming: DENY (default deny all)
- Outgoing: ALLOW (allow all outbound)

---

## Usage

### Accessing the Security Dashboard

```
URL: http://YOUR_IP:7777/security_settings.php
Login: Use your FOS admin credentials
```

### Dashboard Features

#### 1. **Firewall Management**
- View UFW status (active/inactive)
- See all firewall rules
- Add new rules (port/protocol/action)
- Delete existing rules
- Enable/disable firewall

**Example: Add rule**
```
Port: 443
Protocol: tcp
Action: allow
From: (optional - leave empty for "anywhere")
```

#### 2. **fail2ban Management**
- View fail2ban status
- See all active jails
- View banned IPs per jail
- View jail statistics
- Reload fail2ban configuration

#### 3. **IP Ban Management**
- **Ban IP**: Manually ban an IP address
  - Specify IP
  - Provide reason
  - Set duration (or permanent)

- **Whitelist IP**: Protect trusted IPs from being banned
  - Whitelisted IPs bypass all bans

- **Unban IP**: Remove IP from ban list

#### 4. **Security Events Log**
- View recent security events
- See failed login attempts
- Monitor attack patterns
- Export logs

#### 5. **Statistics**
- Total banned IPs
- Events in last 24 hours
- Critical events
- Failed login attempts
- Active firewall rules

---

## Common Operations

### Ban an IP Address

**Via Admin UI:**
1. Go to `security_settings.php`
2. Click "Ban IP Address"
3. Enter IP: `192.168.1.100`
4. Enter reason: "Brute force attack"
5. Enter duration: `3600` (1 hour) or leave empty for permanent

**Via Command Line:**
```bash
# Using UFW
sudo ufw deny from 192.168.1.100

# Using fail2ban
sudo fail2ban-client set fos-auth banip 192.168.1.100
```

### Unban an IP Address

**Via Admin UI:**
1. Go to `security_settings.php`
2. Find IP in "Banned IPs" section
3. Click "Unban" button

**Via Command Line:**
```bash
# Using UFW
sudo ufw delete deny from 192.168.1.100

# Using fail2ban (per jail)
sudo fail2ban-client set fos-auth unbanip 192.168.1.100
```

### Whitelist a Trusted IP

**Via Admin UI:**
1. Go to `security_settings.php`
2. Click "Whitelist IP Address"
3. Enter IP: `203.0.113.50`
4. Enter reason: "Office IP"

**Via Database:**
```sql
INSERT INTO banned_ips (ip_address, type, reason, permanent)
VALUES ('203.0.113.50', 'whitelisted', 'Trusted source', 1);
```

### Add Firewall Rule

**Via Admin UI:**
1. Go to `security_settings.php`
2. Click "Add Firewall Rule"
3. Enter port: `8443`
4. Select protocol: `tcp`
5. Select action: `allow`

**Via Command Line:**
```bash
sudo ufw allow 8443/tcp
```

### View fail2ban Status

**Via Admin UI:**
- Dashboard shows all jails and statistics

**Via Command Line:**
```bash
# General status
sudo fail2ban-client status

# Specific jail
sudo fail2ban-client status fos-auth

# All banned IPs
sudo fail2ban-client banned
```

---

## Security Best Practices

### 1. **Change Default Ports**

Don't use default ports for production:

```bash
# Edit nginx configuration
nano /home/fos-streaming/fos/nginx/conf/nginx.conf

# Change:
listen 7777;  # to 8443 or other non-standard port
listen 8000;  # to 8444 or other

# Add to UFW
sudo ufw allow 8443/tcp
sudo ufw allow 8444/tcp

# Remove old ports
sudo ufw delete allow 7777/tcp
sudo ufw delete allow 8000/tcp
```

### 2. **Whitelist Your IP**

Always whitelist your management IP:

```bash
# Via command line
echo "YOUR_IP" | sudo tee -a /etc/fail2ban/ignoreip.local

# Or via admin UI
# Go to security_settings.php → Whitelist IP
```

### 3. **Enable Email Alerts**

Edit `/etc/fail2ban/jail.d/fos-streaming.local`:

```ini
[DEFAULT]
destemail = admin@yourdomain.com
sender = fail2ban@yourdomain.com
action = %(action_mwl)s  # mail with logs
```

### 4. **Tune fail2ban Sensitivity**

For high-security environments:

```ini
[fos-auth]
maxretry = 3      # Stricter (default: 5)
findtime = 600    # 10 minutes
bantime  = 7200   # 2 hours
```

For development environments:

```ini
[fos-auth]
maxretry = 10     # More lenient
findtime = 1800   # 30 minutes
bantime  = 600    # 10 minutes
```

### 5. **Regular Security Audits**

```bash
# Check banned IPs
sudo fail2ban-client banned

# Review security log
tail -100 /home/fos-streaming/fos/logs/security.log

# Check UFW rules
sudo ufw status numbered

# Review failed logins
grep "FAILED" /home/fos-streaming/fos/logs/auth.log | tail -50
```

### 6. **Geographic IP Blocking**

Block IPs from specific countries (requires GeoIP):

```bash
# Install GeoIP
sudo apt-get install geoip-bin geoip-database

# Block country (example: block China)
# Add to nginx.conf in http block:
geo $block_country {
    default no;
    CN yes;  # China
    RU yes;  # Russia
}

server {
    if ($block_country = yes) {
        return 444;
    }
}
```

### 7. **DDoS Protection**

Already configured in nginx, but you can tune:

```nginx
# Edit /home/fos-streaming/fos/nginx/conf/nginx.conf

# Stricter rate limiting
limit_req_zone $binary_remote_addr zone=login_limit:10m rate=2r/m;  # 2 per minute
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=5r/s;    # 5 per second

# Connection limiting
limit_conn_zone $binary_remote_addr zone=conn_limit:10m;
limit_conn conn_limit 10;  # Max 10 concurrent connections per IP
```

---

## Monitoring & Alerts

### Real-Time Monitoring

**View live security log:**
```bash
tail -f /home/fos-streaming/fos/logs/security.log
```

**View live fail2ban log:**
```bash
tail -f /var/log/fail2ban.log
```

**View live auth attempts:**
```bash
tail -f /home/fos-streaming/fos/logs/auth.log
```

### Daily Security Report

Automatically generated at `/var/log/fos-security-report.log`

View report:
```bash
cat /var/log/fos-security-report.log
```

### Statistics Query

**Get security statistics:**
```sql
mysql -u root -p fos

SELECT * FROM security_dashboard;
```

**Recent security events:**
```sql
SELECT * FROM security_events
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC
LIMIT 50;
```

**Top attacked IPs:**
```sql
SELECT ip_address, COUNT(*) as attempts
FROM failed_login_attempts
WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ip_address
ORDER BY attempts DESC
LIMIT 10;
```

---

## Troubleshooting

### Issue: Can't Access After Enabling UFW

**Problem:** Accidentally blocked yourself

**Solution:**
```bash
# Access via console/SSH from allowed IP
# Or if you have console access:

# Disable UFW temporarily
sudo ufw disable

# Add your IP
sudo ufw allow from YOUR_IP

# Re-enable
sudo ufw enable
```

### Issue: Legitimate User Being Banned

**Problem:** fail2ban banning legitimate users

**Solution:**
```bash
# 1. Unban the IP
sudo fail2ban-client set fos-auth unbanip IP_ADDRESS

# 2. Whitelist the IP
# Via admin UI or:
sudo ufw allow from IP_ADDRESS

# 3. Add to fail2ban whitelist
echo "IP_ADDRESS" | sudo tee -a /etc/fail2ban/ignoreip.local
sudo fail2ban-client reload
```

### Issue: fail2ban Not Starting

**Problem:** fail2ban service won't start

**Solution:**
```bash
# Check status
sudo systemctl status fail2ban

# Check configuration
sudo fail2ban-client -t

# Check logs
sudo journalctl -u fail2ban -n 50

# Common fix: syntax error in jail config
sudo fail2ban-client -t
# Fix any errors shown

# Restart
sudo systemctl restart fail2ban
```

### Issue: Too Many False Positives

**Problem:** fail2ban banning too aggressively

**Solution:**
```bash
# Edit jail configuration
sudo nano /etc/fail2ban/jail.d/fos-streaming.local

# Increase maxretry and findtime
[fos-auth]
maxretry = 10  # Increase from 5
findtime = 1800 # Increase to 30 minutes

# Reload
sudo fail2ban-client reload
```

### Issue: Security Dashboard Not Loading

**Problem:** security_settings.php shows errors

**Solution:**
```bash
# 1. Check if security tables exist
mysql -u root -p fos -e "SHOW TABLES LIKE '%security%';"

# 2. Run migration if missing
mysql -u root -p fos < /home/fos-streaming/fos/www/security/database_migration.sql

# 3. Check permissions
ls -la /home/fos-streaming/fos/www/security_settings.php

# 4. Check PHP logs
tail -f /home/fos-streaming/fos/logs/php-fpm.log
```

---

## Database Schema

### banned_ips Table

```sql
CREATE TABLE `banned_ips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `type` enum('banned','whitelisted') NOT NULL,
  `reason` text NOT NULL,
  `banned_by` varchar(50) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `permanent` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`)
);
```

### security_events Table

```sql
CREATE TABLE `security_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `severity` enum('low','medium','high','critical'),
  `description` text NOT NULL,
  `user_agent` text,
  `request_uri` varchar(255) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

---

## API Reference

### FirewallManager Class

```php
use FOS\Security\FirewallManager;

// UFW Operations
FirewallManager::getUFWStatus();
FirewallManager::enableUFW();
FirewallManager::disableUFW();
FirewallManager::addUFWRule($port, $protocol, $action, $from);
FirewallManager::deleteUFWRule($port, $protocol);
FirewallManager::banIP($ip, $reason);
FirewallManager::unbanIP($ip);

// fail2ban Operations
FirewallManager::getFail2banStatus();
FirewallManager::getJailStatus($jailName);
FirewallManager::fail2banBanIP($jailName, $ip);
FirewallManager::fail2banUnbanIP($jailName, $ip);
FirewallManager::reloadFail2ban();
FirewallManager::getAllBannedIPs();

// Statistics
FirewallManager::getSecurityStats();
```

### BannedIP Model

```php
// Check if IP is banned
BannedIP::isBanned('192.168.1.100');

// Check if whitelisted
BannedIP::isWhitelisted('192.168.1.100');

// Ban an IP
BannedIP::ban('192.168.1.100', 'Brute force', 3600, 'admin');

// Whitelist an IP
BannedIP::whitelist('192.168.1.100', 'Trusted', 'admin');

// Unban
BannedIP::unban('192.168.1.100');

// Get all active bans
BannedIP::getActiveBans();

// Clean expired bans
BannedIP::cleanExpired();
```

---

## Performance Impact

### Resource Usage

**fail2ban:**
- CPU: < 1% average
- RAM: ~50-100 MB
- Disk I/O: Minimal (log reading)

**UFW:**
- CPU: Negligible
- RAM: ~10-20 MB
- Network: No overhead (kernel-level)

**Total Impact:** < 2% system resources

### Optimization Tips

1. **Log Rotation:**
```bash
# Already configured, but verify:
ls -lh /home/fos-streaming/fos/logs/
# Logs should auto-rotate at 10MB
```

2. **Database Cleanup:**
```bash
# Automatic cleanup runs daily
# Manual cleanup:
mysql -u root -p fos -e "CALL cleanup_old_security_data();"
```

3. **fail2ban Performance:**
```ini
# In /etc/fail2ban/jail.local
[DEFAULT]
# Use polling instead of inotify for log monitoring (lower CPU)
backend = polling
```

---

## Changelog

### Version 1.0 (2025-11-21)

**Added:**
- fail2ban integration with 5 custom jails
- UFW firewall management
- Admin web UI for security management
- IP ban/whitelist system
- Real-time security dashboard
- Comprehensive security logging
- Automatic intrusion prevention
- Daily security reports
- Database-backed ban management
- Geographic IP blocking support

---

## Support & Resources

### Documentation
- Main README: [README.md](README.md)
- Migration Guide: [MIGRATION_PLAN.md](MIGRATION_PLAN.md)
- Quick Start: [QUICKSTART.md](QUICKSTART.md)

### Configuration Files
- fail2ban filters: `security/fail2ban/*.conf`
- UFW rules: `/etc/ufw/applications.d/fos-streaming`
- Jail config: `/etc/fail2ban/jail.d/fos-streaming.local`

### Log Files
- Security events: `/home/fos-streaming/fos/logs/security.log`
- Authentication: `/home/fos-streaming/fos/logs/auth.log`
- fail2ban: `/var/log/fail2ban.log`
- Security report: `/var/log/fos-security-report.log`

### Commands Reference

```bash
# fail2ban
sudo fail2ban-client status
sudo fail2ban-client status <jail>
sudo fail2ban-client set <jail> banip <ip>
sudo fail2ban-client set <jail> unbanip <ip>
sudo fail2ban-client reload

# UFW
sudo ufw status
sudo ufw enable
sudo ufw disable
sudo ufw allow <port>/<protocol>
sudo ufw deny from <ip>
sudo ufw delete <rule_number>

# Service Management
sudo systemctl status fail2ban
sudo systemctl status ufw
sudo systemctl restart fail2ban
```

---

**Security Version**: 1.0
**Last Updated**: 2025-11-21
**Compatible With**: FOS-Streaming v70.2 (Debian 12)
