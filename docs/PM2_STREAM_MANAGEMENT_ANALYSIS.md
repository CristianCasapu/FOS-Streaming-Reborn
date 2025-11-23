# PM2 Stream Management - Deep Dive Analysis

## 📊 **CURRENT ARCHITECTURE ANALYSIS**

**Date:** 2025-11-23
**Status:** Analysis Complete
**Version:** v70

---

## 🔍 **FINDINGS: Current Stream Management**

### **1. Current Implementation** (`functions.php`)

#### **Stream Start Process:**
```php
function start_stream($id) {
    // 1. Find stream from database
    $stream = Stream::find($id);
    $setting = Setting::first();

    // 2. Check if stream URL is valid with FFprobe
    $checkstreamurl = shell_exec(
        $setting->ffprobe_path .
        ' -analyzeduration 1000000 -probesize 9000000 -i "' .
        $stream->streamurl .
        '" -v quiet -print_format json -show_streams 2>&1'
    );

    // 3. Build FFmpeg command from transcode settings
    $ffmpegCommand = getTranscode($stream->id);

    // 4. Execute FFmpeg and capture PID
    $pid = shell_exec($ffmpegCommand);

    // 5. Update database
    $stream->pid = $pid;
    $stream->running = 1;
    $stream->status = 1;
    $stream->save();
}
```

#### **Stream Stop Process:**
```php
function stop_stream($id) {
    $stream = Stream::find($id);
    $setting = Setting::first();

    // 1. Check if PID exists
    if (checkPid($stream->pid)) {
        // 2. Kill process
        shell_exec("kill -9 " . $stream->pid);

        // 3. Clean up HLS files
        shell_exec(
            "/bin/rm -r /home/fos-streaming/fos/www/" .
            $setting->hlsfolder . "/" .
            $stream->id . "*"
        );
    }

    // 4. Update database
    $stream->pid = "";
    $stream->running = 0;
    $stream->status = 0;
    $stream->save();

    sleep(2); // Wait for cleanup
}
```

#### **FFmpeg Command Builder:**
```php
function getTranscode($id) {
    // Builds complex FFmpeg command with:
    // - Input stream URL
    // - Transcode settings (codec, bitrate, resolution, etc.)
    // - Output HLS segments
    // - Returns command that echoes PID: "... & echo $!"

    // Example output:
    // ffmpeg -probesize 15000000 -analyzeduration 9000000
    //   -i "http://stream.url" -user_agent "FOS-Streaming"
    //   -c copy -c:a aac -b:a 128k
    //   -bsf h264_mp4toannexb -hls_flags delete_segments
    //   -hls_time 10 -hls_list_size 8
    //   /home/fos-streaming/fos/www/hls/123_.m3u8
    //   > /dev/null 2>/dev/null & echo $!
}
```

---

## ⚠️ **CRITICAL ISSUES IDENTIFIED**

### **Issue #1: No Process Monitoring**
**Problem:** Once FFmpeg starts, there's NO monitoring to check if it crashes
**Impact:** Dead streams remain marked as "running" in database
**Solution:** Stream Monitor worker

### **Issue #2: Synchronous API Calls**
**Problem:** `start_stream()` and `stop_stream()` execute immediately via API
**Impact:**
- API request blocks until FFmpeg starts (slow)
- No queue/retry mechanism
- Race conditions possible
**Solution:** Command queue with Stream Manager worker

### **Issue #3: No State Machine**
**Problem:** Only 3 states: `running=0/1`, `status=0/1/2`
**Current States:**
- `status=0, running=0` = Stopped
- `status=1, running=1` = Running
- `status=2, running=0` = Error

**Missing States:**
- "Starting" (command queued, not yet started)
- "Stopping" (kill signal sent, waiting for cleanup)
- "Crashed" (was running, now PID doesn't exist)
- "Restarting" (stop then start sequence)

**Solution:** Proper state machine with `state` enum field

### **Issue #4: No Auto-Recovery**
**Problem:** If stream crashes, it stays crashed
**Impact:** Manual intervention required
**Solution:** Auto-restart with attempt counter

### **Issue #5: PID Management**
**Problem:** `$stream->pid` is a string, can be empty or contain garbage
**Issues:**
- No validation that PID is actually running
- Zombie processes not cleaned up
- No way to track multiple stream instances

**Solution:** Validate PID existence, use `INT UNSIGNED` for database

### **Issue #6: Hard-coded Paths**
**Problem:** `/home/fos-streaming/fos/www/` is hardcoded
**Solution:** Use `base_path()` helper and env() configuration

### **Issue #7: No Crash Analytics**
**Problem:** No logging of when/why streams crash
**Solution:** Health logs table with crash tracking

---

## 📐 **PROPOSED DATABASE SCHEMA**

### **New Fields for `streams` Table:**

```sql
ALTER TABLE streams ADD COLUMN (
  -- Process Management
  pid INT UNSIGNED NULL COMMENT 'FFmpeg process ID',

  -- Command Queue
  scheduled_command ENUM('none','start','stop','restart')
    DEFAULT 'none'
    COMMENT 'Queued command to execute',
  command_queued_at DATETIME NULL
    COMMENT 'When command was queued',
  command_queued_by INT UNSIGNED NULL
    COMMENT 'Admin/user who queued command',
  last_command_at DATETIME NULL
    COMMENT 'When last command executed',
  last_command_result VARCHAR(255) NULL
    COMMENT 'Result of last command',

  -- State Machine
  state ENUM(
    'stopped',
    'starting',
    'running',
    'stopping',
    'error',
    'crashed'
  ) DEFAULT 'stopped'
    COMMENT 'Current stream state',

  -- Auto-Restart Configuration
  auto_restart_enabled TINYINT(1) DEFAULT 1
    COMMENT 'Enable auto-restart on crash',
  restart_attempts INT DEFAULT 0
    COMMENT 'Current consecutive restart attempts',
  max_restart_attempts INT DEFAULT 3
    COMMENT 'Max auto-restart attempts before giving up',

  -- Health Tracking
  last_health_check DATETIME NULL
    COMMENT 'Last PID health check',
  health_check_failures INT DEFAULT 0
    COMMENT 'Consecutive health check failures',
  crash_count INT DEFAULT 0
    COMMENT 'Total crash count (lifetime)',
  last_crash_at DATETIME NULL
    COMMENT 'When last crash occurred',

  -- Lifecycle Timestamps
  stream_started_at DATETIME NULL
    COMMENT 'When FFmpeg process started',
  stream_stopped_at DATETIME NULL
    COMMENT 'When FFmpeg process stopped',
  current_uptime INT DEFAULT 0
    COMMENT 'Current uptime in seconds',
  total_uptime BIGINT DEFAULT 0
    COMMENT 'Total uptime in seconds (lifetime)',

  -- FFmpeg Command Tracking
  last_ffmpeg_command TEXT
    COMMENT 'Last FFmpeg command executed',
  ffmpeg_exit_code INT NULL
    COMMENT 'Last FFmpeg exit code',

  -- Stream Source Failover
  current_stream_source TINYINT DEFAULT 1
    COMMENT 'Which URL is active (1=primary, 2=backup1, 3=backup2)',
  failover_enabled TINYINT(1) DEFAULT 0
    COMMENT 'Enable automatic failover to backup URLs',

  -- Indexes
  INDEX idx_scheduled_command (scheduled_command, command_queued_at),
  INDEX idx_state (state),
  INDEX idx_pid (pid),
  INDEX idx_auto_restart (auto_restart_enabled, restart_attempts)
);
```

### **New Table: `stream_health_logs`**

```sql
CREATE TABLE stream_health_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  stream_id INT UNSIGNED NOT NULL,

  check_type ENUM(
    'pid_check',
    'url_check',
    'ffprobe_check',
    'hls_check'
  ) NOT NULL COMMENT 'Type of health check',

  status ENUM(
    'healthy',
    'unhealthy',
    'recovered',
    'failed',
    'timeout'
  ) NOT NULL COMMENT 'Check result',

  -- PID Check Details
  pid INT UNSIGNED NULL COMMENT 'Process ID checked',
  pid_exists TINYINT(1) NULL COMMENT 'Did PID exist?',
  process_cpu DECIMAL(5,2) NULL COMMENT 'CPU usage %',
  process_memory BIGINT NULL COMMENT 'Memory usage bytes',

  -- URL/Stream Check Details
  url_checked VARCHAR(1000) NULL COMMENT 'URL that was checked',
  http_status INT NULL COMMENT 'HTTP response code',
  response_time INT NULL COMMENT 'Response time in ms',

  -- Error Details
  error_type VARCHAR(100) NULL COMMENT 'Error classification',
  error_message TEXT NULL COMMENT 'Error description',
  error_details JSON NULL COMMENT 'Additional error data',

  -- Action Taken
  action_taken ENUM(
    'none',
    'restart_queued',
    'failover_triggered',
    'alert_sent',
    'marked_as_error'
  ) NULL COMMENT 'Automated action taken',

  -- Timestamps
  checked_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  -- Indexes
  INDEX idx_stream_id (stream_id),
  INDEX idx_checked_at (checked_at),
  INDEX idx_status (status),
  INDEX idx_check_type (check_type),
  FOREIGN KEY (stream_id) REFERENCES streams(id) ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Stream health monitoring logs';
```

### **New Table: `website_health_logs`**

```sql
CREATE TABLE website_health_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  component VARCHAR(50) NOT NULL COMMENT 'Component checked (nginx, mariadb, php-fpm, disk, cpu, memory, etc)',

  status ENUM('healthy','warning','critical','unknown') NOT NULL,

  -- Metric Details
  metric_name VARCHAR(100) NULL COMMENT 'Specific metric (cpu_load, disk_usage, etc)',
  metric_value VARCHAR(255) NULL COMMENT 'Current value',
  metric_unit VARCHAR(20) NULL COMMENT 'Unit (%, MB, seconds, etc)',

  -- Thresholds
  threshold_warning VARCHAR(50) NULL COMMENT 'Warning threshold',
  threshold_critical VARCHAR(50) NULL COMMENT 'Critical threshold',

  -- Status Details
  is_active TINYINT(1) NULL COMMENT 'Is service active?',
  is_enabled TINYINT(1) NULL COMMENT 'Is service enabled on boot?',
  uptime_seconds BIGINT NULL COMMENT 'Service uptime',

  -- Message & Details
  message TEXT NULL COMMENT 'Human-readable status message',
  details JSON NULL COMMENT 'Additional data',

  -- Alert Tracking
  alert_sent TINYINT(1) DEFAULT 0 COMMENT 'Was alert notification sent?',
  alert_sent_at DATETIME NULL COMMENT 'When alert was sent',

  -- Timestamps
  checked_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  -- Indexes
  INDEX idx_component (component),
  INDEX idx_status (status),
  INDEX idx_checked_at (checked_at),
  INDEX idx_metric_name (metric_name)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Website and system health monitoring logs';
```

---

## 🔄 **REVISED STATE MACHINE**

### **Stream States:**

```
┌─────────────────────────────────────────────────────────┐
│                    STREAM STATE MACHINE                 │
└─────────────────────────────────────────────────────────┘

┌──────────┐
│ STOPPED  │ ← Initial state, FFmpeg not running
└─────┬────┘
      │ scheduled_command = 'start'
      ↓
┌──────────┐
│ STARTING │ ← Command queued, waiting for manager
└─────┬────┘
      │ FFmpeg process launched, PID captured
      ↓
┌──────────┐
│ RUNNING  │ ← FFmpeg active, PID exists
└─────┬────┘
      │
      ├─→ scheduled_command = 'stop' ─→ STOPPING
      │
      ├─→ PID dies unexpectedly ─→ CRASHED
      │
      └─→ scheduled_command = 'restart' ─→ STOPPING ─→ STARTING

┌──────────┐
│ STOPPING │ ← Kill signal sent, cleaning up
└─────┬────┘
      │ Process terminated, files cleaned
      ↓
┌──────────┐
│ STOPPED  │
└──────────┘

┌──────────┐
│ CRASHED  │ ← PID died, was not stopped intentionally
└─────┬────┘
      │
      ├─→ auto_restart_enabled=1 AND
      │   restart_attempts < max
      │   ─→ scheduled_command='start' ─→ STARTING
      │
      └─→ max attempts reached ─→ ERROR

┌──────────┐
│  ERROR   │ ← Fatal error, manual intervention needed
└──────────┘
```

### **State Transition Rules:**

| From State | Action | To State | Conditions |
|-----------|--------|----------|------------|
| STOPPED | Queue start | STARTING | scheduled_command='start' |
| STARTING | FFmpeg starts | RUNNING | PID captured successfully |
| STARTING | FFmpeg fails | ERROR | Exit code != 0 |
| RUNNING | Queue stop | STOPPING | scheduled_command='stop' |
| RUNNING | PID dies | CRASHED | PID check fails |
| STOPPING | Cleanup done | STOPPED | Process killed |
| CRASHED | Auto-restart | STARTING | restart_attempts < max |
| CRASHED | Max retries | ERROR | restart_attempts >= max |
| ERROR | Queue start | STARTING | Manual intervention |

---

## 🛠️ **WORKER ARCHITECTURE**

### **Worker 1: Stream Manager**
**Purpose:** Execute queued stream commands
**Poll Interval:** 5 seconds
**Responsibilities:**
- Start streams (exec FFmpeg, capture PID)
- Stop streams (kill PID, cleanup files)
- Restart streams (stop + start)
- Update state transitions
- Clear scheduled commands

### **Worker 2: Stream Monitor**
**Purpose:** Monitor running stream health
**Poll Interval:** 10 seconds
**Responsibilities:**
- Check if PIDs are alive
- Detect crashes
- Trigger auto-restarts
- Update health metrics
- Log health checks

### **Worker 3: Website Health Monitor**
**Purpose:** Monitor system/application health
**Poll Interval:** 30 seconds
**Responsibilities:**
- Check system services (Nginx, MariaDB, PHP-FPM)
- Monitor resources (CPU, Memory, Disk)
- Check application endpoints
- Log health status
- Send alerts on critical issues

---

## ✅ **VALIDATION OF PROPOSED LOGIC**

### **Your Original Proposal:**
✅ **Streams Manager monitors scheduled_command** - CORRECT
✅ **Captures PID and stores it** - CORRECT
✅ **Streams Monitor checks PID existence** - CORRECT
✅ **Removes PID if dead, updates state** - CORRECT

### **Critical Fixes Applied:**
⚠️ **Changed:** "update scheduled_command to start" on crash
✅ **Fixed:** Only auto-restart if `restart_attempts < max_restart_attempts`
✅ **Added:** `restart_attempts` counter to prevent infinite loops
✅ **Added:** Proper state transitions (stopped → starting → running)
✅ **Added:** Crash analytics and health logging

---

## 🎯 **NEXT STEPS**

1. **Review this analysis** - Validate findings and proposed schema
2. **Create detailed worker specifications** - Define exact behavior
3. **Design API endpoints** - Stream control APIs
4. **Design UI components** - Stream controls in table
5. **Begin implementation** - Start with database migrations

---

**Analysis Complete** ✅
**Ready for:** Specification Phase
**Approved by:** Awaiting Review
