# PM2 Workers Deployment Guide

**Version**: 70.0.0 (Security Fortress)
**Last Updated**: 2025-11-24
**Status**: Complete with all 8 workers

## Overview

This guide covers the deployment and management of all 8 PM2 background workers for FOS-Streaming Reborn v70.

### All PM2 Workers

| Priority | Worker Name | Category | Purpose |
|----------|-------------|----------|---------|
| 10 | stream-import-worker | Streaming | Import streams from M3U playlists |
| 20 | ffprobe-worker | Streaming | Analyze stream technical details (2 instances) |
| 30 | stream-manager-worker | Streaming | Manage stream lifecycle |
| 40 | stream-monitor-worker | Monitoring | Health checks and logging |
| 50 | website-health-worker | Monitoring | Website uptime monitoring |
| 60 | srt-proxy-worker | Proxy | SRT protocol with AES-256 encryption |
| 70 | v2ray-proxy-worker | Proxy | Traffic obfuscation (VMess/VLESS) |
| 80 | quic-proxy-worker | Proxy | HTTP/3 over QUIC with TLS 1.3 |

**Total Processes**: 9 (8 unique workers, ffprobe runs 2 instances in cluster mode)

---

## Fresh Deployment Setup

### Step 1: Run Database Migrations

```bash
# Navigate to project root
cd /path/to/FOS-Streaming-v69

# Run migrations to create pm2_workers table
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} < database/migrations/2025-11-23_create_pm2_workers_table.sql
mysql -u ${DB_USERNAME} -p${DB_PASSWORD} ${DB_DATABASE} < database/migrations/2025-11-24_add_missing_pm2_workers_columns.sql
```

### Step 2: Seed PM2 Workers

```bash
# Run the PM2 workers seeder
php database/seed.php PM2WorkersSeeder
```

**Output**:
```
PM2 workers seeded successfully with all 8 workers!
```

### Step 3: Generate Ecosystem Configuration

```bash
# Generate ecosystem.config.cjs from database
php -r "
require_once 'config.php';
use App\Services\PM2WorkerService;
\$service = new PM2WorkerService();
\$result = \$service->generateEcosystemConfig();
echo \$result['message'] . PHP_EOL;
"
```

**Output**:
```
Successfully generated ecosystem.config.cjs with 8 workers
```

### Step 4: Start PM2 Workers

```bash
# Start all workers
pm2 start ecosystem.config.cjs

# Save PM2 process list
pm2 save

# Enable PM2 startup on boot
pm2 startup
# Follow the instructions provided by PM2
```

### Step 5: Verify Workers

```bash
# Check all workers are running
pm2 list

# View worker logs
pm2 logs --lines 50
```

---

## Web UI Management

### Access PM2 Manager

1. Login to Admin Panel
2. Navigate to **Settings** page
3. Scroll to **PM2 Background Workers** section

### Available Actions

#### Global Controls

- **Start All** - Start all workers from ecosystem config
- **Stop All** - Stop all running workers
- **Restart All** - Restart all workers with zero-downtime
- **Sync Workers** - Regenerate ecosystem.config.cjs from database and reload
- **Refresh** - Update worker status display

#### Individual Worker Controls

For each worker:
- **Start** - Launch the worker (if stopped)
- **Stop** - Gracefully stop the worker
- **Restart** - Zero-downtime restart
- **Edit** - Modify worker configuration

### Sync Workers Button

The **Sync Workers** button performs these actions:

1. Reads all enabled workers from `pm2_workers` database table
2. Generates new `ecosystem.config.cjs` file
3. Reloads PM2 configuration
4. Restarts affected workers

**Use Cases**:
- After editing worker configuration in database
- After adding new workers via seeder
- To apply configuration changes from Admin UI

---

## Database Seeder Reference

### Running Individual Seeders

```bash
# Seed only PM2 workers
php database/seed.php PM2WorkersSeeder

# Seed all database tables
php database/seed.php
```

### Seeder Location

- **File**: [database/seeders/PM2WorkersSeeder.php](../../database/seeders/PM2WorkersSeeder.php)
- **Class**: `Database\Seeders\PM2WorkersSeeder`

### What the Seeder Does

1. **Truncates** existing `pm2_workers` table
2. **Inserts** all 8 workers with complete configuration
3. Sets appropriate:
   - Priority levels (10-80)
   - Memory limits (200M-500M)
   - Execution modes (fork/cluster)
   - Log file paths
   - Cron restart schedules
   - Categories and tags

---

## Worker Categories

### Streaming Workers (Priority 10-30)

**Purpose**: Handle stream operations and analysis

1. **stream-import-worker** (fork, 1 instance, 500M)
   - Imports M3U playlists
   - Daily cron: 3 AM

2. **ffprobe-worker** (cluster, 2 instances, 300M)
   - Analyzes stream codecs, bitrate, resolution
   - Parallel processing with 2 instances

3. **stream-manager-worker** (fork, 1 instance, 400M)
   - Manages stream lifecycle
   - Critical for stream operations

### Monitoring Workers (Priority 40-50)

**Purpose**: Health checks and uptime monitoring

4. **stream-monitor-worker** (fork, 1 instance, 300M)
   - Monitors active streams
   - Logs performance metrics

5. **website-health-worker** (fork, 1 instance, 200M)
   - Website uptime monitoring
   - Platform availability checks

### Proxy Workers (Priority 60-80) - Enterprise Features

**Purpose**: Secure, encrypted, obfuscated streaming delivery

6. **srt-proxy-worker** (fork, 1 instance, 400M)
   - SRT protocol proxy
   - AES-256 encryption
   - Device locking validation
   - **No transcoding** - proxy only

7. **v2ray-proxy-worker** (fork, 1 instance, 500M)
   - VMess/VLESS protocols
   - WebSocket tunneling
   - Domain fronting
   - DPI bypass
   - **No transcoding** - proxy only

8. **quic-proxy-worker** (fork, 1 instance, 400M)
   - HTTP/3 over QUIC
   - TLS 1.3 with ECH
   - 0-RTT connection
   - Lossy network optimization
   - **No transcoding** - proxy only

---

## Configuration Management

### Via Database

```php
// Update worker configuration
$worker = PM2Worker::where('name', 'srt-proxy-worker')->first();
$worker->max_memory_restart = '600M';
$worker->instances = 2;
$worker->save();

// Regenerate ecosystem config
use App\Services\PM2WorkerService;
$service = new PM2WorkerService();
$service->syncWithPM2();
```

### Via Admin UI

1. Go to **Settings → PM2 Background Workers**
2. Click **Edit** on any worker
3. Modify configuration:
   - Script path
   - Instances (1-16)
   - Execution mode (fork/cluster)
   - Memory limit
   - Max restarts
   - Min uptime
   - Cron schedule
   - Log level
4. Click **Save Configuration**
5. Worker automatically restarts with new config

### Via API

```bash
# Get worker configuration
curl -X GET "http://localhost/admin/api/pm2.php?action=get_config&worker=srt-proxy-worker"

# Update worker configuration
curl -X POST "http://localhost/admin/api/pm2.php?action=update_config" \
  -H "Content-Type: application/json" \
  -d '{
    "worker": "srt-proxy-worker",
    "config": {
      "max_memory_restart": "600M",
      "instances": 2
    }
  }'

# Sync all workers
curl -X GET "http://localhost/admin/api/pm2.php?action=sync"
```

---

## Troubleshooting

### Workers Not Showing in UI

**Symptom**: PM2 Manager page shows empty or incomplete worker list

**Solution**:
```bash
# 1. Check database has all workers
php -r "require_once 'config.php'; echo PM2Worker::count() . ' workers in database' . PHP_EOL;"

# 2. Verify API returns workers
php -r "require_once 'config.php'; require_once 'public/admin/api/pm2.php';" | jq '.data.pm2_workers | length'

# 3. Check browser console for errors (F12)
# Look for: [PM2Manager] Loaded workers: X

# 4. Rebuild frontend
npm run build
```

### Worker Won't Start

**Symptom**: Worker shows "stopped" or "errored" status

**Solution**:
```bash
# Check worker logs
pm2 logs worker-name --lines 100 --err

# Check worker script exists
ls -la workers/worker-name.js

# Check Node.js dependencies
npm install

# Try manual start
pm2 start ecosystem.config.cjs --only worker-name

# Check for port conflicts
netstat -tulpn | grep :PORT
```

### Ecosystem Config Out of Sync

**Symptom**: Database has 8 workers but PM2 shows different count

**Solution**:
```bash
# Method 1: Via Web UI
# Settings → PM2 Manager → Click "Sync Workers"

# Method 2: Via CLI
php -r "
require_once 'config.php';
use App\Services\PM2WorkerService;
\$service = new PM2WorkerService();
\$result = \$service->syncWithPM2();
echo \$result['message'] . PHP_EOL;
"

# Method 3: Manual regeneration
pm2 delete all
php -r "/* ... generate ecosystem ... */"
pm2 start ecosystem.config.cjs
```

### PM2 Not Installed

**Symptom**: `pm2: command not found`

**Solution**:
```bash
# Install PM2 globally
npm install -g pm2

# Or via Web UI
# Settings → PM2 Manager → Click "Install PM2 Automatically"
```

---

## Best Practices

### 1. Always Use Web UI for Config Changes

✅ **Good**: Edit worker config in Admin UI, click Save
❌ **Bad**: Manually edit `ecosystem.config.cjs` file

**Reason**: Manual edits will be overwritten on next sync

### 2. Use Sync After Database Changes

Whenever you:
- Add new workers via database/seeder
- Modify worker configuration in database
- Run migrations that affect pm2_workers table

**Always run**: Settings → PM2 Manager → **Sync Workers**

### 3. Monitor Worker Memory Usage

```bash
# Check memory usage
pm2 monit

# View detailed info
pm2 show worker-name
```

Adjust `max_memory_restart` if workers frequently restart due to memory.

### 4. Check Logs Regularly

```bash
# View all logs
pm2 logs

# View specific worker
pm2 logs srt-proxy-worker --lines 200

# View only errors
pm2 logs --err
```

### 5. Use PM2 Save After Manual Changes

```bash
# Save current process list
pm2 save

# Restore after reboot
pm2 resurrect
```

---

## Deployment Checklist

- [ ] Database migrations applied
- [ ] PM2WorkersSeeder executed
- [ ] `ecosystem.config.cjs` generated
- [ ] PM2 installed (`pm2 --version`)
- [ ] All 8 workers seeded in database
- [ ] Workers started (`pm2 list` shows 9 processes)
- [ ] PM2 process list saved (`pm2 save`)
- [ ] PM2 startup configured (`pm2 startup`)
- [ ] Web UI shows all 8 workers
- [ ] Worker controls functional (start/stop/restart)
- [ ] Sync Workers button works
- [ ] Worker logs accessible
- [ ] No errors in browser console

---

## API Endpoints Reference

### PM2 Worker Management

**Base URL**: `/admin/api/pm2.php`

| Action | Method | Endpoint | Description |
|--------|--------|----------|-------------|
| status | GET | `?action=status` | Get all workers and services status |
| start | GET | `?action=start&worker_id=all` | Start workers |
| stop | GET | `?action=stop&worker_id=all` | Stop workers |
| restart | GET | `?action=restart&worker_id=all` | Restart workers |
| sync | GET | `?action=sync` | Regenerate config and reload |
| get_config | GET | `?action=get_config&worker=name` | Get worker config |
| update_config | POST | `?action=update_config` | Update worker config |
| logs | GET | `?action=logs&worker=name&lines=100` | Get worker logs |
| queue_stats | GET | `?action=queue_stats` | Get job queue statistics |
| install | GET | `?action=install` | Install PM2 globally |

---

## Related Files

- **Seeder**: [database/seeders/PM2WorkersSeeder.php](../../database/seeders/PM2WorkersSeeder.php)
- **Migration**: [database/migrations/2025-11-23_create_pm2_workers_table.sql](../../database/migrations/2025-11-23_create_pm2_workers_table.sql)
- **Model**: [models/PM2Worker.php](../../models/PM2Worker.php)
- **Service**: [app/Services/PM2WorkerService.php](../../app/Services/PM2WorkerService.php)
- **API**: [public/admin/api/pm2.php](../../public/admin/api/pm2.php)
- **UI Component**: [resources/js/components/PM2Manager.vue](../../resources/js/components/PM2Manager.vue)
- **Ecosystem Config**: [ecosystem.config.cjs](../../ecosystem.config.cjs) (auto-generated)

---

## Support

For issues or questions:
1. Check worker logs: `pm2 logs worker-name`
2. Review browser console (F12)
3. Check [PM2 Manager Bug Fixes](PM2_MANAGER_BUG_FIXES.md)
4. Report issues on GitHub

---

**Last Updated**: 2025-11-24
**Maintained By**: FOS-Streaming Development Team
