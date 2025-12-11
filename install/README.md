# FOS-Streaming v70 Installation Guide

This guide walks you through the modular installation process for FOS-Streaming v70.
The installation is split into multiple steps to provide better control and reliability.

---

## Supported Operating Systems

| OS | Version | Status |
|----|---------|--------|
| **Debian** | 12 (Bookworm) | Fully Supported |
| **Ubuntu** | 24.04 LTS (Noble) | Fully Supported |

> **Note**: Any minor version of Debian 12.x or Ubuntu 24.04.x is supported.
> Older versions (Debian 10/11, Ubuntu 20.04/22.04) are **not supported**.

---

## Software Versions

All software is installed at their **latest stable release**:

| Software | Version | Notes |
|----------|---------|-------|
| PHP | 8.4.x | Latest stable via sury.org/ondrej PPA |
| MariaDB | 11.4.x | Latest LTS stable |
| Node.js | 20.x LTS | Via NVM |
| Nginx | 1.26.x | Custom build with HTTP-FLV module |
| FFmpeg | Latest | From OS repositories |
| Redis | Latest | From OS repositories |
| Composer | 2.x | Latest stable |

---

## System Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| **RAM** | 2 GB | 4 GB+ |
| **Disk Space** | 20 GB | 50 GB+ |
| **CPU** | 2 cores | 4 cores+ |
| **Network** | Internet connection | Static IP for production |

---

## Installation Overview

```
┌─────────────────────────────────────────────────────────────────┐
│  STEP 1: Bootstrap & User Setup (as root)                       │
│  Install prerequisites, create system user with sudo access     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 2: Clone Repository (as user)                             │
│  Clone FOS-Streaming into user's home directory                 │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 3: Install Dependencies (02-install-deps.py - as user)   │
│  Installs: PHP, Node.js, Composer, Redis, FFmpeg                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 4: Database Setup (manual)                                │
│  Create: MariaDB database, user, permissions                    │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 5: Platform Deployment (manual)                           │
│  Run: composer install, npm install, npm run build, migrate     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 6: Service Configuration (03-setup-services.py)          │
│  Install: PHP-FPM pools, Nginx vhosts, directories, systemd     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 7: Nginx Setup (build/configure)                          │
│  Build or configure Nginx with HTTP-FLV module                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP 8: Start All Services                                     │
│  Start: PHP-FPM, Nginx, MariaDB, Redis, PM2 Workers             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Step 1: Bootstrap & User Setup

This step installs prerequisites and creates a dedicated system user.
**Run all commands as root** on a fresh Debian 12 or Ubuntu 24.04 installation.

### 1.1 Install Prerequisites

```bash
# SSH into your server as root
ssh root@your-server-ip

# Update package lists
apt-get update

# Install essential prerequisites
apt-get install -y python3 git curl wget sudo ca-certificates gnupg

# Verify installations
python3 --version
git --version
```

### 1.2 Download User Setup Script

```bash
# Download only the user setup script (not the entire repo)
curl -fsSL https://raw.githubusercontent.com/CristianCasapu/FOS-Streaming-Reborn/develop/install/01-setup-user.py -o /tmp/01-setup-user.py

# Make it executable
chmod +x /tmp/01-setup-user.py
```

### 1.3 Create System User

```bash
# Run the user setup script
python3 /tmp/01-setup-user.py
```

### What This Script Does

1. Prompts for username (default: `fosadmin`)
2. Creates the user with a home directory
3. Generates a secure random password
4. Adds user to the `sudo` group
5. Configures passwordless sudo in `/etc/sudoers.d/`
6. Displays credentials (**save these!**)

### Options

```bash
# Create user with custom username
python3 /tmp/01-setup-user.py myusername

# Use an existing user (will configure sudo access)
python3 /tmp/01-setup-user.py existinguser
```

### 1.4 Switch to New User

```bash
# Switch to the new user
su - fosadmin  # or your chosen username

# Verify sudo access works
sudo whoami  # Should output: root
```

---

## Step 2: Clone Repository

Now that you have a user with sudo access, clone the repository into the user's home directory.

### Run as the FOS User

```bash
# Ensure you're logged in as the FOS user (not root)
whoami  # Should show 'fosadmin' or your chosen username

# Navigate to home directory
cd ~

# Clone the repository
git clone https://github.com/CristianCasapu/FOS-Streaming-Reborn.git FOS-Streaming

# Enter the project directory
cd ~/FOS-Streaming

# Verify the clone
ls -la
```

You should see the project files including `install/`, `config.php`, `composer.json`, etc.

---

## Step 3: Install Dependencies

This step installs all required packages and dependencies.

### Run as the FOS User

```bash
# Ensure you're the FOS user
whoami  # Should show 'fosadmin' or your chosen username

# Navigate to project
cd ~/FOS-Streaming

# Run the dependencies installer
python3 install/02-install-deps.py
```

### What Gets Installed

| Category | Packages |
|----------|----------|
| **Build Tools** | gcc, make, git, curl, wget, openssl |
| **PHP 8.4** | CLI, FPM, mysql, curl, mbstring, xml, gd, zip, redis, etc. |
| **Composer** | Latest stable (2.x) |
| **Node.js** | 20 LTS via NVM |
| **PM2** | Process manager for Node.js workers |
| **Redis** | In-memory cache and session store |
| **FFmpeg** | Media processing for streams |

### Options

```bash
# Skip specific components
python3 install/02-install-deps.py --skip-update   # Skip apt update/upgrade
python3 install/02-install-deps.py --skip-node     # Skip Node.js/NVM
python3 install/02-install-deps.py --skip-php      # Skip PHP installation
python3 install/02-install-deps.py --skip-redis    # Skip Redis
python3 install/02-install-deps.py --skip-ffmpeg   # Skip FFmpeg

# Combine options
python3 install/02-install-deps.py --skip-update --skip-redis
```

### Log File

Installation logs are saved to: `install/install-deps.log`

Review this file if you encounter any issues.

---

## Step 4: Database Setup

MariaDB server installation and configuration is done manually for security reasons.

### 4.1 Install MariaDB Server

```bash
# Add MariaDB repository for latest stable version
curl -sS https://downloads.mariadb.com/MariaDB/mariadb_repo_setup | sudo bash

# Update and install
sudo apt-get update
sudo apt-get install -y mariadb-server mariadb-client

# Start and enable service
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Verify version (should be 11.4.x or newer)
mariadb --version
```

### 4.2 Secure MariaDB Installation

```bash
sudo mysql_secure_installation
```

Answer the prompts:
- **Switch to unix_socket authentication?** → Yes (recommended)
- **Change the root password?** → No (unix_socket is sufficient)
- **Remove anonymous users?** → Yes
- **Disallow root login remotely?** → Yes
- **Remove test database?** → Yes
- **Reload privilege tables?** → Yes

### 4.3 Create Database and User

```bash
# Connect as root (uses unix_socket, no password needed)
sudo mariadb
```

Run these SQL commands:

```sql
-- Create database with UTF8MB4 support (required for emojis and special characters)
CREATE DATABASE fos_streaming
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Create application user
-- IMPORTANT: Replace 'YourSecurePassword123!' with a strong password
CREATE USER 'fos'@'localhost' IDENTIFIED BY 'YourSecurePassword123!';

-- Grant privileges
GRANT ALL PRIVILEGES ON fos_streaming.* TO 'fos'@'localhost';
FLUSH PRIVILEGES;

-- Verify creation
SHOW DATABASES;
SELECT User, Host FROM mysql.user WHERE User = 'fos';

-- Exit
EXIT;
```

### 4.4 Test Connection

```bash
# Test the application user connection
mariadb -u fos -p'YourSecurePassword123!' fos_streaming -e "SELECT 'Connection successful!' AS status;"
```

### 4.5 Save Password Securely

```bash
# Store password for reference (root-only readable)
sudo bash -c 'echo "YourSecurePassword123!" > /root/MARIADB_FOS_PASSWORD'
sudo chmod 600 /root/MARIADB_FOS_PASSWORD

# Verify
sudo cat /root/MARIADB_FOS_PASSWORD
```

### 4.6 Install phpMyAdmin (Optional)

phpMyAdmin provides a web-based interface for database management.

```bash
# Install phpMyAdmin
sudo apt-get install -y phpmyadmin

# During installation:
# - Select "apache2" or "none" when asked (we'll use nginx)
# - Choose "Yes" to configure database with dbconfig-common
# - Enter a password for phpMyAdmin's database user
```

#### Configure phpMyAdmin with Nginx

```bash
# Create a symbolic link to phpMyAdmin
sudo ln -s /usr/share/phpmyadmin /var/www/html/phpmyadmin

# Create nginx configuration for phpMyAdmin
sudo tee /etc/nginx/sites-available/phpmyadmin > /dev/null <<'EOF'
server {
    listen 8080;
    listen [::]:8080;
    server_name _;

    root /usr/share/phpmyadmin;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Enable the site
sudo ln -sf /etc/nginx/sites-available/phpmyadmin /etc/nginx/sites-enabled/

# Test and reload nginx (if system nginx is installed)
sudo nginx -t && sudo systemctl reload nginx
```

#### Access phpMyAdmin

- **URL**: `http://your-server:8080/`
- **Username**: `fos` (or `root` for full access)
- **Password**: Your MariaDB password

> **Security Note**: For production servers, consider restricting phpMyAdmin access by IP or using SSH tunneling instead of exposing it publicly.

---

## Step 5: Deploy Platform

### 5.1 Configure Environment

```bash
cd ~/FOS-Streaming

# Copy example environment file
cp .env.example .env

# Edit configuration
nano .env
```

**Key settings to update in `.env`:**

```ini
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_DOMAIN=your-domain.com

# Database credentials (from Step 4)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fos_streaming
DB_USERNAME=fos
DB_PASSWORD=YourSecurePassword123!

# Redis password (check: sudo cat /root/REDIS_PASSWORD)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password_here
REDIS_PORT=6379

# Server Ports (see section below for details)
WEB_PORT=8000
STREAMING_PORT=8080
RTMP_PORT=1935
```

### 5.2 Configure Server Ports (Optional)

FOS-Streaming uses three main ports. You can customize these based on your needs.

#### Default Ports

| Port | Service | Description |
|------|---------|-------------|
| **8000** | Web Panel | Admin dashboard and API |
| **8080** | Streaming | HLS/HTTP-FLV stream delivery |
| **1935** | RTMP | Stream ingestion (source input) |

#### Changing the Web Port

To change the web panel port (default: 8000):

1. **Update `.env`**:
   ```ini
   WEB_PORT=7777
   APP_URL=https://your-domain.com:7777
   ```

2. **Update Nginx configuration** (after Step 6):
   ```bash
   # Edit the nginx config
   nano ~/FOS-Streaming/fospackv69/fos/nginx/conf/nginx.conf

   # Find and change the listen directive:
   # listen 8000; → listen 7777;
   ```

3. **Restart Nginx**:
   ```bash
   sudo systemctl restart fos-nginx
   ```

#### Changing the Streaming Port

To change the streaming port (default: 8080):

1. **Update `.env`**:
   ```ini
   STREAMING_PORT=9000
   ```

2. **Update Nginx configuration**:
   ```bash
   # Edit the nginx config and change the streaming server block
   # listen 8080; → listen 9000;
   ```

3. **Update firewall rules** (if applicable):
   ```bash
   sudo ufw allow 9000/tcp
   ```

#### Changing the RTMP Port

To change the RTMP ingestion port (default: 1935):

1. **Update `.env`**:
   ```ini
   RTMP_PORT=1936
   ```

2. **Update Nginx RTMP configuration**:
   ```bash
   # In the rtmp block of nginx.conf:
   # listen 1935; → listen 1936;
   ```

#### Common Port Configurations

| Use Case | Web | Streaming | RTMP |
|----------|-----|-----------|------|
| **Default** | 8000 | 8080 | 1935 |
| **Behind Cloudflare** | 8443 | 8080 | 1935 |
| **Standard HTTP/HTTPS** | 80/443 | 8080 | 1935 |
| **Avoid conflicts** | 7777 | 9000 | 1936 |

> **Note**: If using ports below 1024 (like 80 or 443), Nginx must run as root or use `setcap`:
> ```bash
> sudo setcap 'cap_net_bind_service=+ep' ~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx_fos
> ```

### 5.3 Install PHP Dependencies

```bash
cd ~/FOS-Streaming

# Install Composer packages (production mode)
composer install --no-dev --optimize-autoloader

# Generate optimized autoloader
composer dump-autoload --optimize
```

### 5.4 Install Frontend Dependencies

```bash
# Load NVM (required after fresh install or new terminal)
source ~/.nvm/nvm.sh

# Verify Node.js
node -v  # Should show v20.x.x

# Install npm packages
npm install

# Build frontend for production
npm run build
```

### 5.5 Run Database Migrations

```bash
# Run migrations to create tables
php artisan migrate

# Seed database with initial data (packages, default admin, etc.)
php artisan db:seed
```

### 5.6 Set Permissions

```bash
# Set correct ownership
sudo chown -R $(whoami):$(whoami) ~/FOS-Streaming

# Set directory permissions
find ~/FOS-Streaming -type d -exec chmod 755 {} \;
find ~/FOS-Streaming -type f -exec chmod 644 {} \;

# Make scripts executable
chmod +x install/*.py
chmod +x install/*.sh 2>/dev/null || true

# Storage directories need write access
chmod -R 775 storage cache logs
mkdir -p storage/framework/{cache,sessions,views}
chmod -R 775 storage/framework
```

---

## Step 6: Service Configuration

This step installs optimized PHP-FPM pools and Nginx configurations using the automated setup script.

### 6.1 Run the Service Configuration Script

```bash
cd ~/FOS-Streaming

# Run the service configuration installer
python3 install/03-setup-services.py
```

#### What This Script Does

1. **Installs PHP-FPM Pools**:
   - `fos-admin` pool - Optimized for admin web UI
   - `fos-streaming` pool - Optimized for stream authentication

2. **Installs Nginx Configurations**:
   - Updates main `nginx.conf` with correct paths
   - Configures admin vhost (port 8000)
   - Configures streaming vhost (port 8080)
   - Configures RTMP server (port 1935)

3. **Creates Required Directories**:
   - `~/FOS-Streaming/logs/`
   - `~/FOS-Streaming/hl/` (HLS output)
   - `~/FOS-Streaming/storage/` subdirectories
   - `/run/php/` for PHP-FPM sockets

4. **Creates Systemd Service**:
   - `fos-nginx.service` for managing the FOS nginx

#### Script Options

```bash
# Custom username (default: current user)
python3 install/03-setup-services.py --user myuser

# Custom FOS directory
python3 install/03-setup-services.py --fos-dir /opt/FOS-Streaming

# Configure for system nginx instead of FOS nginx
python3 install/03-setup-services.py --system-nginx
```

### 6.2 Configuration Files Overview

The script uses pre-configured templates from `install/config/`:

#### PHP-FPM Pools

| File | Socket | Purpose |
|------|--------|---------|
| `install/config/php-fpm/fos-admin.conf` | `/run/php/php8.4-fpm-admin.sock` | Admin dashboard, API, forms |
| `install/config/php-fpm/fos-streaming.conf` | `/run/php/php8.4-fpm-streaming.sock` | Stream authentication |

**Admin Pool Features** (`fos-admin.conf`):
- Process manager: `dynamic` (scales with demand)
- Max children: 50
- Memory limit: 256MB
- Longer timeouts (300s) for complex operations
- Session handling with security settings
- OPcache enabled for performance

**Streaming Pool Features** (`fos-streaming.conf`):
- Process manager: `static` (consistent performance)
- Max children: 100 (high concurrency)
- Memory limit: 64MB (lightweight auth only)
- Short timeouts (30s) for quick responses
- Minimal logging for performance
- No session handling (stateless)

#### Nginx Configurations

| File | Port | Purpose |
|------|------|---------|
| `install/config/nginx/nginx.conf` | - | Main nginx config |
| `install/config/nginx/fos-admin.conf` | 8000 | Admin web UI |
| `install/config/nginx/fos-streaming.conf` | 8080 | HLS/HTTP-FLV streaming |
| `install/config/nginx/fos-rtmp.conf` | 1935 | RTMP stream ingestion |

**Admin VHost Features** (`fos-admin.conf`):
- Security headers (XSS, CSRF, clickjacking protection)
- Rate limiting for API and login endpoints
- Static asset caching (1 year for versioned assets)
- PHP-FPM via admin pool socket
- Gzip compression

**Streaming VHost Features** (`fos-streaming.conf`):
- CORS headers for player compatibility
- No caching for live streams
- HTTP-FLV endpoint at `/live`
- HLS endpoint at `/hls`
- RTMP statistics at `/stat` and `/stat.xml`
- Stream control at `/control` (localhost only)
- PHP-FPM via streaming pool socket

**RTMP Configuration** (`fos-rtmp.conf`):
- Stream ingestion on port 1935
- HLS output with 3s fragments
- Multiple applications: `live`, `hls`, `encoder`, `restream`
- Authentication callback placeholders

### 6.3 Verify Configuration

After running the script:

```bash
# Check PHP-FPM pools are installed
ls -la /etc/php/8.4/fpm/pool.d/fos-*.conf

# Verify PHP-FPM is running with both pools
sudo systemctl restart php8.4-fpm
sudo systemctl status php8.4-fpm

# Check sockets exist (after PHP-FPM restart)
ls -la /run/php/php8.4-fpm-*.sock
```

Expected output:
```
/run/php/php8.4-fpm-admin.sock
/run/php/php8.4-fpm-streaming.sock
```

### 6.4 Manual Configuration (Alternative)

If you prefer to configure manually instead of using the script:

```bash
# Copy PHP-FPM pools
sudo cp install/config/php-fpm/fos-admin.conf /etc/php/8.4/fpm/pool.d/
sudo cp install/config/php-fpm/fos-streaming.conf /etc/php/8.4/fpm/pool.d/

# Disable default pool (optional)
sudo mv /etc/php/8.4/fpm/pool.d/www.conf /etc/php/8.4/fpm/pool.d/www.conf.disabled

# Copy nginx configs (for FOS nginx)
cp install/config/nginx/nginx.conf fospackv69/fos/nginx/conf/

# Edit configs to update paths (replace 'fosadmin' with your username)
sed -i "s/fosadmin/$(whoami)/g" /etc/php/8.4/fpm/pool.d/fos-*.conf
sed -i "s|/home/fosadmin/FOS-Streaming|$HOME/FOS-Streaming|g" /etc/php/8.4/fpm/pool.d/fos-*.conf

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

---

## Step 7: Nginx Configuration

FOS-Streaming can use either a **custom Nginx build** (with HTTP-FLV module for streaming) or the **system Nginx** (for basic web panel only). Both configurations use PHP-FPM for PHP processing.

### 7.1 PHP-FPM Verification

If you ran `03-setup-services.py` in Step 6, PHP-FPM pools are already configured. Verify they're working:

```bash
# Verify PHP-FPM is running
sudo systemctl status php8.4-fpm

# Check both sockets exist
ls -la /run/php/php8.4-fpm-admin.sock
ls -la /run/php/php8.4-fpm-streaming.sock
```

> **Note**: The optimized PHP-FPM pool configurations are in `install/config/php-fpm/`. See Step 6.2 for details about each pool's settings.

---

### 7.2 Option A: FOS Custom Nginx (Recommended)

The custom Nginx includes HTTP-FLV module for live streaming support.

#### Check for Pre-built Binary

```bash
ls -la ~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx_fos
```

If the binary exists, skip to "Verify Configuration" below.

#### Build Nginx from Source

```bash
cd ~/FOS-Streaming/fospackv69/nginx-builder

# Build nginx with HTTP-FLV module (recommended script)
sudo bash build-fos-nginx.sh

# Or with custom options
sudo bash build-fos-nginx.sh \
    --fos-dir ~/FOS-Streaming \
    --user $(whoami)

# Alternative: use the legacy build script
# sudo bash build-debian12.sh
```

**Build time**: 5-15 minutes depending on system specs.

> **Note**: See `docs/guides/NGINX_SETUP_GUIDE.md` for detailed build options and troubleshooting.

#### Configuration Files (Pre-configured)

If you ran `03-setup-services.py` in Step 6, the nginx configuration is already set up. The script installed:

| Config File | Description |
|-------------|-------------|
| `fospackv69/fos/nginx/conf/nginx.conf` | Main nginx config (includes vhosts) |
| `install/config/nginx/fos-admin.conf` | Admin vhost (port 8000) |
| `install/config/nginx/fos-streaming.conf` | Streaming vhost (port 8080) |
| `install/config/nginx/fos-rtmp.conf` | RTMP config (port 1935) |

The main `nginx.conf` includes the vhost files via:
```nginx
include /home/YOUR_USER/FOS-Streaming/install/config/nginx/fos-admin.conf;
include /home/YOUR_USER/FOS-Streaming/install/config/nginx/fos-streaming.conf;
include /home/YOUR_USER/FOS-Streaming/install/config/nginx/fos-rtmp.conf;
```

#### Verify Configuration

```bash
# Test nginx configuration
~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx_fos -t

# Expected output:
# nginx: the configuration file ... syntax is ok
# nginx: configuration file ... test is successful
```

#### Customizing Configuration (Optional)

To customize any configuration, edit the files directly:

```bash
# Edit main config
nano ~/FOS-Streaming/fospackv69/fos/nginx/conf/nginx.conf

# Edit admin vhost (port 8000)
nano ~/FOS-Streaming/install/config/nginx/fos-admin.conf

# Edit streaming vhost (port 8080)
nano ~/FOS-Streaming/install/config/nginx/fos-streaming.conf

# Edit RTMP config (port 1935)
nano ~/FOS-Streaming/install/config/nginx/fos-rtmp.conf
```

**Key settings you might want to change:**

| Setting | File | Purpose |
|---------|------|---------|
| `listen 8000` | fos-admin.conf | Admin web panel port |
| `listen 8080` | fos-streaming.conf | Streaming service port |
| `listen 1935` | fos-rtmp.conf | RTMP ingestion port |
| `hls_path` | fos-rtmp.conf | HLS output directory |
| `hls_fragment 3s` | fos-rtmp.conf | HLS segment duration |

#### Systemd Service (Pre-configured)

The `03-setup-services.py` script already created the systemd service file at `/etc/systemd/system/fos-nginx.service`.

If you need to create it manually:

```bash
FOS_USER=$(whoami)
FOS_HOME=$(eval echo ~$FOS_USER)

sudo tee /etc/systemd/system/fos-nginx.service > /dev/null <<EOF
[Unit]
Description=FOS-Streaming Nginx with HTTP-FLV
After=network.target php8.4-fpm.service
Wants=php8.4-fpm.service

[Service]
Type=forking
User=root
PIDFile=${FOS_HOME}/FOS-Streaming/fospackv69/fos/nginx/logs/nginx.pid
ExecStartPre=${FOS_HOME}/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx_fos -t
ExecStart=${FOS_HOME}/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx_fos
ExecReload=/bin/kill -s HUP \$MAINPID
ExecStop=/bin/kill -s QUIT \$MAINPID
Restart=on-failure
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable fos-nginx
```

#### FOS Nginx Management Commands

```bash
# Test configuration
~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx_fos -t

# Start/Stop/Restart
sudo systemctl start fos-nginx
sudo systemctl stop fos-nginx
sudo systemctl restart fos-nginx

# Reload configuration (no downtime)
sudo systemctl reload fos-nginx

# View status
sudo systemctl status fos-nginx

# View logs
tail -f ~/FOS-Streaming/fospackv69/fos/nginx/logs/error.log
tail -f ~/FOS-Streaming/fospackv69/fos/nginx/logs/access.log
```

---

### 7.3 Option B: System Nginx Configuration

Use system Nginx if you don't need HTTP-FLV streaming (web panel only).

> **Important**: System nginx does NOT support HTTP-FLV streaming or RTMP. Use this only for the admin web panel. For full streaming functionality, use the FOS custom nginx (Option A).

#### Install System Nginx

```bash
sudo apt-get install -y nginx
```

#### Use Pre-configured Admin VHost

If you ran `03-setup-services.py` with the `--system-nginx` flag, the admin vhost was installed to `/etc/nginx/sites-available/fos-admin`.

Otherwise, copy the admin vhost manually:

```bash
# Copy the admin vhost configuration
sudo cp ~/FOS-Streaming/install/config/nginx/fos-admin.conf /etc/nginx/sites-available/fos-streaming

# Update paths for your user
sudo sed -i "s/fosadmin/$(whoami)/g" /etc/nginx/sites-available/fos-streaming
sudo sed -i "s|/home/fosadmin/FOS-Streaming|$HOME/FOS-Streaming|g" /etc/nginx/sites-available/fos-streaming

# Update the PHP-FPM socket to use admin pool
sudo sed -i "s|php8.4-fpm-admin.sock|php8.4-fpm-admin.sock|g" /etc/nginx/sites-available/fos-streaming
```

#### Enable the Site

```bash
# Enable the FOS site
sudo ln -sf /etc/nginx/sites-available/fos-streaming /etc/nginx/sites-enabled/

# Remove default site (optional)
sudo rm -f /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

#### System Nginx Management Commands

```bash
# Test configuration
sudo nginx -t

# Start/Stop/Restart
sudo systemctl start nginx
sudo systemctl stop nginx
sudo systemctl restart nginx

# Reload (no downtime)
sudo systemctl reload nginx

# View status
sudo systemctl status nginx

# View logs
tail -f /var/log/nginx/fos-error.log
tail -f /var/log/nginx/fos-access.log
```

---

### 7.4 Advanced Nginx Configuration

#### SSL/TLS with Let's Encrypt

```bash
# Install Certbot
sudo apt-get install -y certbot

# For system Nginx
sudo apt-get install -y python3-certbot-nginx

# Obtain certificate (system Nginx)
sudo certbot --nginx -d your-domain.com

# For FOS Nginx (standalone mode)
sudo certbot certonly --standalone -d your-domain.com

# Auto-renewal test
sudo certbot renew --dry-run
```

#### Rate Limiting

Add to your `http` block:

```nginx
# Rate limiting zones
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=login:10m rate=1r/s;
limit_conn_zone $binary_remote_addr zone=addr:10m;

# Apply to locations
location /api {
    limit_req zone=api burst=20 nodelay;
    # ... rest of config
}

location /admin/login {
    limit_req zone=login burst=5 nodelay;
    # ... rest of config
}
```

#### WebSocket Support

For real-time features, add WebSocket proxy:

```nginx
location /ws {
    proxy_pass http://127.0.0.1:6001;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 86400;
}
```

#### Caching Configuration

```nginx
# FastCGI cache
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=FCGI:100m inactive=60m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";

# In server block
location ~ \.php$ {
    fastcgi_cache FCGI;
    fastcgi_cache_valid 200 60m;
    fastcgi_cache_bypass $http_cache_control;
    add_header X-Cache-Status $upstream_cache_status;
    # ... rest of PHP config
}
```

#### IP Whitelisting for Admin

```nginx
location /admin {
    # Allow specific IPs only
    allow 192.168.1.0/24;
    allow 10.0.0.0/8;
    deny all;

    try_files $uri $uri/ /index.php?$query_string;
}
```

#### Load Balancing (Multiple PHP-FPM)

```nginx
upstream php-fpm-pool {
    least_conn;
    server unix:/run/php/php8.4-fpm.sock weight=3;
    server unix:/run/php/php8.4-fpm-2.sock weight=2;
    keepalive 32;
}
```

---

### 7.5 Running Both Nginx Services

If you need both system Nginx (for phpMyAdmin/other sites) and FOS Nginx (for streaming):

```bash
# System Nginx: ports 80, 443, 8080 (phpMyAdmin)
# FOS Nginx: ports 8000 (web), 1935 (RTMP)

# Ensure no port conflicts in configurations

# Start both services
sudo systemctl start nginx        # System Nginx
sudo systemctl start fos-nginx    # FOS Nginx

# Verify both are running
sudo netstat -tlnp | grep nginx
```

---

## Step 8: Start All Services

### Start Services

```bash
# PHP-FPM
sudo systemctl start php8.4-fpm
sudo systemctl enable php8.4-fpm

# MariaDB (should already be running)
sudo systemctl enable mariadb

# Redis
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Nginx (custom)
sudo systemctl start fos-nginx
```

### Start PM2 Workers

```bash
cd ~/FOS-Streaming

# Load NVM
source ~/.nvm/nvm.sh

# Start all background workers
npm run pm2:start

# Save PM2 configuration for auto-restart
pm2 save

# Setup PM2 to start on system boot
pm2 startup systemd -u $(whoami) --hp $(eval echo ~$(whoami))
# Run the command it outputs
```

---

## Verification

### Check All Services

```bash
# PHP-FPM
sudo systemctl status php8.4-fpm

# MariaDB
sudo systemctl status mariadb

# Redis
sudo systemctl status redis-server

# Nginx
sudo systemctl status fos-nginx

# PM2 workers
source ~/.nvm/nvm.sh && pm2 status
```

### Test Web Access

Open in browser:

| URL | Description |
|-----|-------------|
| `http://your-server:8000/admin` | Admin Panel |
| `http://your-server:8080/` | phpMyAdmin (if installed, Step 4.6) |
| `http://your-server:STREAMING_PORT/` | Streaming endpoint |

**Default Admin Login:**
- Username: `admin`
- Password: `admin`

> **IMPORTANT**: Change the default admin password immediately after first login!

**phpMyAdmin Login** (if installed):
- Username: `fos`
- Password: Your MariaDB password (from Step 4)

---

## Troubleshooting

### Python Script Won't Run

```bash
# Ensure Python 3 is installed
python3 --version

# If not found
sudo apt-get install -y python3
```

### PHP Issues

```bash
# Check PHP version
php -v

# List installed modules
php -m

# Check PHP-FPM status
sudo systemctl status php8.4-fpm

# View logs
sudo journalctl -u php8.4-fpm -f
```

### Node.js Issues

```bash
# Reload NVM in current shell
source ~/.nvm/nvm.sh

# Check version
node -v && npm -v

# Reinstall if needed
nvm install 20
nvm use 20
nvm alias default 20
```

### Database Connection Issues

```bash
# Check MariaDB status
sudo systemctl status mariadb

# Test connection
mariadb -u fos -p fos_streaming -e "SELECT VERSION();"

# View logs
sudo journalctl -u mariadb -f
```

### Nginx Issues

```bash
# Test configuration
~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx_fos -t

# Check status
sudo systemctl status fos-nginx

# View logs
tail -f ~/FOS-Streaming/fospackv69/fos/nginx/logs/error.log
```

### Permission Issues

```bash
# Reset ownership
sudo chown -R $(whoami):$(whoami) ~/FOS-Streaming

# Fix storage permissions
chmod -R 775 ~/FOS-Streaming/storage
chmod -R 775 ~/FOS-Streaming/cache
chmod -R 775 ~/FOS-Streaming/logs
```

---

## File Locations

| Item | Path |
|------|------|
| **Project Root** | `~/FOS-Streaming/` |
| Environment Config | `~/FOS-Streaming/.env` |
| Port Config | `~/FOS-Streaming/config/ports.php` |
| Install Logs | `~/FOS-Streaming/install/install-deps.log` |
| Application Logs | `~/FOS-Streaming/logs/` |
| Storage | `~/FOS-Streaming/storage/` |
| HLS Output | `~/FOS-Streaming/hl/` |
| **Installation Scripts** | |
| User Setup Script | `install/01-setup-user.py` |
| Dependencies Script | `install/02-install-deps.py` |
| Services Script | `install/03-setup-services.py` |
| **PHP-FPM Configs** | |
| Admin Pool Template | `install/config/php-fpm/fos-admin.conf` |
| Streaming Pool Template | `install/config/php-fpm/fos-streaming.conf` |
| Admin Pool (installed) | `/etc/php/8.4/fpm/pool.d/fos-admin.conf` |
| Streaming Pool (installed) | `/etc/php/8.4/fpm/pool.d/fos-streaming.conf` |
| Admin Socket | `/run/php/php8.4-fpm-admin.sock` |
| Streaming Socket | `/run/php/php8.4-fpm-streaming.sock` |
| **Nginx Configs** | |
| Main Config Template | `install/config/nginx/nginx.conf` |
| Admin VHost Template | `install/config/nginx/fos-admin.conf` |
| Streaming VHost Template | `install/config/nginx/fos-streaming.conf` |
| RTMP Config Template | `install/config/nginx/fos-rtmp.conf` |
| Nginx Binary | `~/FOS-Streaming/fospackv69/fos/nginx/sbin/nginx_fos` |
| Nginx Config (installed) | `~/FOS-Streaming/fospackv69/fos/nginx/conf/nginx.conf` |
| Nginx Logs | `~/FOS-Streaming/fospackv69/fos/nginx/logs/` |
| **System Services** | |
| FOS Nginx Service | `/etc/systemd/system/fos-nginx.service` |
| phpMyAdmin | `/usr/share/phpmyadmin/` |
| phpMyAdmin Nginx | `/etc/nginx/sites-available/phpmyadmin` |
| **Credentials** | |
| DB Password | `/root/MARIADB_FOS_PASSWORD` |
| Redis Password | `/root/REDIS_PASSWORD` |

---

## Quick Start Summary

```bash
# ============================================
# STEP 1: As root - Install prerequisites and create user
# ============================================
apt-get update && apt-get install -y python3 git curl wget sudo ca-certificates gnupg

# Download and run user setup script
curl -fsSL https://raw.githubusercontent.com/CristianCasapu/FOS-Streaming-Reborn/develop/install/01-setup-user.py -o /tmp/01-setup-user.py
python3 /tmp/01-setup-user.py
# Save the displayed password!

# Switch to new user
su - fosadmin

# ============================================
# STEP 2: As user - Clone repository
# ============================================
cd ~
git clone https://github.com/CristianCasapu/FOS-Streaming-Reborn.git FOS-Streaming
cd ~/FOS-Streaming

# ============================================
# STEP 3: As user - Install dependencies
# ============================================
python3 install/02-install-deps.py

# ============================================
# STEP 4: Database Setup (manual - see detailed instructions)
# ============================================
# Install MariaDB, create database 'fos_streaming', create user 'fos'

# ============================================
# STEP 5: Platform Deployment
# ============================================
cp .env.example .env
nano .env  # Update database credentials
composer install --no-dev --optimize-autoloader
source ~/.nvm/nvm.sh && npm install && npm run build
php artisan migrate
php artisan db:seed

# ============================================
# STEP 6: Service Configuration
# ============================================
python3 install/03-setup-services.py

# ============================================
# STEP 7: Nginx Setup (if not using pre-built binary)
# ============================================
# Build FOS nginx from source (optional):
# cd fospackv69/nginx-builder && sudo bash build-debian12.sh

# ============================================
# STEP 8: Start All Services
# ============================================
sudo systemctl restart php8.4-fpm
sudo systemctl start fos-nginx
sudo systemctl enable fos-nginx
source ~/.nvm/nvm.sh && npm run pm2:start && pm2 save
```

---

## Legacy Installer

The original `install.sh` script is still available but **not recommended**:

```bash
sudo bash install/install.sh
```

The modular approach provides better control, easier troubleshooting, and more reliable installations.

---

## Support

- **GitHub Issues**: https://github.com/CristianCasapu/FOS-Streaming-Reborn/issues
- **Documentation**: See `docs/` directory

---

*Version: 70.0.0 | Last Updated: 2025-12-11*

---

## Additional Documentation

- **Nginx Setup Guide**: See `docs/guides/NGINX_SETUP_GUIDE.md` for detailed nginx build and configuration
- **Laravel Components**: See `docs/guides/LARAVEL_COMPONENTS_USAGE.md`
- **PM2 Workers**: See `docs/guides/PM2_BACKGROUND_WORKERS_GUIDE.md`
