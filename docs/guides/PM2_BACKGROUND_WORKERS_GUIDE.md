# PM2 Background Workers Guide

Complete guide to PM2 process management for FOS-Streaming v70 background workers.

## Overview

FOS-Streaming v70 uses PM2 to manage background workers that process:
1. **Stream Imports** - Bulk M3U playlist imports
2. **FFprobe Analysis** - Stream technical analysis

### Why PM2?

- ✅ **Auto-restart** on failure
- ✅ **Start at boot** with systemd
- ✅ **Cluster mode** for scaling
- ✅ **Log management** with rotation
- ✅ **Monitoring** and metrics
- ✅ **Zero-downtime** reload
- ✅ **Environment-aware** (dev/prod)

---

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│ Web Application (Vue.js + PHP)                           │
├──────────────────────────────────────────────────────────┤
│ User clicks "Import Streams"                             │
│    ↓                                                      │
│ Creates job in queue (storage/jobs/stream-import/)       │
│    ↓                                                      │
│ Returns immediately to user                               │
└──────────────────────────────────────────────────────────┘
                       ↓
┌──────────────────────────────────────────────────────────┐
│ PM2 Workers (Background Services)                        │
├──────────────────────────────────────────────────────────┤
│                                                           │
│ ┌─────────────────────┐  ┌──────────────────────────┐  │
│ │ stream-import-worker│  │ ffprobe-worker (x2)      │  │
│ │ (Node.js)           │  │ (Node.js Cluster)        │  │
│ ├─────────────────────┤  ├──────────────────────────┤  │
│ │ Polls queue every   │  │ Polls queue every        │  │
│ │ 5 seconds           │  │ 10 seconds               │  │
│ │    ↓                │  │    ↓                     │  │
│ │ Calls PHP script    │  │ Calls PHP script         │  │
│ │    ↓                │  │    ↓                     │  │
│ │ Creates streams     │  │ Analyzes with FFprobe    │  │
│ │    ↓                │  │    ↓                     │  │
│ │ Queues for FFprobe  │  │ Updates database         │  │
│ └─────────────────────┘  └──────────────────────────┘  │
└──────────────────────────────────────────────────────────┘
```

---

## Installation

### 1. Install PM2 Globally

```bash
# Using npm
npm install -g pm2

# Or using the project's package.json
npm install
```

### 2. Setup PM2 Startup

Configure PM2 to start at boot:

```bash
# Generate startup script
pm2 startup

# Follow the command PM2 outputs (usually requires sudo)
sudo env PATH=$PATH:/usr/bin pm2 startup systemd -u fos-streaming --hp /home/fos-streaming
```

### 3. Create Required Directories

```bash
# Create job queue directories
mkdir -p storage/jobs/stream-import
mkdir -p storage/jobs/ffprobe-analysis
mkdir -p storage/logs

# Set permissions
chmod -R 755 storage/jobs
chmod -R 755 storage/logs
```

---

## Starting Workers

### Start All Workers

```bash
# Using PM2 directly
pm2 start ecosystem.config.js

# Or using npm script
npm run pm2:start

# Save PM2 configuration
pm2 save
```

### Start Specific Worker

```bash
# Start only import worker
pm2 start ecosystem.config.js --only stream-import-worker

# Start only FFprobe worker
pm2 start ecosystem.config.js --only ffprobe-worker
```

---

## Managing Workers

### Check Status

```bash
# View all workers
pm2 status

# Or using npm
npm run pm2:status

# Example output:
# ┌─────┬────────────────────────┬─────────┬─────────┬─────────┬──────────┐
# │ id  │ name                   │ mode    │ ↺       │ status  │ cpu      │
# ├─────┼────────────────────────┼─────────┼─────────┼─────────┼──────────┤
# │ 0   │ stream-import-worker   │ fork    │ 0       │ online  │ 0%       │
# │ 1   │ ffprobe-worker         │ cluster │ 0       │ online  │ 0%       │
# │ 2   │ ffprobe-worker         │ cluster │ 0       │ online  │ 0%       │
# └─────┴────────────────────────┴─────────┴─────────┴─────────┴──────────┘
```

### View Logs

```bash
# All workers
pm2 logs

# Specific worker
pm2 logs stream-import-worker
pm2 logs ffprobe-worker

# Last 100 lines
pm2 logs --lines 100

# Follow logs in real-time
pm2 logs --raw
```

### Restart Workers

```bash
# Restart all
pm2 restart all

# Restart specific worker
pm2 restart stream-import-worker

# Graceful reload (zero-downtime)
pm2 reload all
```

### Stop Workers

```bash
# Stop all
pm2 stop all

# Stop specific
pm2 stop stream-import-worker

# Delete from PM2
pm2 delete all
```

### Monitor Workers

```bash
# Real-time monitoring
pm2 monit

# Web-based dashboard
pm2 plus
```

---

## Configuration

### Ecosystem File

Located at `/ecosystem.config.js`:

```javascript
module.exports = {
  apps: [
    {
      name: 'stream-import-worker',
      script: './workers/stream-import-worker.js',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 10,
      max_memory_restart: '500M',
      cron_restart: '0 3 * * *', // Restart daily at 3 AM
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      env_development: {
        NODE_ENV: 'development',
        LOG_LEVEL: 'debug'
      }
    },
    // ... ffprobe-worker config
  ]
};
```

### Environment Variables

**Production:**
- `NODE_ENV=production`
- `LOG_LEVEL=warn`

**Development:**
- `NODE_ENV=development`
- `LOG_LEVEL=debug`

Set environment when starting:

```bash
# Production
pm2 start ecosystem.config.js --env production

# Development
pm2 start ecosystem.config.js --env development
```

---

## Job Queue System

### How It Works

1. **Web app creates job**
   ```php
   $queueService = new JobQueueService();
   $jobId = $queueService->push('stream-import', [
       'streams' => $streamData
   ]);
   ```

2. **Worker polls queue**
   - Checks `storage/jobs/stream-import/` every 5 seconds
   - Picks oldest pending job
   - Marks as "processing"

3. **Worker processes job**
   - Calls PHP script with job ID
   - PHP script reads job data
   - Performs import/analysis
   - Updates status

4. **Job completion**
   - Success: Moved to `completed/` folder
   - Failure: Retried up to 3 times, then moved to `failed/`

### Job Status

Jobs have these states:
- `pending` - Waiting to be processed
- `processing` - Currently being processed
- `completed` - Successfully finished
- `failed` - Failed after max attempts

### Queue Structure

```
storage/jobs/
├── stream-import/
│   ├── job_xxx.json         # Pending/processing jobs
│   ├── completed/
│   │   └── job_xxx.json     # Completed jobs
│   └── failed/
│       └── job_xxx.json     # Failed jobs
└── ffprobe-analysis/
    ├── job_xxx.json
    ├── completed/
    └── failed/
```

---

## Monitoring & Debugging

### Check Worker Health

```bash
# PM2 status
pm2 status

# Detailed info
pm2 show stream-import-worker
pm2 show ffprobe-worker

# Memory/CPU usage
pm2 monit
```

### View Logs

**PM2 Logs:**
```bash
# Real-time logs
pm2 logs --lines 50

# Specific worker
pm2 logs stream-import-worker --lines 100
```

**File Logs:**
```bash
# Import worker logs
tail -f storage/logs/pm2-stream-import-combined.log

# FFprobe worker logs
tail -f storage/logs/pm2-ffprobe-combined.log

# Error logs only
tail -f storage/logs/pm2-stream-import-error.log
tail -f storage/logs/pm2-ffprobe-error.log
```

### Check Queue Status

```php
// Via PHP
$queueService = new JobQueueService();
$stats = $queueService->getQueueStats('stream-import');
print_r($stats);

// Output:
// [
//   'queue' => 'stream-import',
//   'pending' => 5,
//   'processing' => 1,
//   'completed' => 120,
//   'failed' => 2,
//   'total' => 128
// ]
```

### Common Issues

**Worker not starting:**
```bash
# Check logs
pm2 logs stream-import-worker --err

# Manually test
node workers/stream-import-worker.js
```

**Jobs not processing:**
```bash
# Check queue directory permissions
ls -la storage/jobs/

# Check worker logs
pm2 logs --lines 200

# Manually process a job
php scripts/process-import-job.php job_xxx
```

**High memory usage:**
```bash
# Restart workers
pm2 restart all

# Or increase limit in ecosystem.config.js
max_memory_restart: '1G'
```

---

## Performance Tuning

### Scaling Workers

**FFprobe worker can run in cluster mode:**

```javascript
{
  name: 'ffprobe-worker',
  instances: 4, // 4 instances for parallel processing
  exec_mode: 'cluster'
}
```

Recommended instances:
- **Low-resource (2-4GB RAM):** 1-2 instances
- **Medium (8-16GB RAM):** 2-4 instances
- **High-resource (16GB+ RAM):** 4-8 instances

### Resource Limits

```javascript
{
  max_memory_restart: '500M', // Restart if exceeds
  max_restarts: 10,            // Max restarts in 1 minute
  min_uptime: '10s',          // Min uptime to consider stable
  restart_delay: 5000         // Delay between restarts (ms)
}
```

### Cron Restart

Restart workers daily to prevent memory leaks:

```javascript
{
  cron_restart: '0 3 * * *' // Every day at 3 AM
}
```

---

## Production Best Practices

### 1. Log Rotation

PM2 logs can grow large. Use PM2 log rotate module:

```bash
pm2 install pm2-logrotate

# Configure rotation
pm2 set pm2-logrotate:max_size 10M
pm2 set pm2-logrotate:retain 30
pm2 set pm2-logrotate:compress true
```

### 2. Startup at Boot

```bash
# Save current PM2 processes
pm2 save

# Generate startup script
pm2 startup

# Follow the outputted command
```

### 3. Monitoring

```bash
# Enable PM2 Plus (free tier)
pm2 plus

# Or use custom monitoring
pm2 install pm2-server-monit
```

### 4. Cleanup Old Jobs

Run cleanup periodically:

```php
// Remove completed/failed jobs older than 7 days
$queueService->cleanup('stream-import', 7);
$queueService->cleanup('ffprobe-analysis', 7);
```

Add to cron:
```bash
0 4 * * * cd /path/to/fos && php -r "require 'config.php'; require 'app/Services/JobQueueService.php'; \$q = new \App\Services\JobQueueService(); \$q->cleanup('stream-import', 7); \$q->cleanup('ffprobe-analysis', 7);"
```

---

## Troubleshooting

### Workers Keep Restarting

**Check error logs:**
```bash
pm2 logs --err --lines 200
```

**Common causes:**
- Missing dependencies: `npm install`
- Permission issues: Check file permissions
- PHP not found: Verify PHP path
- Config errors: Check ecosystem.config.js

### Jobs Stuck in Processing

**Reset stuck jobs:**
```bash
# Find stuck jobs
find storage/jobs/ -name "*.json" -type f

# Reset manually by editing JSON
# Change "status": "processing" to "status": "pending"
```

**Or via PHP:**
```php
$files = glob('storage/jobs/stream-import/job_*.json');
foreach ($files as $file) {
    $job = json_decode(file_get_contents($file), true);
    if ($job['status'] === 'processing' &&
        strtotime($job['updated_at']) < time() - 3600) {
        $job['status'] = 'pending';
        file_put_contents($file, json_encode($job, JSON_PRETTY_PRINT));
    }
}
```

### High CPU Usage

**Check worker status:**
```bash
pm2 monit
```

**Reduce poll frequency** in worker files:
```javascript
const POLL_INTERVAL = 15000; // Increase from 5000 to 15000
```

---

## API Integration

Workers are managed via PM2 API from Settings page:

```javascript
// Start workers
await pm2API.start();

// Stop workers
await pm2API.stop();

// Restart workers
await pm2API.restart();

// Get status
const status = await pm2API.getStatus();
```

See Settings page for UI controls.

---

## Complete Command Reference

### PM2 Commands

```bash
# Start
pm2 start ecosystem.config.js
pm2 start ecosystem.config.js --env development

# Stop
pm2 stop all
pm2 stop stream-import-worker

# Restart
pm2 restart all
pm2 restart stream-import-worker

# Delete
pm2 delete all
pm2 delete stream-import-worker

# Logs
pm2 logs
pm2 logs stream-import-worker --lines 100

# Status
pm2 status
pm2 show stream-import-worker

# Monitor
pm2 monit

# Save/Load
pm2 save
pm2 resurrect

# Startup
pm2 startup
pm2 unstartup
```

### NPM Scripts

```bash
npm run pm2:start
npm run pm2:stop
npm run pm2:restart
npm run pm2:status
npm run pm2:logs
npm run pm2:monit
```

---

## Support

- **PM2 Documentation:** https://pm2.keymetrics.io/docs/
- **FOS Streaming Issues:** https://github.com/CristianCasapu/FOS-Streaming-Reborn/issues
- **Job Queue:** `/app/Services/JobQueueService.php`
- **Workers:** `/workers/`

---

**Last Updated:** 2025-11-22
**Version:** 70.0.0
**Status:** Production Ready
