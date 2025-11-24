# Fresh Deployment Database Guide

## Overview

This guide provides comprehensive instructions for setting up the FOS-Streaming v70 database from scratch. The deployment system uses Laravel-style migrations and seeders running standalone (without full Laravel installation).

**Last Updated**: 2025-11-24
**Database Version**: v70.0.0
**Required**: MariaDB 11.4+ or MySQL 8.0+

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Database Architecture](#database-architecture)
3. [Deployment Scripts](#deployment-scripts)
4. [Fresh Deployment Workflow](#fresh-deployment-workflow)
5. [Verification Steps](#verification-steps)
6. [Troubleshooting](#troubleshooting)
7. [Migration Details](#migration-details)
8. [Seeder Details](#seeder-details)

---

## Prerequisites

### Software Requirements

```bash
# Check PHP version (8.4+ required)
php -v

# Check MariaDB version (11.4+ required)
mysql --version

# Check Composer
composer --version
```

### Environment Configuration

Ensure your `.env` file has correct database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=fos_streaming
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

### File Structure

```
/database/
├── migrate.php                  # Migration runner
├── seed.php                     # Seeder runner
├── recreate_database.php        # Database recreation script
├── migrations/
│   └── laravel/                 # 24 migration files
│       ├── 2025_11_24_010001_create_staff_table.php
│       ├── 2025_11_24_010002_create_categories_table.php
│       └── ... (22 more files)
└── seeders/                     # 10 seeder files
    ├── PackagesSeeder.php
    ├── BouquetsSeeder.php
    ├── PM2WorkersSeeder.php
    ├── SettingsSeeder.php
    ├── AdminSeeder.php
    └── ... (5 more files)
```

---

## Database Architecture

### Total Tables: 38+

| Category | Tables | Count |
|----------|--------|-------|
| **Core** | subscribers, streams, packages, bouquets, subscriptions, trials | 6 |
| **Auth/Staff** | staff, staff_activity_logs, staff_sessions | 3 |
| **Configuration** | categories, transcodes, settings, pm2_workers | 4 |
| **Monitoring** | activity, stream_health_logs, website_health_logs, system_command_logs | 4 |
| **Security** | banned_ips, blocked_ips, blocked_user_agents, failed_login_attempts, security_events, ufw_rules | 6 |
| **Device Management** | device_fingerprints, device_sessions, device_bindings, device_violations | 4 |
| **SaaS/Reseller** | resellers, reseller_subscribers, reseller_transactions | 3 |
| **V2Ray/Proxy** | v2ray_users, v2ray_servers, v2ray_logs, v2ray_traffic_stats | 4 |
| **Audit** | audit_logs | 1 |
| **Pivot/Junction** | package_bouquet | 1 |
| **System** | migrations | 1 |

### Character Set

- **Charset**: `utf8mb4` (full Unicode support including emojis)
- **Collation**: `utf8mb4_unicode_ci` (case-insensitive, accent-insensitive)

---

## Deployment Scripts

### 1. recreate_database.php

**Purpose**: Drops and recreates the database with proper charset
**Warning**: ⚠️ DELETES ALL DATA

```bash
php database/recreate_database.php
```

**What it does**:
- Connects to MySQL without selecting database
- Drops database if exists
- Creates database with UTF8MB4 charset
- Displays database information
- Provides next steps

### 2. migrate.php

**Purpose**: Runs all Laravel migrations in order
**Safe**: Only runs new migrations (tracks in `migrations` table)

```bash
php database/migrate.php
```

**What it does**:
- Creates `migrations` table if not exists
- Scans `/database/migrations/laravel/` directory
- Sorts files alphabetically (timestamp order)
- Skips already-run migrations
- Runs `up()` method in each migration
- Records migration in `migrations` table
- Exits on first error

**Output Example**:
```
=== Running Migrations ===

→ Running: 2025_11_24_010001_create_staff_table
✓ Staff table created
✓ Completed: 2025_11_24_010001_create_staff_table

→ Running: 2025_11_24_010002_create_categories_table
✓ Categories table created
✓ Completed: 2025_11_24_010002_create_categories_table

...

✓ Successfully ran 24 migration(s)
```

### 3. seed.php

**Purpose**: Populates database with essential data
**Safe**: Checks for existing data before inserting

```bash
php database/seed.php
```

**What it does** (in order):
1. **PackagesSeeder** - 4 subscription tiers (Basic, Standard, Premium, Enterprise)
2. **BouquetsSeeder** - 8 bouquets (Sports, Movies, News, etc.)
3. **PackageBouquetSeeder** - Package-bouquet relationships
4. **PM2WorkersSeeder** - 8 worker configurations
5. **SettingsSeeder** - Application settings
6. **AdminRolesSeeder** - Role definitions
7. **ResellersSeeder** - Sample reseller accounts
8. **V2RayServersSeeder** - V2Ray server nodes

**Output Example**:
```
=== Running Database Seeders ===

→ Seeding packages...
  ✓ Packages seeded successfully!

→ Seeding bouquets...
  ✓ Bouquets seeded successfully!

...

✓ All seeders completed successfully!
```

### 4. AdminSeeder.php

**Purpose**: Creates default admin account
**Interactive**: Prompts for confirmation

```bash
php database/seeders/AdminSeeder.php
```

**What it does**:
- Checks if admin account exists
- Creates admin account if not exists
- Default credentials: `admin` / `admin`
- Forces password change on first login
- Displays login URL

**Output Example**:
```
=== Seeding Staff ===

✓ Default staff account created successfully

╔═══════════════════════════════════════╗
║     DEFAULT STAFF CREDENTIALS         ║
╠═══════════════════════════════════════╣
║  Username: admin                      ║
║  Password: admin                      ║
║  Role: Administrator                  ║
╠═══════════════════════════════════════╣
║  ⚠ SECURITY WARNING:                  ║
║  Change the default password          ║
║  immediately after first login!       ║
╚═══════════════════════════════════════╝

🌐 Admin Login URL:
   http://localhost:7777/admin#/login

=== Staff Seeding Complete ===
```

---

## Fresh Deployment Workflow

### Complete Setup (Fresh Install)

```bash
# Step 1: Ensure dependencies installed
composer install

# Step 2: Configure environment
cp .env.example .env
nano .env  # Edit database credentials

# Step 3: Create clean database
php database/recreate_database.php
# Type 'yes' when prompted

# Step 4: Run all migrations
php database/migrate.php

# Step 5: Seed essential data
php database/seed.php

# Step 6: Create admin account
php database/seeders/AdminSeeder.php

# Step 7: Verify
mysql -u your_user -p fos_streaming -e "SHOW TABLES;"
```

### Update Existing Database

```bash
# Only run new migrations
php database/migrate.php

# Re-seed if needed (safe - checks for existing data)
php database/seed.php
```

### Reset Database (Development Only)

```bash
# WARNING: Deletes all data
php database/recreate_database.php
php database/migrate.php
php database/seed.php
php database/seeders/AdminSeeder.php
```

---

## Verification Steps

### 1. Check Database Exists

```bash
mysql -u your_user -p -e "SHOW DATABASES LIKE 'fos_streaming';"
```

### 2. Verify Tables Created

```bash
mysql -u your_user -p fos_streaming -e "SHOW TABLES;" | wc -l
```

Expected: **38+ tables**

### 3. Check UTF8MB4 Charset

```bash
mysql -u your_user -p -e "
SELECT
    DEFAULT_CHARACTER_SET_NAME as charset,
    DEFAULT_COLLATION_NAME as collation
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'fos_streaming';
"
```

Expected:
- charset: `utf8mb4`
- collation: `utf8mb4_unicode_ci`

### 4. Verify Migrations Ran

```bash
mysql -u your_user -p fos_streaming -e "SELECT COUNT(*) as total FROM migrations;"
```

Expected: **24 rows**

### 5. Check Seeded Data

```bash
# Packages (should be 4)
mysql -u your_user -p fos_streaming -e "SELECT COUNT(*) FROM packages;"

# Bouquets (should be 8)
mysql -u your_user -p fos_streaming -e "SELECT COUNT(*) FROM bouquets;"

# PM2 Workers (should be 8)
mysql -u your_user -p fos_streaming -e "SELECT COUNT(*) FROM pm2_workers;"

# Settings (should be 1)
mysql -u your_user -p fos_streaming -e "SELECT COUNT(*) FROM settings;"

# Admin account (should be 1)
mysql -u your_user -p fos_streaming -e "SELECT username, role FROM staff WHERE username='admin';"
```

### 6. Test Admin Login

1. Start development server: `npm run dev`
2. Navigate to: `http://localhost:7777/admin#/login`
3. Login with: `admin` / `admin`
4. Should force password change on first login

---

## Troubleshooting

### Error: "Access denied for user"

**Solution**: Check `.env` credentials

```bash
# Test connection manually
mysql -u your_user -p
```

### Error: "Database does not exist"

**Solution**: Run `recreate_database.php` first

```bash
php database/recreate_database.php
```

### Error: "Table already exists"

**Solution**: Migration already ran or database not clean

```bash
# Check migrations table
mysql -u your_user -p fos_streaming -e "SELECT * FROM migrations ORDER BY batch DESC LIMIT 10;"

# Option 1: Skip (safe)
# Migrations automatically skip if already run

# Option 2: Reset (deletes data)
php database/recreate_database.php
php database/migrate.php
```

### Error: "Class not found"

**Solution**: Rebuild Composer autoloader

```bash
composer dump-autoload
```

### Error: "SQLSTATE[HY000] [2002] Connection refused"

**Solution**: Ensure MySQL/MariaDB is running

```bash
# Check status
systemctl status mariadb

# Start if stopped
sudo systemctl start mariadb
```

### Error: Charset warnings

**Solution**: Verify MySQL defaults

```bash
mysql -u your_user -p -e "SHOW VARIABLES LIKE 'char%';"
```

Should see `utf8mb4` as default charset.

### Migrations Run Out of Order

**Solution**: Delete `migrations` table and re-run

```bash
mysql -u your_user -p fos_streaming -e "DROP TABLE migrations;"
php database/migrate.php
```

---

## Migration Details

### Execution Order (by timestamp prefix)

**Phase 1: Foundation (010xxx - 015xxx)**
- `010001` - Staff table (admin accounts)
- `010002` - Categories table
- `010003` - Settings table
- `010004` - Transcodes table
- `015001` - Subscribers table

**Phase 2: Security & Monitoring (020xxx - 060xxx)**
- `020001` - Security tables (6 tables: banned_ips, blocked_ips, etc.)
- `030001` - Device locking tables (4 tables: fingerprints, sessions, etc.)
- `050001` - Streams table (massive table with 100+ columns)
- `060001` - Monitoring tables (4 tables: activity, health logs, etc.)

**Phase 3: Subscription Management (100xxx)**
- `100001` - Packages table
- `100002` - Bouquets table
- `100005` - Subscriptions table
- `100006` - Trials table
- `100007` - Package-bouquet pivot table
- `100009` - PM2 workers table

**Phase 4: Advanced Features (200xxx)**
- `200001` - Audit logs table
- `200002` - Resellers table
- `200003` - V2Ray tables (4 tables)
- `200004` - Update streams for advanced protocols
- `200005` - Add RBAC to admins

**Phase 5: Refactoring (300xxx, 900xxx)**
- `300002` - Migrate bouquets to streams (removes channels table)
- `900001` - Refactor users to subscribers
- `900002` - Update subscriptions table
- `900003` - Update packages table

### Critical Dependencies

1. **Subscribers table** must exist before:
   - Subscriptions
   - Trials
   - Activity logs

2. **Packages table** must exist before:
   - Subscriptions
   - Trials
   - Package-bouquet relationships

3. **Streams table** must exist before:
   - Bouquets (references stream_ids)
   - Health logs

4. **Staff table** must exist before:
   - Staff activity logs
   - Staff sessions
   - Audit logs

---

## Seeder Details

### PackagesSeeder

**Inserts**: 4 subscription tiers

| Package | Devices | Bandwidth | Quality | Recording | Price |
|---------|---------|-----------|---------|-----------|-------|
| Basic | 1 | 5 Mbps | SD | No | $9.99 |
| Standard | 2 | 10 Mbps | HD | No | $19.99 |
| Premium | 5 | 25 Mbps | FHD | Yes | $29.99 |
| Enterprise | 10 | Unlimited | UHD | Yes | $99.99 |

**Safe**: Checks if package name exists before inserting

### BouquetsSeeder

**Inserts**: 8 stream groupings

- Sports
- Movies
- News
- Entertainment
- Kids
- Documentary
- Music
- Premium

**Note**: `stream_ids` field is empty (populate via M3U import or admin UI)

### PM2WorkersSeeder

**Inserts**: 8 background workers

1. **stream-import-worker** - M3U playlist imports (fork, daily 3 AM)
2. **ffprobe-worker** - Stream analysis (cluster, 2 instances)
3. **stream-manager-worker** - Stream lifecycle management (fork)
4. **stream-monitor-worker** - Health checks (fork)
5. **website-health-worker** - Uptime monitoring (fork)
6. **srt-proxy-worker** - SRT proxy with encryption (fork)
7. **v2ray-proxy-worker** - Traffic obfuscation (fork)
8. **quic-proxy-worker** - HTTP/3 over QUIC (fork)

**Configuration**: Full PM2 config with logging, restart policies, environment vars, cron schedules

### SettingsSeeder

**Inserts**: Single settings row with 29 columns

**Categories**:
- **Core System**: ffmpeg/ffprobe paths, webport, folders, branding
- **Sudo/Commands**: sudo credentials, command execution flags
- **Trial Settings**: duration, approval requirements, limits
- **Device Security**: timeouts, registration limits, fingerprint TTL
- **Violation Thresholds**: low/medium/high/critical levels, time windows

**Safe**: Uses `COALESCE` to preserve existing values on re-run

### AdminSeeder

**Interactive**: Creates admin account

- Username: `admin`
- Password: `admin` (MD5 hashed)
- Role: `admin`
- Permissions: All (JSON array)
- Forces password change on first login

**Safe**: Checks if admin exists before creating

---

## Database Schema Summary

### Most Important Tables

#### subscribers
Customer accounts (renamed from `users` in v70)

**Key columns**: username, password, email, phone, country, city, enabled

#### streams
Core streaming content (100+ columns)

**Key columns**: name, streamurl (+ 2 backups), source_url, status, state, cat_id, trans_id

**Features**: Health monitoring, FFprobe analysis, PM2 integration, proxy settings, encryption

#### packages
Subscription tiers

**Key columns**: name, max_concurrent_devices, bandwidth_limit_mbps, video_quality, price, duration_days

#### bouquets
Stream groupings

**Key columns**: name, stream_ids (JSON array), is_active, sort_order

**Important**: Directly references streams (no channel pivot table in v70)

#### subscriptions
Active paid subscriptions

**Key columns**: subscriber_id, package_id, device, expire_date, is_active, max_concurrent_connections

#### trials
Free trial access

**Key columns**: subscriber_id (unique!), package_id, trial_duration_hours, expires_at, is_active

**Constraint**: One trial per subscriber (database-level unique constraint)

#### staff
Administrator accounts (replaced `admins` in v70)

**Key columns**: username, password, role, permissions, status

**Features**: RBAC, 2FA support, API tokens, session tracking

#### settings
Application configuration (columnar design)

**Key columns**: key, value, type, group, label, description

**Purpose**: Centralized config for entire platform

#### pm2_workers
PM2 worker configuration

**Key columns**: name, script, instances, exec_mode, env_vars, cron_restart, enabled

**Purpose**: Auto-generates `ecosystem.config.js`

---

## Post-Deployment Checklist

- [ ] Database created with UTF8MB4 charset
- [ ] All 24 migrations ran successfully
- [ ] All 8 seeders completed
- [ ] Admin account created (`admin`/`admin`)
- [ ] 38+ tables exist in database
- [ ] Admin login works at `http://localhost:7777/admin#/login`
- [ ] Password change forced on first login
- [ ] Settings page loads correctly
- [ ] PM2 workers configured
- [ ] Packages visible in admin UI
- [ ] Bouquets visible in admin UI
- [ ] Can create test subscriber
- [ ] Can create test subscription

---

## Best Practices

### Development

- Use `migrate.php` for updates (safe, tracks migrations)
- Use `seed.php` to refresh default data (safe, checks existence)
- Never edit migrations after deployment
- Create new migrations for schema changes
- Test migrations on dev database first

### Production

- **NEVER** run `recreate_database.php` (deletes all data!)
- Always backup before running migrations
- Test migrations on staging first
- Monitor migration logs for errors
- Document custom schema changes

### Maintenance

- Check migration status: `SELECT * FROM migrations;`
- Verify charset: `SHOW TABLE STATUS WHERE Name='table_name';`
- Monitor table sizes: `SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='fos_streaming';`
- Regular backups: `mysqldump -u user -p fos_streaming > backup.sql`

---

## Additional Resources

- **Project Root**: `/home/casapu/projects/FOS-Streaming-v69/`
- **CLAUDE.md**: Project overview and guidelines
- **README.md**: Installation and setup instructions
- **Platform Refactoring Plan**: `docs/guides/PLATFORM_REFACTORING_MASTER_PLAN.md`
- **Laravel Components Usage**: `docs/guides/LARAVEL_COMPONENTS_USAGE.md`

---

## Support

For issues or questions:
1. Check [CLAUDE.md](../../CLAUDE.md) for project context
2. Review migration file comments
3. Check seeder file comments
4. Examine model relationships
5. Test on development database first

---

**Document Version**: 1.0
**Last Verified**: 2025-11-24
**Compatibility**: FOS-Streaming v70.0.0
