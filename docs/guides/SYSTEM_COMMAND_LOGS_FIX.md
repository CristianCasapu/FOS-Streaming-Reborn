# System Command Logs Table Fix

**Date**: 2025-11-24
**Version**: 70.0.0
**Status**: ✅ Completed

## Overview

Fixed the missing `admin_id` column and other expected columns in the `system_command_logs` table that was causing errors when detecting FFmpeg/FFprobe paths in the Settings page.

## Problem Statement

### Error Encountered

```
Error detecting FFmpeg: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'admin_id' in 'INSERT INTO' (Connection: default, SQL: insert into `system_command_logs` (`admin_id`, `command`, `description`, `output`, `exit_code`, `execution_time`, `success`, `ip_address`, `user_agent`, `created_at`) values ...)
```

### Root Cause

The SystemCommandLog Eloquent model expected columns that didn't exist in the database table:

**Model Expected**: `admin_id`, `command`, `description`, `output`, `exit_code`, `execution_time`, `success`, `ip_address`, `user_agent`, `created_at`

**Table Had**: `executed_by`, `command`, `full_command`, `execution_type`, `output`, `error_output`, `exit_code`, `execution_time_ms`, `started_at`, `completed_at`

**Missing**: `admin_id` (critical), `description`, `execution_time` (as float), `success`, `ip_address`, `user_agent`, `created_at`

## Changes Made

### 1. Database Migration

**File**: [`database/migrations/2025-11-24_fix_system_command_logs_table.sql`](../../database/migrations/2025-11-24_fix_system_command_logs_table.sql)

Added missing columns:

#### New Columns Added
- **`admin_id`** - References `staff.id` (not `admins.id`) for tracking which staff member executed the command
- **`description`** - Human-readable command description
- **`execution_time`** - Execution time in seconds (float), calculated from `execution_time_ms`
- **`success`** - Boolean flag indicating command success (based on exit_code = 0)
- **`ip_address`** - IP address of the executor
- **`user_agent`** - Browser user agent string
- **`created_at`** - Timestamp for Eloquent compatibility

#### Data Migration
```sql
-- Copy executed_by to admin_id
UPDATE system_command_logs SET admin_id = executed_by WHERE executed_by IS NOT NULL;

-- Convert execution_time_ms to seconds
UPDATE system_command_logs SET execution_time = execution_time_ms / 1000.0 WHERE execution_time_ms IS NOT NULL;

-- Set success based on exit_code
UPDATE system_command_logs SET success = (exit_code = 0) WHERE exit_code IS NOT NULL;

-- Copy started_at to created_at
UPDATE system_command_logs SET created_at = started_at WHERE started_at IS NOT NULL;
```

#### Old Columns Preserved
- `executed_by` - Kept for backwards compatibility
- `full_command` - Full command with arguments
- `execution_type` - Execution type (manual, cron, api)
- `error_output` - Separate error output stream
- `execution_time_ms` - Original millisecond precision
- `started_at` - Command start time
- `completed_at` - Command completion time

Made nullable:
```sql
ALTER TABLE system_command_logs
  MODIFY COLUMN started_at timestamp NULL DEFAULT NULL,
  MODIFY COLUMN execution_type varchar(50) DEFAULT 'manual';
```

#### Indexes Added
```sql
idx_admin_id
idx_success
idx_created_at
idx_exit_code
```

### 2. Model Update

**File**: [`models/SystemCommandLog.php`](../../models/SystemCommandLog.php:39)

Updated relationship from `Admin` to `Staff`:

```php
/**
 * Relationship to Staff (admin user)
 */
public function staff()
{
    return $this->belongsTo(Staff::class, 'admin_id');
}

/**
 * Backwards compatibility alias for admin relationship
 */
public function admin()
{
    return $this->staff();
}
```

**Why Staff instead of Admin?**
- The platform uses `staff` table for admin users
- The `admins` table doesn't exist
- This aligns with the platform's user management structure

### 3. Test Suite

**File**: [`test_system_command_logs_fix.php`](../../test_system_command_logs_fix.php)

Comprehensive test suite with 9 tests:
1. ✅ Check table structure
2. ✅ Test SystemCommandLog model
3. ✅ Test creating a log entry
4. ✅ Test retrieving log entries
5. ✅ Test staff relationship
6. ✅ Test static logging methods
7. ✅ Test filtering by success status
8. ✅ Verify attribute casting
9. ✅ Test settings integration

## Test Results

All 9 tests passed successfully:

```
=================================
System Command Logs Fix Test
=================================

Test 1: Check system_command_logs table structure
✓ All required columns exist
  Total columns: 18

Test 2: Test SystemCommandLog model
✓ Model instantiated successfully
  Fillable attributes: 9
✓ Model has fillable attributes

Test 3: Test creating a log entry
✓ Test log entry created successfully

Test 4: Test retrieving log entries
✓ Log retrieval working

Test 5: Test staff relationship
✓ Staff relationship defined
✓ Admin relationship alias working

Test 6: Test static logging methods
✓ logSuccess() method works
✓ logFailure() method works

Test 7: Test filtering by success status
✓ Failed commands retrieval working

Test 8: Verify attribute casting
✓ Attribute casting works correctly

Test 9: Test settings integration
✓ Settings table accessible

=================================
All tests passed! ✓
=================================
```

## Usage

### Logging Commands

```php
// Log successful command
SystemCommandLog::logSuccess(
    $staffId,           // Staff member ID
    'which ffmpeg',     // Command
    'Detect FFmpeg',    // Description
    '/usr/bin/ffmpeg',  // Output
    0,                  // Exit code
    0.123               // Execution time in seconds
);

// Log failed command
SystemCommandLog::logFailure(
    $staffId,
    'invalid-command',
    'Test command',
    'Command not found',
    127,
    0.456
);
```

### Retrieving Logs

```php
// Get recent logs
$recentLogs = SystemCommandLog::getRecent(50);

// Get logs by staff member
$staffLogs = SystemCommandLog::getByAdmin($staffId, 50);

// Get failed commands
$failures = SystemCommandLog::getFailedCommands(50);

// Get all logs
$allLogs = SystemCommandLog::all();
```

### Accessing Relationships

```php
$log = SystemCommandLog::find(1);

// Get the staff member who executed the command
$staff = $log->staff;
// or
$staff = $log->admin(); // Backwards compatible alias
```

## Admin UI Functionality

The fix enables these features in the Settings page:

### FFmpeg Detection
1. Navigate to **Settings** → **FFmpeg Configuration**
2. Click **"Detect"** button
3. System will execute `which ffmpeg` command
4. Command execution is logged with:
   - Staff member who clicked detect
   - Command output (path to FFmpeg)
   - Execution time
   - Success/failure status
   - IP address and user agent

### FFprobe Detection
Same as FFmpeg detection, for `which ffprobe`

### System Commands
All system commands executed through the admin UI are now logged:
- UFW firewall commands
- fail2ban commands
- Service start/stop/restart
- Package installation
- Nginx reloads

## Migration Instructions

### For Fresh Installations

Migration runs automatically. If needed manually:

```bash
# Run migration
sudo mysql -u username -p database < database/migrations/2025-11-24_fix_system_command_logs_table.sql

# Update autoloader
composer dump-autoload -o

# Test
php test_system_command_logs_fix.php
```

### For Existing Installations

⚠️ **Warning**: This migration adds new columns and preserves old ones. No data loss.

```bash
# Backup existing logs (optional but recommended)
mysqldump -u username -p database system_command_logs > system_command_logs_backup.sql

# Run migration
sudo mysql -u username -p database < database/migrations/2025-11-24_fix_system_command_logs_table.sql

# Update autoloader
composer dump-autoload -o

# Verify
php test_system_command_logs_fix.php
```

## Verification

### 1. Check Table Structure
```bash
sudo mysql -u username -p database -e "DESCRIBE system_command_logs;"
```

Should show 18 columns including `admin_id`, `description`, `execution_time`, `success`, `ip_address`, `user_agent`, `created_at`.

### 2. Test FFmpeg Detection
1. Go to Settings page
2. Click "Detect" button for FFmpeg
3. Should successfully detect without errors
4. Check logs table to verify entry was created

### 3. Run Test Suite
```bash
php test_system_command_logs_fix.php
```

All 9 tests should pass.

## Database Schema Comparison

### Before (Original Schema)
```sql
CREATE TABLE system_command_logs (
  id bigint(20) unsigned AUTO_INCREMENT PRIMARY KEY,
  command varchar(255) NOT NULL,
  full_command text,
  executed_by int(10) unsigned,
  execution_type varchar(50) NOT NULL DEFAULT 'manual',
  output text,
  error_output text,
  exit_code int(11),
  execution_time_ms int(10) unsigned,
  started_at timestamp NOT NULL,
  completed_at timestamp
);
```

### After (Fixed Schema)
```sql
CREATE TABLE system_command_logs (
  id bigint(20) unsigned AUTO_INCREMENT PRIMARY KEY,
  admin_id int(10) unsigned,              -- NEW: References staff.id
  command varchar(255) NOT NULL,
  full_command text,
  description varchar(255),               -- NEW: Human-readable description
  executed_by int(10) unsigned,           -- PRESERVED
  execution_type varchar(50) DEFAULT 'manual', -- MADE NULLABLE
  output text,
  error_output text,
  exit_code int(11),
  execution_time float,                   -- NEW: Seconds (float)
  success tinyint(1) DEFAULT 0,           -- NEW: Boolean success flag
  ip_address varchar(45),                 -- NEW: Executor IP
  user_agent varchar(512),                -- NEW: Browser UA
  created_at timestamp,                   -- NEW: For Eloquent
  execution_time_ms int(10) unsigned,     -- PRESERVED
  started_at timestamp NULL DEFAULT NULL, -- MADE NULLABLE
  completed_at timestamp,                 -- PRESERVED

  INDEX idx_admin_id (admin_id),
  INDEX idx_success (success),
  INDEX idx_created_at (created_at),
  INDEX idx_exit_code (exit_code)
);
```

## Related Files

### Created
- `database/migrations/2025-11-24_fix_system_command_logs_table.sql`
- `test_system_command_logs_fix.php`
- `docs/guides/SYSTEM_COMMAND_LOGS_FIX.md` (this file)

### Modified
- `models/SystemCommandLog.php` - Updated relationship to reference Staff

### Unmodified (Already Compatible)
- `app/SystemCommands.php` - Already uses SystemCommandLog::logSuccess/logFailure
- `public/admin/api/settings.php` - Uses SystemCommands::execute()

## Future Enhancements

1. **Retention Policy**: Auto-delete logs older than X days
2. **Log Viewer UI**: Admin UI page to view command execution history
3. **Alerts**: Email/Slack notifications for failed commands
4. **Metrics**: Dashboard showing command success rates
5. **Audit Trail**: Full audit trail for compliance
6. **Export**: Export logs to CSV/JSON for analysis

## Support

For questions or issues:

1. Run test suite: `php test_system_command_logs_fix.php`
2. Check table: `DESCRIBE system_command_logs;`
3. Check logs: `SELECT * FROM system_command_logs ORDER BY created_at DESC LIMIT 10;`
4. Try FFmpeg detection in Settings page
5. Check model: Verify SystemCommandLog model can create logs

---

**Fix completed successfully on 2025-11-24** ✅
