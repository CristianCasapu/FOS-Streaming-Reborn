# FOS-Streaming Reborn v70

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Support-orange?style=flat-square&logo=buy-me-a-coffee)](https://buymeacoffee.com/CristianCasapu)
[![Telegram](https://img.shields.io/badge/Telegram-Contact-blue?style=flat-square&logo=telegram)](https://t.me/CristianCasapu)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php)](https://www.php.net)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=flat-square&logo=vue.js)](https://vuejs.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)](LICENSE)

An **enterprise-grade IPTV streaming platform** with advanced security features, modern Vue.js 3 admin panel, and comprehensive subscriber management. Built for ISP-resistant streaming with encryption, traffic obfuscation, and zero-trust architecture.

**Repository**: [CristianCasapu/FOS-Streaming-Reborn](https://github.com/CristianCasapu/FOS-Streaming-Reborn)

---

## Quick Links

- [Installation](#installation)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Documentation](#documentation)
- [Support](#support)

---

## What's New in v70

- **Modern Vue.js 3 SPA Admin Panel** - Complete rewrite with Composition API
- **Laravel 11 API Backend** - RESTful APIs with Eloquent ORM
- **TailwindCSS UI** - Beautiful, responsive interface
- **Advanced Security** - UFW firewall + fail2ban integration
- **PM2 Process Manager** - Background workers management from admin UI
- **Subscriber Management** - Packages, bouquets, subscriptions, trials
- **Real-time Monitoring** - Activity tracking with statistics dashboard
- **8 Background Workers** - Stream import, FFprobe analysis, health monitoring

---

## System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **OS** | Debian 12 / Ubuntu 24.04 | Debian 12 |
| **PHP** | 8.4.x | 8.4.x |
| **MariaDB** | 11.4.x | 11.4.x |
| **Node.js** | 20.19+ LTS | 20 LTS |
| **Nginx** | 1.26+ | Custom build with HTTP-FLV |
| **RAM** | 2 GB | 4 GB+ |
| **Disk** | 20 GB | 50 GB+ |
| **CPU** | 2 cores | 4 cores+ |

---

## Installation

### Step-by-Step Installation

The installation is modular for better control and reliability. See [install/README.md](install/README.md) for the complete guide.

```bash
# Step 1: As root - Install prerequisites and create user
apt-get update && apt-get install -y python3 git curl wget sudo ca-certificates gnupg
curl -fsSL https://raw.githubusercontent.com/CristianCasapu/FOS-Streaming-Reborn/develop/install/01-setup-user.py -o /tmp/01-setup-user.py
python3 /tmp/01-setup-user.py
# Save the displayed password!

# Step 2: Switch to new user and clone
su - fosadmin
git clone https://github.com/CristianCasapu/FOS-Streaming-Reborn.git FOS-Streaming
cd ~/FOS-Streaming

# Step 3: Install dependencies
python3 install/02-install-deps.py

# Step 4: Database setup (manual - see install/README.md)

# Step 5: Configure and deploy
cp .env.example .env
nano .env  # Update database credentials
composer install --no-dev --optimize-autoloader
source ~/.nvm/nvm.sh && npm install && npm run build
php artisan migrate && php artisan db:seed

# Step 6: Service configuration
python3 install/03-setup-services.py

# Step 7: Start services
sudo systemctl restart php8.4-fpm
sudo systemctl start fos-nginx
source ~/.nvm/nvm.sh && npm run pm2:start && pm2 save
```

### Post-Installation

1. **Access Admin Panel**: `http://your-server:8000/admin#/login`
2. **Default Login**: `admin` / `admin`
3. **Change Password Immediately** - Navigate to Settings

---

## Features

### Streaming

| Feature | Description |
|---------|-------------|
| **RTMP/HLS/HTTP-FLV** | Full protocol support with authentication |
| **Transcoding** | 20+ FFmpeg profiles with presets |
| **Stream Import** | M3U/M3U8 playlist import |
| **Auto-Restart** | Automated stream monitoring and recovery |
| **Mass Operations** | Bulk start/stop/delete streams |

### Admin Panel (Vue.js 3 SPA)

| Section | Components |
|---------|------------|
| **Dashboard** | Real-time stats, charts, activity feed |
| **Streams** | Manage, Bouquets, Categories, Packages |
| **Subscribers** | Subscribers, Subscriptions, Trials, Activity |
| **Security** | IP Blocks, User-Agent Blocks, UFW, fail2ban |
| **Settings** | System config, FFmpeg testing, PM2 Manager |
| **Staff** | RBAC with Admin/Supervisor/Support roles |

### Security

- **Argon2id Password Hashing** - PHP 8.4 native
- **CSRF Protection** - Token-based validation
- **Rate Limiting** - Brute-force protection (5 attempts/15 min)
- **UFW Firewall** - Enable/disable, manage rules via web UI
- **fail2ban Integration** - Monitor jails, ban/unban IPs
- **Security Headers** - X-Frame-Options, X-Content-Type-Options, CSP
- **Audit Logging** - Comprehensive event tracking

### Background Workers (PM2)

| Worker | Purpose |
|--------|---------|
| `stream-import-worker` | M3U playlist import processing |
| `ffprobe-worker` | Stream analysis (codec, bitrate, resolution) |
| `stream-manager-worker` | Stream lifecycle management |
| `stream-monitor-worker` | Health checks and logging |
| `website-health-worker` | Website uptime monitoring |
| `srt-proxy-worker` | SRT protocol with AES-256 encryption |
| `quic-proxy-worker` | QUIC/HTTP3 proxy |
| `v2ray-proxy-worker` | V2Ray traffic obfuscation |

---

## Tech Stack

### Backend

- **PHP 8.4** with Laravel Illuminate components
- **Eloquent ORM** for database operations
- **MariaDB 11.4** with UTF8MB4 support
- **Redis** for caching and sessions

### Frontend

- **Vue.js 3.5** with Composition API
- **Pinia** for state management
- **Vue Router** (hash mode)
- **TailwindCSS 3.4**
- **Vite 7** for builds

### Streaming

- **Nginx** with HTTP-FLV module
- **FFmpeg** for transcoding
- **PM2** for worker management
- **Node.js 20 LTS**

---

## Project Structure

```
FOS-Streaming/
├── app/                    # Laravel application classes
│   ├── Console/           # Artisan commands
│   ├── Http/              # Middleware and controllers
│   └── Services/          # Business logic services
├── database/
│   ├── migrations/        # SQL migrations
│   └── seeders/           # Database seeders
├── docs/                   # Documentation
│   ├── guides/            # Feature guides
│   ├── install/           # Installation docs
│   └── database/          # Database docs
├── install/               # Installation scripts
│   ├── config/            # Config templates
│   │   ├── nginx/         # Nginx vhosts
│   │   └── php-fpm/       # PHP-FPM pools
│   ├── 01-setup-user.py   # User setup
│   ├── 02-install-deps.py # Dependencies
│   └── 03-setup-services.py # Service config
├── models/                 # Eloquent models (35 models)
├── public/admin/
│   ├── api/               # REST API endpoints (30 endpoints)
│   └── index.html         # Vue SPA entry
├── resources/
│   ├── js/
│   │   ├── views/         # Vue components (44 views)
│   │   ├── components/    # Reusable components
│   │   ├── stores/        # Pinia stores
│   │   ├── services/      # API service layer
│   │   └── router/        # Vue Router config
│   └── css/               # TailwindCSS styles
├── workers/               # PM2 background workers (8 workers)
├── fospackv69/            # Nginx build and binaries
├── .env.example           # Environment template
├── composer.json          # PHP dependencies
├── package.json           # Node.js dependencies
├── ecosystem.config.js    # PM2 configuration
└── vite.config.js         # Frontend build config
```

---

## Common Commands

### Frontend

```bash
npm run dev              # Development server (port 5173)
npm run build            # Production build
npm run watch            # Watch mode
```

### Backend

```bash
composer install         # Install dependencies
composer test            # Run all tests
composer lint            # Auto-fix code style
composer analyze         # Static analysis (PHPStan)
composer check           # Run lint + analyze + test
```

### Database

```bash
php artisan migrate      # Run migrations
php artisan migrate:fresh --seed  # Fresh database
php artisan db:seed      # Seed data
```

### PM2 Workers

```bash
npm run pm2:start        # Start all workers
npm run pm2:stop         # Stop all workers
npm run pm2:status       # View status
npm run pm2:logs         # View logs
npm run pm2:monit        # Interactive monitoring
```

### Services

```bash
sudo systemctl start fos-nginx      # Start Nginx
sudo systemctl status fos-nginx     # Check status
sudo systemctl restart php8.4-fpm   # Restart PHP-FPM
```

---

## Configuration

### Environment Variables

Key settings in `.env`:

```ini
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=fos_streaming
DB_USERNAME=fos
DB_PASSWORD=your_password

# Ports
WEB_PORT=8000
STREAMING_PORT=8080
RTMP_PORT=1935

# Redis (optional)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password
```

### Ports

| Port | Service | Description |
|------|---------|-------------|
| 8000 | Web Panel | Admin dashboard and API |
| 8080 | Streaming | HLS/HTTP-FLV delivery |
| 1935 | RTMP | Stream ingestion |

---

## Streaming URLs

| Format | URL |
|--------|-----|
| **HLS** | `http://your-ip:8080/live/{user}/{pass}/{stream}/index.m3u8` |
| **Direct** | `http://your-ip:8080/live/{user}/{pass}/{stream}` |

---

## Documentation

| Document | Description |
|----------|-------------|
| [install/README.md](install/README.md) | Complete installation guide |
| [docs/guides/PLATFORM_REFACTORING_MASTER_PLAN.md](docs/guides/PLATFORM_REFACTORING_MASTER_PLAN.md) | Architecture roadmap |
| [docs/guides/LARAVEL_COMPONENTS_USAGE.md](docs/guides/LARAVEL_COMPONENTS_USAGE.md) | Laravel integration |
| [docs/guides/PM2_BACKGROUND_WORKERS_GUIDE.md](docs/guides/PM2_BACKGROUND_WORKERS_GUIDE.md) | PM2 worker guide |
| [docs/guides/SUBSCRIBER_MANAGEMENT_COMPLETED.md](docs/guides/SUBSCRIBER_MANAGEMENT_COMPLETED.md) | Subscriber system |
| [docs/guides/ENV_CONFIGURATION_GUIDE.md](docs/guides/ENV_CONFIGURATION_GUIDE.md) | Environment setup |

---

## Troubleshooting

### Web Panel Not Accessible

```bash
# Check nginx status
sudo systemctl status fos-nginx

# Verify port is listening
ss -tlnp | grep 8000

# Check PHP-FPM
sudo systemctl status php8.4-fpm
```

### Database Connection Issues

```bash
# Check MariaDB status
sudo systemctl status mariadb

# Test connection
mariadb -u fos -p fos_streaming -e "SELECT VERSION();"
```

### PM2 Worker Issues

```bash
# Check status
pm2 status

# View logs
pm2 logs <worker-name> --err --lines 50

# Restart all
pm2 restart all
```

### Permission Issues

```bash
# Fix ownership
sudo chown -R $(whoami):$(whoami) ~/FOS-Streaming

# Fix storage permissions
chmod -R 775 storage cache logs
```

---

## Roadmap

### Phase 1: Advanced Streaming (In Progress)

- SRT (Secure Reliable Transport) with AES-256
- QUIC/HTTP3 with TLS 1.3
- V2Ray integration (VMess/VLESS)

### Phase 2: Enhanced Security

- Traffic obfuscation via WebSocket tunneling
- CDN integration (Cloudflare/Sucuri)
- Auto-ban system for port scanners

### Phase 3: Enterprise Features

- VOD system with MKV multi-track support
- Load balancing with automatic failover
- Complete reseller portal

See [PLATFORM_REFACTORING_MASTER_PLAN.md](docs/guides/PLATFORM_REFACTORING_MASTER_PLAN.md) for details.

---

## Support

- **Issues**: [GitHub Issues](https://github.com/CristianCasapu/FOS-Streaming-Reborn/issues)
- **Telegram**: [@CristianCasapu](https://t.me/CristianCasapu)
- **Support Development**: [Buy Me a Coffee](https://buymeacoffee.com/CristianCasapu)

---

## Contributing

1. Fork the repository
2. Create a feature branch
3. Test thoroughly on Debian 12
4. Submit a pull request with detailed description

See [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) for guidelines.

---

## License

MIT License - See [LICENSE](LICENSE) for details.

---

## Credits

- FOS-Streaming Development Team (Original)
- [FFmpeg](https://ffmpeg.org)
- [Nginx](https://nginx.org)
- [nginx-http-flv-module](https://github.com/winshining/nginx-http-flv-module)
- [MariaDB](https://mariadb.org)
- [Vue.js](https://vuejs.org)
- [Laravel](https://laravel.com)

---

**Version**: 70.0.0 | **Codename**: Security Fortress | **Released**: 2025-11-21

Copyright 2025 FOS-Streaming. All Rights Reserved.
