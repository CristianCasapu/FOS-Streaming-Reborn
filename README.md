# FOS-Streaming Reborn v70

A powerful streaming and restreaming platform with **modern Vue.js 3 admin interface**, advanced transcoding capabilities, user management, and enterprise-grade security features.

**GitHub**: [CristianCasapu/FOS-Streaming-Reborn](https://github.com/CristianCasapu/FOS-Streaming-Reborn)

---

## 💬 Support & Contact

<div align="center">

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Support%20Development-orange?style=for-the-badge&logo=buy-me-a-coffee&logoColor=white)](https://buymeacoffee.com/CristianCasapu)
[![Telegram](https://img.shields.io/badge/Telegram-Contact%20Me-blue?style=for-the-badge&logo=telegram&logoColor=white)](https://t.me/CristianCasapu)

**Like this project? [Buy me a coffee!](https://buymeacoffee.com/CristianCasapu) ☕**
**Need help? [Contact me on Telegram](https://t.me/CristianCasapu) 💬**

</div>

---

## ✨ What's New in v70

- 🎨 **Modern Vue.js 3 SPA Admin Panel** - Complete rewrite from legacy PHP/Blade to Vue.js with Composition API
- 🚀 **Laravel API Backend** - RESTful APIs with Eloquent ORM
- 🎯 **TailwindCSS UI** - Beautiful, responsive interface
- 🔒 **Advanced Security** - UFW firewall + fail2ban integration directly in admin panel
- 📊 **Real-time Monitoring** - Activity tracking with statistics dashboard
- 🛠️ **FFmpeg Testing** - Built-in tools to test FFmpeg/FFprobe
- 📱 **Mobile Responsive** - Works seamlessly on all devices
- 🔧 **PM2 Process Manager** - Manage background workers and system services from Settings
- 👥 **Subscriber Management** - Refactored user management with activity tracking
- ⚡ **Latest Packages** - Vite 7, Vue 3.5, Laravel 11 components

## Features

### Core Streaming Features
- **RTMP/HLS/HTTP-FLV Streaming**: Full streaming protocol support with authentication
- **M3U8 Playlist Generation**: Automated HLS playlist creation
- **Transcoding Profiles**: 20+ FFmpeg parameters with multiple predefined profiles
- **Stream Management**: Complete CRUD operations (create, start, stop, edit, delete)
- **Mass Operations**: Bulk start/stop/delete streams
- **Auto-Restart**: Cron-based stream monitoring and restart
- **Import/Export**: M3U playlist import functionality

### Modern Admin Panel (Vue.js 3 SPA)
- 🎨 **Dashboard**: Real-time statistics with charts and activity feeds
- 📺 **Streams Manager**: Full stream lifecycle management
- 👥 **Subscriber Management**: Create, edit, delete subscribers with stream limits and activity tracking
- 📂 **Category Manager**: Organize streams into categories
- 🔧 **Transcode Profiles**: Manage FFmpeg transcode configurations
- 🛡️ **Security Suite**:
  - IP Blocks management
  - User-Agent blocking
  - **Advanced Security** (UFW firewall + fail2ban)
- 👨‍💼 **Admin Manager**: Multiple administrator accounts
- 📊 **Activity Monitor**: Track stream usage and subscriber activity
- ⚙️ **Settings Panel**: System configuration with live FFmpeg testing and PM2 process manager

### Security Features
- **Modern Authentication**: Argon2id password hashing (PHP 8.4)
- **CSRF Protection**: Token-based request validation on all forms
- **Rate Limiting**: Brute-force protection (5 attempts/15 min)
- **UFW Firewall Management**: Enable/disable firewall, manage rules via web UI
- **fail2ban Integration**: Monitor jails, ban/unban IPs directly from admin panel
- **Security Logging**: Comprehensive audit trail with event tracking
- **Input Validation**: Strict input sanitization on all endpoints
- **Security Headers**: X-Frame-Options, X-Content-Type-Options, CSP
- **Session Security**: Secure session management with httponly cookies

## System Requirements

### Debian 12 (Bookworm)
- **OS**: Debian 12 (Bookworm)
- **PHP**: 8.4.x
- **MariaDB**: 11.4.x
- **Nginx**: 1.26.x with HTTP-FLV module (includes RTMP + HTTP-FLV streaming)
- **FFmpeg**: Latest static build

> **⚠️ Important**: Only Debian 12 is supported. Debian 11, PHP 7.x, and Nginx 1.19.x are **no longer supported**.

## Installation

### Quick Install - Debian 12

```bash
# 1. Clone the repository
git clone https://github.com/CristianCasapu/FOS-Streaming-Reborn.git
cd FOS-Streaming-Reborn

# 2. Run the unified installation script
chmod +x install/debian12-installer
./install/debian12-installer

# 3. Wait for installation to complete (10-20 minutes)
```

The installer will automatically set up:
- Nginx 1.26.x with HTTP-FLV and RTMP modules
- PHP 8.4 with all required extensions
- MariaDB 11.4 with UTF8MB4 support
- Composer 2.x for Laravel components
- Node.js 20 LTS with NVM
- FFmpeg latest static build
- Vue.js 3 admin panel with production build

### What Gets Installed

- **Nginx 1.26.x** - Custom built with HTTP-FLV and RTMP modules from `fospackv69/nginx-builder`
- **PHP 8.2** - From Debian 12 repositories with all required extensions
- **MariaDB 10.11** - From Debian 12 repositories
- **FFmpeg** - From Debian repositories
- **Node.js 20 LTS** - For frontend build tools
- **Composer** - For PHP dependency management
- **All Application Files** - From `fospackv69` directory and current codebase

### Repository Structure

- **`develop` branch** - Active development (default)
- **`master` branch** - Stable releases only
- **`fospackv69/`** - Contains all required packages and modules (no external downloads needed)

### Post-Installation Steps

1. **Access Modern Admin Panel**
   ```
   Visit: http://your-server-ip:7777/admin#/login
   Default credentials: admin / admin
   ```

2. **First Login** ⚠️ **CRITICAL SECURITY STEP**
   - Navigate to **Admins** page
   - Click edit on your admin account
   - **Change the default password immediately!**
   - Consider enabling two-factor authentication if available

3. **Configure System Settings**
   - Navigate to **Settings** page (`http://your-server-ip:7777/admin#/settings`)
   - Update "Web IP" to your public IPv4 address
   - Test FFmpeg and FFprobe installations
   - Configure streaming port if needed
   - Save settings

4. **Setup UFW Firewall** (Optional but Recommended)
   - Navigate to **Security → Advanced Security**
   - Enable UFW firewall
   - Add rules for required ports:
     - Port 7777/tcp (Web panel)
     - Port 8000/tcp (Streaming)
     - Port 1935/tcp (RTMP)
     - Port 22/tcp (SSH)

5. **Configure fail2ban** (Optional)
   - In **Security → Advanced Security**
   - Monitor fail2ban jails status
   - Configure IP ban/unban as needed

6. **Verify Cron Job** (Auto-restart streams)
   ```bash
   crontab -e
   # Verify this line exists:
   */2 * * * * /usr/bin/php /home/fos-streaming/fos/www/cron.php
   ```

7. **Database Access** (if needed)
   ```bash
   cat /root/MYSQL_ROOT_PASSWORD
   mysql -u root -p
   ```


## Configuration

### Change Panel Port

1. Change port in web interface: Settings → Web Port
2. Edit nginx configuration:
   ```bash
   nano /home/fos-streaming/fos/nginx/conf/nginx.conf
   # Change: listen 7777; to your desired port
   ```
3. Restart nginx:
   ```bash
   killall nginx; killall nginx_fos
   /home/fos-streaming/fos/nginx/sbin/nginx
   ```

### Service Management

```bash
# Nginx
systemctl start fos-nginx
systemctl stop fos-nginx
systemctl restart fos-nginx
systemctl status fos-nginx

# PHP-FPM
systemctl start php8.4-fpm
systemctl stop php8.4-fpm
systemctl restart php8.4-fpm
systemctl status php8.4-fpm

# MariaDB
systemctl start mariadb
systemctl stop mariadb
systemctl restart mariadb
systemctl status mariadb
```

## Usage

### Adding Your First Stream

1. **Create User**
   - Navigate to Users → Add User
   - Set username, password, and stream limits

2. **Add Stream**
   - Navigate to Streams → Add Stream
   - Select transcode profile: **Default 1** (recommended)
   - Enter stream source URL
   - Save and start stream

3. **Access Stream**
   - Format: `http://your-ip:8000/live/{username}/{password}/{stream-id}`
   - Or use the web player: Streams → Play

### Transcoding Profiles

The most stable configuration is using **Default 1** transcode profile without proxy mode.

Proxy mode is available but depends on your use case and network configuration.

## Security Best Practices

1. **Change Default Credentials**
   ```bash
   # Change admin password immediately after installation
   ```

2. **Configure Firewall**
   ```bash
   # Using UFW
   ufw allow 7777/tcp   # Web panel
   ufw allow 8000/tcp   # Streaming port
   ufw allow 1935/tcp   # RTMP port
   ufw allow 22/tcp     # SSH
   ufw enable
   ```

3. **Setup SSL/TLS** (Recommended for production)
   ```bash
   # Install certbot
   apt-get install certbot

   # Get certificate
   certbot certonly --standalone -d your-domain.com

   # Update nginx configuration to use SSL
   ```

4. **Monitor Security Logs**
   ```bash
   tail -f /home/fos-streaming/fos/logs/security.log
   tail -f /home/fos-streaming/fos/logs/auth.log
   ```

5. **Regular Updates**
   ```bash
   apt-get update
   apt-get upgrade
   ```

## Troubleshooting

### Streams Not Starting

1. Check FFmpeg:
   ```bash
   /usr/local/bin/ffmpeg -version
   ```

2. Check logs:
   ```bash
   tail -f /home/fos-streaming/fos/logs/error.log
   tail -f /home/fos-streaming/fos/logs/php-fpm.log
   ```

3. Verify permissions:
   ```bash
   ls -la /home/fos-streaming/fos/www/hl/
   # Should be owned by nginx:nginx
   ```

### Web Panel Not Accessible

1. Check nginx status:
   ```bash
   systemctl status fos-nginx
   ```

2. Verify port is listening:
   ```bash
   netstat -tlnp | grep 7777
   ```

3. Check PHP-FPM:
   ```bash
   systemctl status php8.4-fpm
   ```

### Database Connection Errors

1. Check MariaDB status:
   ```bash
   systemctl status mariadb
   ```

2. Verify database credentials:
   ```bash
   cat /home/fos-streaming/fos/www/config.php
   ```

3. Test connection:
   ```bash
   mysql -u fos -p
   # Enter password from /root/MYSQL_ROOT_PASSWORD
   ```

### Permission Issues

```bash
# Fix ownership
chown -R nginx:nginx /home/fos-streaming/fos/www
chown -R nginx:nginx /home/fos-streaming/fos/www1
chown -R fosstreaming:fosstreaming /home/fos-streaming/fos/nginx

# Fix permissions
chmod 777 /home/fos-streaming/fos/www/hl
chmod 777 /home/fos-streaming/fos/www/cache
```

## File Locations

### Important Directories

- **Web Root**: `/home/fos-streaming/fos/www/`
- **Streaming Root**: `/home/fos-streaming/fos/www1/`
- **Nginx Config**: `/home/fos-streaming/fos/nginx/conf/nginx.conf`
- **PHP-FPM Config**: `/etc/php/8.4/fpm/pool.d/www.conf`
- **Logs**: `/home/fos-streaming/fos/logs/`
- **HLS Output**: `/home/fos-streaming/fos/www/hl/`

### Log Files

- **Nginx Error**: `/home/fos-streaming/fos/logs/error.log`
- **Nginx Access**: `/home/fos-streaming/fos/logs/access.log`
- **PHP-FPM**: `/home/fos-streaming/fos/logs/php-fpm.log`
- **Security**: `/home/fos-streaming/fos/logs/security.log`
- **Authentication**: `/home/fos-streaming/fos/logs/auth.log`

## Architecture

### Components

1. **Nginx with HTTP-FLV Module**
   - Handles HTTP/HTTPS requests
   - RTMP streaming ingress
   - HTTP-FLV streaming
   - HLS segment generation
   - FastCGI to PHP-FPM

2. **PHP 8.4 with FPM**
   - Web panel application
   - Stream management API
   - User authentication
   - Database operations

3. **MariaDB 11.4**
   - User data storage
   - Stream configuration
   - Settings and metadata

4. **FFmpeg**
   - Stream transcoding
   - Format conversion
   - Bitrate adaptation

### Data Flow

```
RTMP Source → Nginx RTMP → FFmpeg Transcode → HLS Output → Nginx HTTP → Client
                ↓
           PHP Management → MariaDB
```

## API Endpoints

### Streaming URLs

- **HLS Playlist**: `http://your-ip:8000/live/{user}/{pass}/{stream}/index.m3u8`
- **Direct Stream**: `http://your-ip:8000/live/{user}/{pass}/{stream}`

### Management Panel (Vue.js SPA)

- **Login**: `http://your-ip:7777/admin#/login`
- **Dashboard**: `http://your-ip:7777/admin#/dashboard`
- **Streams**: `http://your-ip:7777/admin#/streams`
- **Users**: `http://your-ip:7777/admin#/users`
- **Categories**: `http://your-ip:7777/admin#/categories`
- **Transcodes**: `http://your-ip:7777/admin#/transcodes`
- **Security**: `http://your-ip:7777/admin#/security/ipblocks` (with dropdown menu)
- **Activities**: `http://your-ip:7777/admin#/activities`
- **Admins**: `http://your-ip:7777/admin#/admins`
- **Settings**: `http://your-ip:7777/admin#/settings`


## Development

### Tech Stack

- **Backend**: PHP 8.4 (Laravel Components)
  - Eloquent ORM for database operations
  - Validation, Cache, Queue, Mail, Events
  - Authentication, Session, Encryption
  - HTTP Client (Guzzle), Redis, Logging (Monolog)
  - RESTful API endpoints
- **Frontend**: Vue.js 3 + Vite 5
  - Composition API with `<script setup>`
  - Pinia for state management
  - Vue Router (hash mode)
  - TailwindCSS for styling
  - Axios for HTTP requests
- **Streaming**: Nginx-HTTP-FLV, FFmpeg
- **Database**: MariaDB 11.4 (UTF8MB4)
- **Cache/Queue**: Redis (optional)
- **Development**: Laravel Sail (Docker), NVM, Node.js 20 LTS

### Docker Development (Laravel Sail)

For a complete Docker-based development environment, see [LARAVEL_SAIL_GUIDE.md](LARAVEL_SAIL_GUIDE.md).

Quick start:
```bash
# Copy environment file
cp .env.example .env

# Start containers
./vendor/bin/sail up -d

# Access application
# Web Panel: http://localhost:7777
# Streaming: http://localhost:8000
```

### Available Laravel Components

- **Cache**: File, Redis, Database drivers
- **Queue**: Sync, Database, Redis workers
- **Mail**: SMTP, Mailgun, SES, Mailpit (development)
- **Validation**: Form requests, custom rules
- **Events**: Broadcasting, listeners
- **Notifications**: Email, SMS, Slack
- **Logging**: Daily, Single, Syslog channels
- **HTTP Client**: Guzzle-based API calls
- **Encryption**: AES-256-CBC
- **Hashing**: Argon2id, bcrypt

### Project Structure

```
FOS-Streaming-v70/
├── Root (14 PHP files - streaming endpoints + bootstrap)
│   ├── index.php, server.php, artisan      # Entry points
│   ├── config.php, helpers.php, functions.php  # Bootstrap
│   ├── stream.php, playlist.php, retrieve.php  # Streaming
│   ├── getfile.php, play.php, cron.php        # Utilities
│   └── not_encrypted_stream.php, api.php, clientsgen.php
│
├── public/admin/                   # Modern Vue.js SPA
│   ├── index.html                  # Vue app entry point
│   ├── api/                        # 13 Laravel-style API endpoints
│   │   ├── auth.php                # Authentication
│   │   ├── dashboard.php           # Stats & charts
│   │   ├── streams.php             # Stream CRUD
│   │   ├── users.php               # User management
│   │   ├── categories.php          # Category management
│   │   ├── transcodes.php          # Transcode profiles
│   │   ├── ipblocks.php            # IP blocking
│   │   ├── useragents.php          # User-agent blocking
│   │   ├── security.php            # UFW/fail2ban (NEW!)
│   │   ├── admins.php              # Admin accounts
│   │   ├── activities.php          # Activity logs
│   │   ├── settings.php            # System config
│   │   └── middleware.php          # API middleware
│   └── build/                      # Vite production builds
│
├── resources/js/                   # Vue.js 3 Frontend
│   ├── app.js                      # Main entry point
│   ├── views/                      # 16 Vue components
│   │   ├── Login.vue
│   │   ├── DashboardEnhanced.vue
│   │   ├── Streams/StreamsList.vue
│   │   ├── Users/UsersList.vue
│   │   ├── Categories/CategoriesList.vue
│   │   ├── Transcodes/TranscodesList.vue
│   │   ├── Security/
│   │   │   ├── IPBlocks.vue
│   │   │   ├── UserAgentBlocks.vue
│   │   │   └── AdvancedSecurity.vue  # NEW!
│   │   ├── Admins/AdminsList.vue
│   │   ├── Activities/ActivitiesList.vue
│   │   └── Settings/Settings.vue
│   ├── components/
│   │   ├── AppLayout.vue           # Main layout with nav
│   │   └── subscriber/             # Subscriber components
│   ├── stores/
│   │   ├── auth.js                 # Pinia auth store
│   │   └── dashboard.js            # Dashboard state
│   ├── services/
│   │   └── api.js                  # Axios API service
│   └── router/
│       └── index.js                # Vue Router config
│
├── models/                         # Eloquent ORM Models
│   ├── Stream.php, User.php
│   ├── Category.php, Transcode.php
│   ├── IPBlock.php, UserAgentBlock.php
│   ├── Admin.php, Activity.php
│   └── BannedIP.php, SecurityEvent.php
│
├── views/                          # Blade Templates (legacy)
│   ├── main.blade.php
│   ├── clientsgen.blade.php
│   ├── play.blade.php
│   └── stream_importer.blade.php
│
├── scripts/                        # Utilities (NEW!)
│   ├── install_database_tables.php
│   ├── migrate_passwords.php
│   └── stream_importer.php
│
├── docs/                           # Documentation
│   ├── guides/                     # 17+ guide documents
│   ├── database/                   # Database docs
│   └── install/                    # Installation docs
│
├── install/                        # Installation Scripts
│   └── debian12-installer          # Unified installer
│
├── vendor/                         # Composer dependencies
├── node_modules/                   # Node.js dependencies
├── package.json, vite.config.js    # Frontend build config
├── composer.json, composer.lock    # PHP dependencies
├── .env.example, .env              # Environment config
├── .gitignore                      # Git ignore rules
└── README.md                       # This file
```

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Test thoroughly on Debian 12
4. Submit a pull request with detailed description

## Support

- **GitHub Issues**: https://github.com/theraw/FOS-Streaming-v70/issues
- **Documentation**: See [MIGRATION_PLAN.md](MIGRATION_PLAN.md) for detailed technical information

## License

All Rights Reserved - FOS-Streaming

## Sources & Credits

1. FOS-Streaming-v1
2. FFmpeg - https://ffmpeg.org
3. Nginx - https://nginx.org
4. nginx-http-flv-module - https://github.com/winshining/nginx-http-flv-module
5. nginx-geoip2-module - https://github.com/leev/ngx_http_geoip2_module
6. MariaDB - https://mariadb.org
7. PHP - https://www.php.net

## Migration to Vue.js (v70)

FOS-Streaming v70 represents a **complete modernization** of the admin interface from legacy PHP/Blade to Vue.js 3 SPA:

### What Changed
- ✅ **35 legacy PHP/Blade files** deleted
- ✅ **13 RESTful API endpoints** created with 60+ actions
- ✅ **16 Vue.js components** built with Composition API
- ✅ **13 routes** with authentication guards
- ✅ **Security dropdown** with 3 organized pages
- ✅ **Production build:** 196 kB (gzipped: 42.46 kB)
- ✅ **Zero legacy dependencies** - Pure Vue.js 3 + TailwindCSS

### Architecture Benefits
- **Separation of Concerns**: Clean API/frontend split
- **Modern Stack**: Vue.js 3, Vite 5, TailwindCSS, Pinia
- **Type Safety**: Better code organization with Composition API
- **Performance**: Lazy loading, code splitting, optimized builds
- **Maintainability**: Component-based architecture
- **Developer Experience**: Hot module replacement, fast rebuilds

### Migration Documentation
See [/docs/guides/MIGRATION_PROGRESS.md](docs/guides/MIGRATION_PROGRESS.md) for complete migration details.

---

## Changelog

### Version 70.4 - PM2 Workers & Package Upgrades (2025-11-23)

**Major Changes:**
- Upgraded all packages to latest versions (Vite 7, Vue 3.5, Laravel 11)
- Added PM2 Process Manager UI in Settings page
- Refactored Users to Subscribers with enhanced activity tracking
- Fixed multiple bugs in streams management
- Implemented background worker architecture with PM2

**Added:**
- **PM2 Process Manager** - Control workers and system services from Settings
  - Manage PM2 workers (stream-import, ffprobe)
  - Control system services (Nginx, MariaDB, PHP-FPM)
  - Real-time job queue statistics
  - Auto-refresh every 30 seconds
- **Background Workers** with PM2
  - Stream Import Worker (Node.js)
  - FFprobe Analysis Worker (Node.js Cluster, 2 instances)
  - Job queue system with status tracking
- **Enhanced Subscriber Management**
  - Renamed from "Users" to "Subscribers"
  - Improved activity tracking
  - Better UI/UX
- **Package Upgrades**
  - Vite 5 → 7.2.4 (major upgrade, ESM-only)
  - Vue 3.4 → 3.5.13 (56% memory reduction, 10x faster arrays)
  - Laravel/Illuminate 10 → 11.46.1 (15% faster bootstrap)
  - Carbon 2.x → 3.10.3 (major upgrade)
  - PHPUnit 10 → 11.5.44
  - PHPStan 1.x → 2.1.32
  - Symfony 6.x → 7.3.x
  - All other packages to latest stable versions

**Fixed:**
- Streams management bugs and edge cases
- Activity tracking for subscribers
- Vite 7 ESM compatibility (updated vite.config.js)
- Laravel 11 breaking changes compatibility
- Node.js version requirements (now 20.19+)

**Documentation:**
- `docs/PM2_MANAGEMENT_UI.md` - PM2 UI implementation details
- `docs/PM2_WORKERS_IMPLEMENTATION.md` - Worker architecture
- `docs/PM2_SUDO_PASSWORD_UPDATE.md` - Sudo configuration guide
- `docs/guides/PM2_BACKGROUND_WORKERS_GUIDE.md` - Complete PM2 guide
- `docs/guides/PACKAGE_UPGRADE_2025.md` - Package upgrade documentation
- `docs/UPGRADE_SUMMARY.txt` - Quick upgrade summary

### Version 70.3 - Vue.js SPA Complete (2025-11-22)

**Major Changes:**
- Complete migration to Vue.js 3 SPA admin interface
- Added Advanced Security page (UFW + fail2ban integration)
- Created Security dropdown menu
- Unified installation script (debian12-installer)
- Reorganized project structure

**Added:**
- Vue.js 3 admin panel with 16 components
- 13 RESTful API endpoints
- Advanced Security management (UFW/fail2ban)
- Activity monitoring with statistics
- FFmpeg testing tools in Settings
- Security dropdown navigation
- `/scripts/` directory for utilities
- Comprehensive documentation in `/docs/`

**Removed:**
- 35 legacy PHP/Blade admin files
- 3 duplicate installation scripts
- index-secure.php (replaced by Vue.js login)
- 18+ markdown files from root (moved to /docs/)

**Changed:**
- Moved utilities to `/scripts/` directory
- Unified installation to `debian12-installer`
- Updated all documentation references
- Modernized README with current architecture

### Version 70.2 - Debian 12 Support (2025-11-21)

**Added:**
- Debian 12 (Bookworm) support
- PHP 8.4 compatibility
- MariaDB 11.4 support
- Nginx 1.26+ with HTTP/2, HTTP/3
- Argon2id password hashing
- CSRF token protection
- Rate limiting
- Security logging and audit trails
- Input validation and sanitization
- Modern security headers
- Systemd service files
- Comprehensive migration tools

**Security Improvements:**
- Replaced MD5 with Argon2id for passwords
- Added CSRF protection to all forms
- Implemented login rate limiting (5 attempts/15 min)
- Added security event logging
- Enhanced session security
- Input validation for all user inputs
- Security headers via nginx
- DDoS protection via rate limiting

**Changed:**
- Updated installation script for Debian 12
- Modernized PHP-FPM configuration
- Enhanced nginx configuration
- Improved error handling
- Better log management with rotation

**Documentation:**
- Added MIGRATION_PLAN.md
- Updated README with Debian 12 instructions
- Added security best practices
- Added troubleshooting guide

### Version 70 - Original Release

**Features:**
- Multi-user streaming platform
- RTMP/HLS support
- Transcoding with multiple profiles
- Web-based management panel
- User and stream management
- IP and User-Agent blocking
- Playlist import
- Auto-restart via cron

---

## 💬 Support & Contact

<div align="center">

### Enjoying FOS-Streaming Reborn?

Your support helps keep this project alive and growing! 🚀

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Support%20Development-orange?style=for-the-badge&logo=buy-me-a-coffee&logoColor=white)](https://buymeacoffee.com/CristianCasapu)

**☕ [Support the project with a coffee!](https://buymeacoffee.com/CristianCasapu)**

---

### Need Help or Have Questions?

I'm available on Telegram for support, feedback, and feature requests!

[![Telegram](https://img.shields.io/badge/Telegram-Contact%20Me-blue?style=for-the-badge&logo=telegram&logoColor=white)](https://t.me/CristianCasapu)

**💬 [Contact me on Telegram](https://t.me/CristianCasapu)**

---

### Other Ways to Contribute

- ⭐ **Star this repository** on GitHub
- 🐛 **Report bugs** via GitHub Issues
- 💡 **Suggest features** via GitHub Discussions
- 🔧 **Submit pull requests** to improve the code
- 📖 **Improve documentation** and tutorials
- 📢 **Share with others** who might benefit

Every contribution, no matter how small, makes a difference! ❤️

</div>

---

**Copyright © 2025 FOS-Streaming. All Rights Reserved.**
