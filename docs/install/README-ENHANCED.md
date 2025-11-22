# FOS-Streaming v70 Enhanced Installation Guide

## Overview

The enhanced Debian 12 installation script provides a complete, production-ready FOS-Streaming setup with modern development tools and Laravel components integration.

## Features

### 🆕 New in Enhanced Version

- ✅ **Runs as normal user** with sudo privileges (more secure)
- ✅ **NVM installation** for Node.js version management
- ✅ **Node.js 20 LTS** via NVM
- ✅ **Composer** for PHP dependency management
- ✅ **Automatic composer install** for Laravel components
- ✅ **NPM install** and **npm run build** for frontend assets
- ✅ **Production .env file** auto-generated
- ✅ **Database creation** via sudo commands
- ✅ **FOS user added to sudoers** with NOPASSWD
- ✅ **Enhanced security** with proper permissions

### 📦 Installed Components

- **PHP 8.4** with all extensions + Redis
- **Composer** (latest)
- **NVM** + **Node.js 20 LTS** + **NPM**
- **MariaDB 11.4**
- **Nginx 1.26+** with HTTP-FLV module
- **FFmpeg** (latest static build)
- **Laravel Components** (20+ packages)
- **SSL Certificates** (self-signed, upgradeable to Let's Encrypt)

## System Requirements

### Operating System
- **Debian 12 (Bookworm)** - Required
- Fresh installation recommended
- Minimum 2GB RAM
- Minimum 20GB disk space

### User Requirements
- **Normal user account** (NOT root)
- **Sudo privileges** required
- User must be in sudoers

## Installation

### Quick Install

```bash
# 1. Download the enhanced installation script
wget https://raw.githubusercontent.com/theraw/FOS-Streaming-v69/master/install/debian12-enhanced

# 2. Make it executable
chmod +x debian12-enhanced

# 3. Run as normal user (will request sudo password)
./debian12-enhanced
```

### Step-by-Step Installation

#### 1. Prepare Your System

```bash
# Update system
sudo apt update
sudo apt upgrade -y

# Ensure your user has sudo access
sudo -v

# If you need to add your user to sudo group:
# su -
# usermod -aG sudo yourusername
# exit
```

#### 2. Download Script

```bash
# Using wget
wget https://raw.githubusercontent.com/theraw/FOS-Streaming-v69/master/install/debian12-enhanced

# OR using curl
curl -O https://raw.githubusercontent.com/theraw/FOS-Streaming-v69/master/install/debian12-enhanced
```

#### 3. Make Executable

```bash
chmod +x debian12-enhanced
```

#### 4. Run Installation

```bash
./debian12-enhanced
```

**The script will:**
- Request your sudo password at the start
- Keep sudo alive throughout installation
- Install all dependencies
- Build Nginx from source
- Setup PHP, MariaDB, Node.js
- Install Composer dependencies
- Build frontend assets (if package.json exists)
- Configure services
- Generate SSL certificates
- Initialize database
- Configure security

#### 5. Installation Time

Total installation time: **15-30 minutes**
- System updates: 2-5 minutes
- PHP & MariaDB: 3-5 minutes
- Nginx compilation: 5-10 minutes
- Node.js & dependencies: 2-5 minutes
- Configuration: 2-5 minutes

## What Gets Installed

### Directory Structure

```
/home/fos-streaming/fos/
├── nginx/              # Nginx binaries and config
│   ├── conf/
│   │   ├── nginx.conf
│   │   └── certs/      # SSL certificates
│   ├── sbin/
│   └── logs/
├── www/                # Web panel application
│   ├── .env            # Production environment config
│   ├── vendor/         # Composer dependencies
│   ├── node_modules/   # NPM dependencies (if package.json exists)
│   ├── config/
│   │   └── ports.php   # Port configuration
│   ├── storage/
│   │   ├── framework/
│   │   └── logs/
│   └── ...
├── www1/               # Streaming application
├── php/
└── logs/               # Application logs
```

### System Users Created

1. **nginx** - PHP-FPM and web server user
   - Shell: /sbin/nologin
   - Purpose: Running web services

2. **fosstreaming** - FOS application user
   - Shell: /bin/bash
   - Purpose: Running nginx, managing application
   - **Sudoers**: Has NOPASSWD sudo access

### Ports Configured

The script automatically selects **random, available ports**:

- **Web Port**: Random Cloudflare SSL-compatible port (2053, 2083, 2087, 2096, 8443)
- **Stream Port**: Different Cloudflare SSL-compatible port
- **RTMP Port**: Random port from 1935-1999 or 8000-8999

Ports are saved to: `/home/fos-streaming/fos/www/config/ports.php`

### Environment File

Production `.env` file created at: `/home/fos-streaming/fos/www/.env`

Contains:
- Application settings
- Database credentials
- Port configuration
- Security settings
- FFmpeg paths
- Logging configuration

## Post-Installation

### 1. Access Web Panel

```bash
# Get your server IP
hostname -I | awk '{print $1}'

# Access panel
https://YOUR_IP:WEB_PORT
```

**Default credentials:**
- Username: `admin`
- Password: `admin`

⚠️ **IMPORTANT**: Change the default password immediately!

### 2. Configure Settings

1. Login to web panel
2. Navigate to **Settings**
3. Update **Web ip** to your public IP
4. Save settings

### 3. Service Management

```bash
# Check services
systemctl status fos-nginx
systemctl status php8.4-fpm
systemctl status mariadb

# Restart services
sudo systemctl restart fos-nginx
sudo systemctl restart php8.4-fpm
sudo systemctl restart mariadb

# View logs
sudo tail -f /home/fos-streaming/fos/logs/error.log
sudo journalctl -u fos-nginx -f
```

### 4. Database Access

```bash
# Get MySQL root password
sudo cat /root/MYSQL_ROOT_PASSWORD

# Access MySQL
mysql -u root -p
# (Enter password from file above)

# Or as fos user
mysql -u fos -p fos_streaming
# (Use same password)
```

## Laravel Components Usage

### Composer

```bash
# Navigate to project
cd /home/fos-streaming/fos/www

# Install new packages
composer require vendor/package

# Update dependencies
composer update

# Dump autoload
composer dump-autoload
```

### Node.js / NPM

```bash
# Load NVM (if not in your shell)
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

# Use Node.js 20
nvm use 20

# Install packages
npm install

# Build frontend
npm run build

# Development mode (if configured)
npm run dev
```

**To make NVM available in all shells**, add to `~/.bashrc`:

```bash
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
[ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"
```

## User & Permissions

### FOS User Sudoers Configuration

The `fosstreaming` user is automatically added to sudoers:

```bash
# Configuration file
/etc/sudoers.d/fosstreaming

# Content:
fosstreaming ALL=(ALL) NOPASSWD: ALL
```

This allows the fosstreaming user to run any sudo command without password.

### FFmpeg Sudoers Configuration

Both `nginx` and `fosstreaming` users can run FFmpeg:

```bash
# Configuration file
/etc/sudoers.d/fos-ffmpeg

# Content:
nginx ALL = (root) NOPASSWD: /usr/local/bin/ffmpeg
nginx ALL = (root) NOPASSWD: /usr/local/bin/ffprobe
fosstreaming ALL = (root) NOPASSWD: /usr/local/bin/ffmpeg
fosstreaming ALL = (root) NOPASSWD: /usr/local/bin/ffprobe
```

### Switching to FOS User

```bash
# Switch to fosstreaming user
sudo su - fosstreaming

# You're now logged in as fosstreaming
# Can run sudo commands without password
```

## SSL Certificates

### Self-Signed Certificates

The installation creates **self-signed SSL certificates** for immediate HTTPS use.

Location: `/home/fos-streaming/fos/nginx/conf/certs/`
- `fullchain.pem` - Certificate
- `privkey.pem` - Private key

### Upgrade to Let's Encrypt

For production use, upgrade to Let's Encrypt:

```bash
# 1. Point your domain to server IP

# 2. Run upgrade script
sudo bash /root/upgrade-to-letsencrypt.sh

# 3. Enter your domain when prompted
# 4. Enter your email for Let's Encrypt notifications
```

The upgrade script will:
- Backup existing certificates
- Install certbot
- Obtain Let's Encrypt certificate
- Configure auto-renewal
- Restart nginx

## Firewall Configuration

If UFW is installed, the script automatically configures firewall rules:

```bash
# Check UFW status
sudo ufw status

# Allow additional ports
sudo ufw allow 22/tcp comment 'SSH'
sudo ufw allow 80/tcp comment 'HTTP'
sudo ufw allow 443/tcp comment 'HTTPS'

# Enable firewall
sudo ufw enable
```

## Troubleshooting

### Installation Fails

**Check sudo access:**
```bash
sudo -v
```

**Check disk space:**
```bash
df -h
```

**Check logs:**
```bash
# Review the installation output for errors
# Most recent error will be at the end
```

### Services Won't Start

**Check PHP-FPM:**
```bash
sudo systemctl status php8.4-fpm
sudo journalctl -u php8.4-fpm -n 50
```

**Check Nginx:**
```bash
sudo systemctl status fos-nginx
sudo /home/fos-streaming/fos/nginx/sbin/nginx -t
```

**Check MariaDB:**
```bash
sudo systemctl status mariadb
sudo journalctl -u mariadb -n 50
```

### Can't Access Web Panel

**Check if services are running:**
```bash
sudo systemctl status fos-nginx
sudo systemctl status php8.4-fpm
```

**Check ports:**
```bash
# Get configured ports
cat /home/fos-streaming/fos/www/config/ports.php

# Check if nginx is listening
sudo netstat -tlnp | grep nginx
```

**Check firewall:**
```bash
sudo ufw status
# Make sure web port is allowed
```

**Check SSL certificate:**
```bash
ls -la /home/fos-streaming/fos/nginx/conf/certs/
# Should contain fullchain.pem and privkey.pem
```

### Composer Issues

**Permission denied:**
```bash
# Fix ownership
sudo chown -R nginx:nginx /home/fos-streaming/fos/www
```

**Out of memory:**
```bash
# Run with more memory
COMPOSER_MEMORY_LIMIT=-1 composer install
```

### NPM Issues

**NVM not found:**
```bash
# Load NVM
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

# Add to ~/.bashrc for persistence
```

**Permission errors:**
```bash
# NPM should be run as current user, not sudo
nvm use 20
npm install
```

## Advanced Configuration

### Custom PHP Configuration

```bash
# Edit php.ini
sudo nano /etc/php/8.4/fpm/php.ini

# Edit PHP-FPM pool
sudo nano /etc/php/8.4/fpm/pool.d/www.conf

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

### Custom Nginx Configuration

```bash
# Edit nginx config
sudo nano /home/fos-streaming/fos/nginx/conf/nginx.conf

# Test configuration
sudo /home/fos-streaming/fos/nginx/sbin/nginx -t

# Reload nginx
sudo systemctl reload fos-nginx
```

### Database Optimization

```bash
# Edit MariaDB config
sudo nano /etc/mysql/mariadb.conf.d/99-fos.cnf

# Restart MariaDB
sudo systemctl restart mariadb
```

## Security Best Practices

### 1. Change Default Credentials

```bash
# Web panel: Change admin password via web interface
# Database: Change fos user password
sudo mysql -u root -p
> ALTER USER 'fos'@'localhost' IDENTIFIED BY 'new_strong_password';
> FLUSH PRIVILEGES;
```

### 2. Limit Sudo Access (Optional)

If you want to restrict fosstreaming user's sudo access:

```bash
# Edit sudoers file
sudo visudo -f /etc/sudoers.d/fosstreaming

# Change from:
# fosstreaming ALL=(ALL) NOPASSWD: ALL

# To specific commands only:
# fosstreaming ALL=(ALL) NOPASSWD: /bin/systemctl restart fos-nginx
# fosstreaming ALL=(ALL) NOPASSWD: /bin/systemctl reload fos-nginx
```

### 3. Enable Firewall

```bash
# Install UFW
sudo apt install ufw

# Allow SSH first!
sudo ufw allow 22/tcp

# Allow your web ports
sudo ufw allow WEB_PORT/tcp
sudo ufw allow STREAM_PORT/tcp
sudo ufw allow RTMP_PORT/tcp

# Enable firewall
sudo ufw enable
```

### 4. Regular Updates

```bash
# Update system
sudo apt update
sudo apt upgrade -y

# Update composer dependencies
cd /home/fos-streaming/fos/www
composer update

# Update npm packages
npm update
```

## Differences from Original Script

| Feature | Original debian12 | Enhanced debian12-enhanced |
|---------|------------------|---------------------------|
| **Run as** | Root (sudo) | Normal user with sudo |
| **Composer** | ❌ Not installed | ✅ Installed & runs composer install |
| **NVM** | ❌ Not available | ✅ Installed with Node.js 20 LTS |
| **NPM** | ❌ Not available | ✅ Installed via NVM |
| **Frontend Build** | ❌ Manual | ✅ Automatic npm install & build |
| **.env File** | ❌ Manual | ✅ Auto-generated for production |
| **User Sudoers** | ❌ Limited | ✅ fosstreaming has NOPASSWD sudo |
| **Database** | ✅ Created as root | ✅ Created via sudo |
| **Laravel Components** | ❌ Not installed | ✅ Installed via composer |
| **Security** | ✅ Basic | ✅ Enhanced with proper permissions |

## Important Files & Locations

```
/home/fos-streaming/fos/www/.env                    # Production environment
/home/fos-streaming/fos/www/config/ports.php        # Port configuration
/home/fos-streaming/fos/nginx/conf/nginx.conf       # Nginx config
/home/fos-streaming/fos/nginx/conf/certs/           # SSL certificates
/home/fos-streaming/fos/logs/                       # Application logs
/etc/php/8.4/fpm/php.ini                            # PHP configuration
/etc/php/8.4/fpm/pool.d/www.conf                    # PHP-FPM pool
/etc/mysql/mariadb.conf.d/99-fos.cnf                # MariaDB optimization
/etc/systemd/system/fos-nginx.service               # Nginx service
/etc/sudoers.d/fosstreaming                         # User sudo config
/etc/sudoers.d/fos-ffmpeg                           # FFmpeg sudo config
/root/MYSQL_ROOT_PASSWORD                           # MySQL password
/root/upgrade-to-letsencrypt.sh                     # SSL upgrade script
~/.nvm/                                             # NVM installation
```

## Support & Documentation

- **Main Documentation**: [README.md](../README.md)
- **Laravel Guide**: [LARAVEL_SAIL_GUIDE.md](../LARAVEL_SAIL_GUIDE.md)
- **Laravel Components**: [LARAVEL_COMPONENTS_USAGE.md](../LARAVEL_COMPONENTS_USAGE.md)
- **Update Summary**: [UPDATE_SUMMARY.md](../UPDATE_SUMMARY.md)

## FAQ

**Q: Can I run this on Debian 11?**
A: No, this script is specifically for Debian 12. Debian 11 is no longer supported.

**Q: Do I need root access?**
A: You need a normal user account with sudo privileges. Don't run as root.

**Q: Will this work on Ubuntu?**
A: Not officially supported. Debian 12 only.

**Q: Can I change the ports later?**
A: Yes, edit `/home/fos-streaming/fos/www/config/ports.php` and nginx.conf, then restart services.

**Q: How do I add more Node.js versions?**
A: Use NVM: `nvm install 18` or `nvm install 21`

**Q: Can I use Docker instead?**
A: Yes! See [LARAVEL_SAIL_GUIDE.md](../LARAVEL_SAIL_GUIDE.md) for Docker development.

**Q: How do I backup my installation?**
A: Backup `/home/fos-streaming/fos/` directory and database:
```bash
sudo tar -czf fos-backup.tar.gz /home/fos-streaming/fos/
sudo mysqldump -u root -p fos_streaming > fos-db-backup.sql
```

---

**Installation script version:** Enhanced v1.0
**Last updated:** November 22, 2025
**Tested on:** Debian 12 (Bookworm)
