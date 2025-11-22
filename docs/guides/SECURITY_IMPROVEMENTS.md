# FOS-Streaming v70 - Security Improvements

## Overview

This document outlines the security improvements implemented in FOS-Streaming v70, focusing on random port selection, SSL/TLS encryption, and Cloudflare compatibility.

## Key Security Features

### 1. Random Port Selection

Every new installation automatically selects random, unused ports for all services.

**Cloudflare SSL-Compatible Ports (Web & Stream):**
- 2053, 2083, 2087, 2096, 8443

**RTMP Port Ranges:**
- 1935-1999 (standard RTMP range)
- 8000-8999 (alternative streaming ports)

**Benefits:**
- Reduces automated attack surface by not using predictable ports
- Ensures Cloudflare SSL proxy compatibility for HTTPS services
- Prevents port conflicts during installation
- Makes port scanning attacks less effective
- Each installation has unique port configuration

**How it works:**
- Installer checks for available ports using `lsof` and `netstat`
- Web/Stream: Randomly selects from Cloudflare-compatible ports
- RTMP: Randomly selects from streaming port ranges
- Falls back to 8000-8999 range if Cloudflare ports unavailable
- Ensures all three ports (web, stream, RTMP) are different and available

### 2. SSL/TLS Encryption (HTTPS)

All installations now use HTTPS by default with self-signed certificates initially.

**Features:**
- TLS 1.2 and TLS 1.3 support
- HTTP/2 enabled for better performance
- Strong cipher suites (HIGH:!aNULL:!MD5)
- Server cipher preference enabled

**Certificate Management:**
- Self-signed certificates generated during installation
- Easy upgrade to Let's Encrypt using provided script
- Automatic renewal with certbot hooks

### 3. Dynamic Port Configuration

Ports are no longer hardcoded in the application.

**Configuration Files:**
- `config/ports.php` - Stores selected ports
- `lib/PortManager.php` - Port management utilities
- `lib/PortHelper.php` - Helper functions for PHP application

**PHP Constants:**
```php
FOS_WEB_PORT      // Web panel HTTPS port (random Cloudflare SSL)
FOS_STREAM_PORT   // Streaming HTTPS port (random Cloudflare SSL)
FOS_RTMP_PORT     // RTMP port (random)
```

## Installation

### Automated Installation

The Debian 12 installer now includes all security features:

```bash
bash /path/to/install/debian12
```

The installer will:
1. Select random available Cloudflare-compatible ports
2. Install all dependencies
3. Generate self-signed SSL certificates
4. Configure nginx with HTTPS and HTTP/2
5. Save port configuration
6. Display installation summary with URLs

### Post-Installation

After installation, access your panel at:
```
https://YOUR_SERVER_IP:RANDOM_WEB_PORT
```

**Note:** Your browser will show a certificate warning because we're using self-signed certificates. This is expected. Click "Advanced" and proceed.

## Upgrading to Let's Encrypt

For production use, upgrade to Let's Encrypt SSL certificates:

### Prerequisites
1. Point your domain to the server IP
2. Ensure ports are open in firewall
3. Wait for DNS propagation

### Upgrade Process

```bash
bash /root/upgrade-to-letsencrypt.sh
```

The script will:
1. Prompt for domain name
2. Prompt for email address
3. Install certbot
4. Stop nginx temporarily
5. Obtain Let's Encrypt certificate
6. Link certificates to nginx
7. Configure automatic renewal
8. Restart nginx

### Automatic Renewal

Let's Encrypt certificates are valid for 90 days. The upgrade script configures automatic renewal:

- Certbot timer runs daily
- Certificates auto-renew 30 days before expiration
- Nginx automatically reloads after renewal

Check renewal status:
```bash
systemctl status certbot.timer
certbot certificates
```

## Cloudflare Integration

Your installation is pre-configured for Cloudflare SSL proxy.

### Setup Steps

1. **Add Domain to Cloudflare**
   - Create a Cloudflare account
   - Add your domain
   - Update nameservers at your registrar

2. **Configure SSL/TLS Mode**
   - Go to SSL/TLS settings in Cloudflare
   - Set mode to:
     - "Full" for self-signed certificates
     - "Full (strict)" for Let's Encrypt certificates

3. **Create DNS Records**
   ```
   Type: A
   Name: @ (or subdomain)
   Content: YOUR_SERVER_IP
   Proxy: Enabled (orange cloud)
   ```

4. **Configure Ports**
   - Cloudflare automatically proxies standard ports (80, 443)
   - Your custom ports work through Cloudflare's SSL proxy
   - No additional configuration needed

### Benefits of Cloudflare

- DDoS protection
- Web Application Firewall (WAF)
- CDN for static assets
- SSL/TLS encryption between client and Cloudflare
- Analytics and security insights

## Port Configuration Management

### Viewing Current Ports

```bash
cat /home/fos-streaming/fos/www/config/ports.php
```

Example output:
```php
return [
    'web_port' => 2053,      // Web panel HTTPS port
    'stream_port' => 2083,   // Streaming HTTPS port
    'rtmp_port' => 1947,     // RTMP port (random)
];
```

### Changing Ports Manually

If you need to change ports after installation:

1. **Update Port Configuration:**
   ```bash
   nano /home/fos-streaming/fos/www/config/ports.php
   ```

2. **Update Nginx Configuration:**
   ```bash
   nano /home/fos-streaming/fos/nginx/conf/nginx.conf
   ```

   Update `listen` directives in both server blocks.

3. **Update Firewall:**
   ```bash
   ufw allow NEW_PORT/tcp
   ufw delete allow OLD_PORT/tcp
   ```

4. **Restart Services:**
   ```bash
   systemctl restart fos-nginx
   ```

### Using Port Helper in PHP

```php
// Include the helper
require_once __DIR__ . '/lib/PortHelper.php';

// Get individual ports
$webPort = PortHelper::getWebPort();
$streamPort = PortHelper::getStreamPort();

// Get full URLs
$webUrl = PortHelper::getWebUrl('example.com');
$streamUrl = PortHelper::getStreamUrl('example.com');

// Check protocol
$isHttps = PortHelper::isHttps();
```

## Security Best Practices

### 1. Certificate Management

- **Development/Testing:** Self-signed certificates are fine
- **Production:** Always use Let's Encrypt or commercial certificates
- **Renewal:** Monitor certificate expiration dates
- **Backup:** Keep certificate backups in secure location

### 2. Firewall Configuration

The installer automatically configures firewall rules for your random ports. You can verify with:

```bash
# View current firewall rules
ufw status

# You should see rules like:
# RANDOM_WEB_PORT/tcp    ALLOW       Anywhere    # FOS Web Panel HTTPS
# RANDOM_STREAM_PORT/tcp ALLOW       Anywhere    # FOS Streaming HTTPS
# RANDOM_RTMP_PORT/tcp   ALLOW       Anywhere    # FOS RTMP
```

Manual firewall configuration (if needed):

```bash
# Allow web panel
ufw allow RANDOM_WEB_PORT/tcp comment 'FOS Web Panel HTTPS'

# Allow streaming
ufw allow RANDOM_STREAM_PORT/tcp comment 'FOS Streaming HTTPS'

# Allow RTMP
ufw allow RANDOM_RTMP_PORT/tcp comment 'FOS RTMP'

# Allow SSH (if not already)
ufw allow 22/tcp

# Enable firewall
ufw enable
```

### 3. Regular Updates

Keep your system and FOS-Streaming updated:

```bash
# System updates
apt-get update
apt-get upgrade

# Check for FOS-Streaming updates
cd /home/fos-streaming/fos/www
git pull
```

### 4. Strong Passwords

- Change default admin password immediately after installation
- Use strong, unique passwords for:
  - Admin panel
  - MySQL/MariaDB
  - Stream users
- Consider using a password manager

### 5. Database Security

The MySQL root password is saved in:
```
/root/MYSQL_ROOT_PASSWORD
```

**Recommendations:**
- Keep this file secure (already set to 600 permissions)
- Never commit passwords to version control
- Consider rotating passwords periodically

## Using Random RTMP Port

When connecting RTMP sources (like OBS Studio), you'll need to use the random port assigned during installation.

### Finding Your RTMP Port

```bash
# View your RTMP port
grep rtmp_port /home/fos-streaming/fos/www/config/ports.php

# Or use PHP
php -r "print_r(include('/home/fos-streaming/fos/www/config/ports.php'));"
```

### OBS Studio Configuration

In OBS Studio, configure your stream settings:

**Settings → Stream:**
- Service: Custom
- Server: `rtmp://YOUR_SERVER_IP:RANDOM_RTMP_PORT/live`
- Stream Key: `YOUR_STREAM_KEY`

Example:
```
Server: rtmp://192.168.1.100:1947/live
Stream Key: mystream
```

### FFmpeg RTMP Publishing

```bash
ffmpeg -re -i input.mp4 -c copy -f flv rtmp://YOUR_SERVER_IP:RANDOM_RTMP_PORT/live/STREAM_KEY
```

### Important Notes

- RTMP uses a **non-standard port** for security
- Update all your streaming sources with the new RTMP port
- RTMP port is **not proxied through Cloudflare** (RTMP is not HTTP-based)
- If using Cloudflare, ensure RTMP goes directly to your server IP, not the Cloudflare proxy

## Troubleshooting

### Certificate Errors

**Problem:** Browser shows "Your connection is not private"

**Solution:** This is expected with self-signed certificates. Either:
- Click "Advanced" → "Proceed to site (unsafe)"
- Upgrade to Let's Encrypt using `/root/upgrade-to-letsencrypt.sh`

### Port Already in Use

**Problem:** Installation fails because port is in use

**Solution:** The installer automatically selects available ports. If all Cloudflare ports are taken, it uses 8000-8999 range.

To manually check port usage:
```bash
lsof -i :PORT_NUMBER
netstat -tuln | grep PORT_NUMBER
```

### Nginx Won't Start

**Problem:** Nginx fails to start after configuration changes

**Solution:**
```bash
# Check configuration syntax
/home/fos-streaming/fos/nginx/sbin/nginx -t -c /home/fos-streaming/fos/nginx/conf/nginx.conf

# Check error logs
tail -f /home/fos-streaming/fos/logs/error.log

# Verify certificate files exist
ls -la /home/fos-streaming/fos/nginx/conf/certs/
```

### Let's Encrypt Errors

**Problem:** certbot fails to obtain certificate

**Common causes:**
- Domain not pointing to server IP
- Ports 80/443 blocked by firewall
- DNS not propagated
- Rate limits exceeded

**Solution:**
```bash
# Check DNS
dig YOUR_DOMAIN

# Test connectivity
curl -I http://YOUR_DOMAIN

# Check certbot logs
tail -f /var/log/letsencrypt/letsencrypt.log
```

## File Locations

### Configuration Files
```
/home/fos-streaming/fos/www/config.php           - Main config
/home/fos-streaming/fos/www/config/ports.php     - Port config
/home/fos-streaming/fos/nginx/conf/nginx.conf    - Nginx config
```

### SSL Certificates
```
/home/fos-streaming/fos/nginx/conf/certs/        - Certificate directory
/home/fos-streaming/fos/nginx/conf/certs/fullchain.pem
/home/fos-streaming/fos/nginx/conf/certs/privkey.pem
```

### Scripts
```
/root/upgrade-to-letsencrypt.sh                  - SSL upgrade script
/home/casapu/projects/FOS-Streaming-v70/install/debian12  - Main installer
```

### Library Files
```
/home/fos-streaming/fos/www/lib/PortManager.php  - Port management class
/home/fos-streaming/fos/www/lib/PortHelper.php   - Port helper functions
```

### Logs
```
/home/fos-streaming/fos/logs/error.log           - Nginx error log
/home/fos-streaming/fos/logs/streaming-access.log - Streaming access log
/home/fos-streaming/fos/logs/panel-access.log    - Panel access log
/var/log/letsencrypt/letsencrypt.log             - Certbot log
```

## Technical Details

### Port Selection Algorithm

1. Define Cloudflare SSL-compatible ports: [2053, 2083, 2087, 2096, 8443]
2. Shuffle array randomly
3. Check each port for availability using `lsof` and `netstat`
4. Select first available port
5. Repeat for second port, excluding first selection
6. Fall back to 8000-8999 range if needed

### SSL Configuration

**Nginx SSL Settings:**
```nginx
ssl_certificate /path/to/fullchain.pem;
ssl_certificate_key /path/to/privkey.pem;
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers HIGH:!aNULL:!MD5;
ssl_prefer_server_ciphers on;
```

**HTTP/2 Support:**
```nginx
listen PORT ssl http2;
listen [::]:PORT ssl http2;
```

### Port Configuration Format

```php
<?php
return [
    'web_port' => 2053,      // Web panel HTTPS port
    'stream_port' => 2083,   // Streaming HTTPS port
    'rtmp_port' => 1935,     // RTMP port (standard)
];
```

## Migration from Older Versions

If you're upgrading from an older FOS-Streaming installation:

1. **Backup Your Data:**
   ```bash
   mysqldump -u root -p fos > fos_backup.sql
   tar -czf fos_www_backup.tar.gz /home/fos-streaming/fos/www/
   ```

2. **Note Current Configuration:**
   - Current ports in use
   - Database credentials
   - Stream settings

3. **Install Fresh:**
   - Run new installer with security features
   - Or manually add port configuration system

4. **Restore Data:**
   - Import database backup
   - Copy custom files and configurations

## Support and Resources

- **Installation Issues:** Check installer logs and error messages
- **SSL Problems:** Review nginx error logs and certbot logs
- **Port Conflicts:** Use `lsof` and `netstat` to identify conflicts
- **Cloudflare Setup:** Refer to Cloudflare documentation

## Security Disclosure

If you discover a security vulnerability, please email security@example.com (update with actual contact).

## License

Same as FOS-Streaming v70 main project.

## Changelog

### v69 Security Update (2025-11-21)
- Added random Cloudflare-compatible port selection for web and streaming
- Added random RTMP port selection from streaming port ranges
- Implemented HTTPS with self-signed certificates
- Created Let's Encrypt upgrade script
- Added dynamic port configuration system
- Updated installer with security features
- Created port management libraries (PortManager.php, PortHelper.php)
- Enhanced nginx configuration with SSL/TLS
- Added HTTP/2 support
- Improved firewall configuration with automatic rules for all ports
- Updated nginx to use dynamic RTMP port
- Created comprehensive security documentation
- All three ports (web, stream, RTMP) are now randomized and unique per installation
