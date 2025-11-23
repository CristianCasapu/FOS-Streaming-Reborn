# PM2 Stream Management - Implementation Guide

**Date:** 2025-11-23
**Status:** Ready for Testing
**Version:** v70

---

## 🎉 **Implementation Complete**

This document provides step-by-step instructions for deploying the PM2 stream management system.

---

## 📋 **What Was Implemented**

### **1. Database Enhancements**
✅ Enhanced `streams` table with 25+ new fields for state management
✅ Created `stream_health_logs` table for crash tracking
✅ Created `website_health_logs` table for system monitoring

### **2. Models & Services**
✅ Updated `Stream` model with state machine methods
✅ Created `StreamHealthLog` model
✅ Created `WebsiteHealthLog` model
✅ Created `StreamManagerService` - Executes stream commands
✅ Created `StreamMonitorService` - Monitors stream health
✅ Created `WebsiteHealthMonitorService` - Monitors system health

### **3. PM2 Workers**
✅ `stream-manager-worker` - Processes command queue (5s interval)
✅ `stream-monitor-worker` - Monitors PIDs (10s interval)
✅ `website-health-worker` - Monitors system (30s interval)

### **4. Configuration**
✅ Updated `ecosystem.config.js` with new workers

---

## 🚀 **Installation Steps**

### **Step 1: Backup Database**

```bash
# Create database backup
mysqldump -u root -p fos_streaming > fos_streaming_backup_$(date +%Y%m%d_%H%M%S).sql
```

### **Step 2: Run Database Migrations**

```bash
# Navigate to project directory
cd /home/casapu/projects/FOS-Streaming-v69

# Run migrations in order
mysql -u root -p fos_streaming < database/migrations/2025-11-23_enhance_streams_table_for_pm2_management.sql
mysql -u root -p fos_streaming < database/migrations/2025-11-23_create_stream_health_logs_table.sql
mysql -u root -p fos_streaming < database/migrations/2025-11-23_create_website_health_logs_table.sql
```

**Verify migrations:**
```sql
-- Check new streams table fields
DESC streams;

-- Check new tables exist
SHOW TABLES LIKE '%health_logs%';

-- Verify data migration
SELECT id, state, scheduled_command, auto_restart_enabled FROM streams LIMIT 5;
```

### **Step 3: Start PM2 Workers**

```bash
# Stop existing PM2 workers
pm2 stop all

# Start all workers with new configuration
pm2 start ecosystem.config.js

# Save PM2 configuration
pm2 save

# View worker status
pm2 status

# View logs
pm2 logs
```

**Expected Output:**
```
┌─────┬────────────────────────────┬─────────────┬─────────┬─────────┬──────────┬────────┬──────┬───────────┬──────────┬──────────┬──────────┬──────────┐
│ id  │ name                       │ namespace   │ version │ mode    │ pid      │ uptime │ ↺    │ status    │ cpu      │ mem      │ user     │ watching │
├─────┼────────────────────────────┼─────────────┼─────────┼─────────┼──────────┼────────┼──────┼───────────┼──────────┼──────────┼──────────┼──────────┤
│ 0   │ stream-import-worker       │ default     │ N/A     │ fork    │ 12345    │ 2m     │ 0    │ online    │ 0%       │ 45.2 MB  │ casapu   │ disabled │
│ 1   │ ffprobe-worker             │ default     │ N/A     │ cluster │ 12346    │ 2m     │ 0    │ online    │ 0.5%     │ 52.4 MB  │ casapu   │ disabled │
│ 2   │ stream-manager-worker      │ default     │ N/A     │ fork    │ 12347    │ 2m     │ 0    │ online    │ 0%       │ 38.1 MB  │ casapu   │ disabled │
│ 3   │ stream-monitor-worker      │ default     │ N/A     │ fork    │ 12348    │ 2m     │ 0    │ online    │ 0%       │ 35.7 MB  │ casapu   │ disabled │
│ 4   │ website-health-worker      │ default     │ N/A     │ fork    │ 12349    │ 2m     │ 0    │ online    │ 0%       │ 32.3 MB  │ casapu   │ disabled │
└─────┴────────────────────────────┴─────────────┴─────────┴─────────┴──────────┴────────┴──────┴───────────┴──────────┴──────────┴──────────┴──────────┘
```

### **Step 4: Verify Workers Are Running**

```bash
# Check stream-manager worker logs
pm2 logs stream-manager-worker --lines 20

# Check stream-monitor worker logs
pm2 logs stream-monitor-worker --lines 20

# Check website-health worker logs
pm2 logs website-health-worker --lines 20
```

**Expected Log Output:**
```
[2025-11-23 10:30:00] [INFO] [stream-manager] Stream Manager Worker started
[2025-11-23 10:30:00] [INFO] [stream-manager] Poll interval: 5000ms
[2025-11-23 10:30:05] [DEBUG] [stream-manager] Checking for queued commands...
[2025-11-23 10:30:05] [DEBUG] [stream-manager] No commands to process

[2025-11-23 10:30:00] [INFO] [stream-monitor] Stream Monitor Worker started
[2025-11-23 10:30:00] [INFO] [stream-monitor] Poll interval: 10000ms
[2025-11-23 10:30:10] [DEBUG] [stream-monitor] Monitoring streams...
[2025-11-23 10:30:10] [DEBUG] [stream-monitor] No streams to monitor

[2025-11-23 10:30:00] [INFO] [website-health] Website Health Worker started
[2025-11-23 10:30:00] [INFO] [website-health] Poll interval: 30000ms
[2025-11-23 10:30:30] [INFO] [website-health] Checked 6 components: 6 healthy, 0 warning, 0 critical
```

---

## 🧪 **Testing the Implementation**

### **Test 1: Stream State Machine**

```php
// In PHP console or create test script
$stream = Stream::find(1);

// Test queueing a start command
$stream->queueCommand('start', 1); // 1 = admin ID
echo "Queued: " . $stream->scheduled_command . "\n";

// Wait 5-10 seconds for stream-manager to process
sleep(10);

// Reload stream
$stream = Stream::find(1);
echo "State: " . $stream->state . "\n";
echo "PID: " . $stream->pid . "\n";
```

### **Test 2: Auto-Restart on Crash**

```php
// Simulate a crash by killing the PID
$stream = Stream::where('state', 'running')->first();

if ($stream && $stream->pid) {
    echo "Killing PID: " . $stream->pid . "\n";
    shell_exec("kill -9 " . $stream->pid);

    // Wait for stream-monitor to detect crash (10s poll + processing)
    sleep(15);

    // Reload stream
    $stream = Stream::find($stream->id);
    echo "State after crash: " . $stream->state . "\n";
    echo "Restart attempts: " . $stream->restart_attempts . "\n";
    echo "Scheduled command: " . $stream->scheduled_command . "\n";
}
```

### **Test 3: Health Monitoring**

```sql
-- Check stream health logs
SELECT * FROM stream_health_logs
ORDER BY checked_at DESC
LIMIT 10;

-- Check website health logs
SELECT component, status, message, checked_at
FROM website_health_logs
ORDER BY checked_at DESC
LIMIT 10;

-- Check crash statistics
SELECT stream_id, COUNT(*) as crash_count
FROM stream_health_logs
WHERE check_type = 'pid_check'
  AND status = 'failed'
  AND checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY stream_id
ORDER BY crash_count DESC;
```

### **Test 4: PM2 Manager UI**

1. Navigate to `/admin#/settings`
2. Scroll to "PM2 Background Workers" section
3. Verify 5 workers are displayed:
   - stream-import-worker
   - ffprobe-worker
   - **stream-manager-worker** (NEW)
   - **stream-monitor-worker** (NEW)
   - **website-health-worker** (NEW)
4. Click chevron to expand worker details
5. Verify configuration is displayed correctly

---

## 📊 **Monitoring & Maintenance**

### **PM2 Commands**

```bash
# View worker status
pm2 status

# View logs (all workers)
pm2 logs

# View specific worker logs
pm2 logs stream-manager-worker
pm2 logs stream-monitor-worker
pm2 logs website-health-worker

# Restart a worker
pm2 restart stream-manager-worker

# Stop a worker
pm2 stop stream-manager-worker

# View worker details
pm2 describe stream-manager-worker

# Monitor resources in real-time
pm2 monit
```

### **Database Queries for Monitoring**

```sql
-- Active streams by state
SELECT state, COUNT(*) as count
FROM streams
GROUP BY state;

-- Streams with pending commands
SELECT id, name, scheduled_command, command_queued_at
FROM streams
WHERE scheduled_command != 'none'
ORDER BY command_queued_at;

-- Recent crashes
SELECT s.id, s.name, s.crash_count, s.last_crash_at, s.restart_attempts
FROM streams s
WHERE s.crash_count > 0
ORDER BY s.last_crash_at DESC
LIMIT 10;

-- System health status
SELECT component, status, metric_value, message, checked_at
FROM website_health_logs w1
WHERE checked_at = (
    SELECT MAX(checked_at)
    FROM website_health_logs w2
    WHERE w2.component = w1.component
)
ORDER BY component;
```

### **Log Files**

Worker logs are stored in `/storage/logs/`:
- `pm2-stream-manager-error.log`
- `pm2-stream-manager-out.log`
- `pm2-stream-manager-combined.log`
- `pm2-stream-monitor-error.log`
- `pm2-stream-monitor-out.log`
- `pm2-stream-monitor-combined.log`
- `pm2-website-health-error.log`
- `pm2-website-health-out.log`
- `pm2-website-health-combined.log`

```bash
# Tail all worker logs
tail -f storage/logs/pm2-stream-manager-combined.log
tail -f storage/logs/pm2-stream-monitor-combined.log
tail -f storage/logs/pm2-website-health-combined.log
```

---

## ⚙️ **Configuration**

### **Worker Poll Intervals**

Adjust poll intervals in worker `.js` files:

```javascript
// workers/stream-manager-worker.js
const POLL_INTERVAL = 5000; // 5 seconds (default)

// workers/stream-monitor-worker.js
const POLL_INTERVAL = 10000; // 10 seconds (default)

// workers/website-health-worker.js
const POLL_INTERVAL = 30000; // 30 seconds (default)
```

### **Auto-Restart Configuration**

Configure auto-restart per stream:

```php
$stream = Stream::find(1);
$stream->auto_restart_enabled = 1; // Enable auto-restart
$stream->max_restart_attempts = 3; // Max 3 restart attempts
$stream->save();
```

### **Log Levels**

Change log verbosity in `ecosystem.config.js`:

```javascript
env: {
    NODE_ENV: 'production',
    LOG_LEVEL: 'warn' // Options: debug, info, warn, error
}
```

---

## 🐛 **Troubleshooting**

### **Worker Not Starting**

```bash
# Check PM2 logs
pm2 logs stream-manager-worker --err

# Check if PHP CLI is available
which php

# Check if worker script is executable
ls -la workers/stream-manager-worker.js

# Test worker manually
node workers/stream-manager-worker.js
```

### **Database Connection Issues**

```bash
# Verify config.php is loading correctly
php -r "require 'config.php'; echo 'Config loaded successfully';"

# Check database connection
php -r "require 'config.php'; var_dump(DB::connection()->getPdo());"
```

### **Streams Not Starting**

```sql
-- Check stream state
SELECT id, name, state, scheduled_command, last_command_result
FROM streams
WHERE id = 1;

-- Check recent health logs
SELECT * FROM stream_health_logs
WHERE stream_id = 1
ORDER BY checked_at DESC
LIMIT 5;
```

---

## 🎯 **Next Steps**

1. ✅ **Testing**: Thoroughly test all worker functionality
2. **UI Integration**: Create stream control buttons in Streams table
3. **Dashboard**: Add health monitoring dashboard
4. **Alerts**: Implement email/webhook alerts for critical events
5. **Documentation**: Update user guide with new features

---

## 📚 **Related Documentation**

- **Analysis**: [/docs/PM2_STREAM_MANAGEMENT_ANALYSIS.md](PM2_STREAM_MANAGEMENT_ANALYSIS.md)
- **PM2 Management UI**: [/docs/PM2_MANAGEMENT_UI.md](PM2_MANAGEMENT_UI.md)
- **PM2 Workers Guide**: [/docs/guides/PM2_BACKGROUND_WORKERS_GUIDE.md](guides/PM2_BACKGROUND_WORKERS_GUIDE.md)

---

**Implementation Complete** ✅
**Status**: Ready for Production Testing
**Version**: v70.0.0

