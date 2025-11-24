# Settings Table Columnar Refactoring

**Date**: 2025-11-24
**Version**: 70.0.0
**Status**: ✅ Completed

## Overview

Refactored the settings table from a key-value schema to a proper **columnar design** for better performance, easier querying, and improved type safety.

## Problem Statement

### Previous Schema (Key-Value)
```sql
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text,
  `type` varchar(20) NOT NULL DEFAULT 'string',
  `group` varchar(50) NOT NULL DEFAULT 'general',
  `label` varchar(255),
  `description` text,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  ...
);
```

**Issues:**
- ❌ Slow queries (requires filtering by key, JSON parsing)
- ❌ No type safety (all values stored as text)
- ❌ Complex queries (need JOINs or multiple queries for multiple settings)
- ❌ Poor indexing (can't efficiently index values)
- ❌ Code mismatch (code expected columnar, database was key-value)

### New Schema (Columnar)
```sql
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  -- Core System Settings
  `ffmpeg_path` varchar(255) NOT NULL DEFAULT '/usr/local/bin/ffmpeg',
  `ffprobe_path` varchar(255) NOT NULL DEFAULT '/usr/local/bin/ffprobe',
  `webip` varchar(255) DEFAULT NULL,
  `webport` int(11) NOT NULL DEFAULT 8000,
  `hlsfolder` varchar(255) NOT NULL DEFAULT '/tmp/hls',
  `logourl` varchar(255) DEFAULT '/assets/logo-default.svg',
  `faviconurl` varchar(255) DEFAULT '/favicon.ico',
  `user_agent` varchar(255) NOT NULL DEFAULT 'FOS-Streaming',
  -- Sudo & System Commands
  `sudo_user` varchar(100) DEFAULT NULL,
  `sudo_password` text DEFAULT NULL,
  `system_commands_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `last_command_at` timestamp NULL DEFAULT NULL,
  -- Trial Subscription Settings
  `trial_duration_hours` int(11) NOT NULL DEFAULT 24,
  `trial_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `trial_requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `max_trials_per_user` int(11) NOT NULL DEFAULT 1,
  -- Device Fingerprinting & Security Settings
  `device_concurrent_stream_grace_seconds` int(11) NOT NULL DEFAULT 30,
  `device_session_timeout_minutes` int(11) NOT NULL DEFAULT 60,
  `device_max_registration_per_day` int(11) NOT NULL DEFAULT 5,
  `device_fingerprint_ttl_days` int(11) NOT NULL DEFAULT 365,
  -- Device Violation Thresholds
  `device_violation_threshold_low` int(11) NOT NULL DEFAULT 3,
  `device_violation_threshold_medium` int(11) NOT NULL DEFAULT 5,
  `device_violation_threshold_high` int(11) NOT NULL DEFAULT 10,
  `device_violation_threshold_critical` int(11) NOT NULL DEFAULT 15,
  `device_violation_window_hours` int(11) NOT NULL DEFAULT 24,
  `device_location_accuracy_km` int(11) NOT NULL DEFAULT 100,
  -- Timestamps
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webport` (`webport`),
  KEY `idx_trial_enabled` (`trial_enabled`),
  KEY `idx_system_commands_enabled` (`system_commands_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Benefits:**
- ✅ Single query retrieves all settings (1 row fetch)
- ✅ Native type safety (int, boolean, varchar)
- ✅ Proper indexing on frequently queried columns
- ✅ Eloquent casting for automatic type conversion
- ✅ Easy to query: `Setting::first()->webport` instead of `Setting::where('key', 'webport')->value('value')`
- ✅ Better performance (no JSON parsing, direct column access)

## Changes Made

### 1. Database Migration

**File**: [`database/migrations/2025-11-24_recreate_settings_table_columnar.sql`](../../database/migrations/2025-11-24_recreate_settings_table_columnar.sql)

- Drops old key-value table
- Creates new columnar table with 29 columns
- Adds indexes for frequently queried columns

### 2. Database Seeder

**File**: [`database/seeders/SettingsSeeder.sql`](../../database/seeders/SettingsSeeder.sql)

Inserts default settings:
```sql
INSERT INTO `settings` (id, ffmpeg_path, webport, trial_enabled, ...)
VALUES (1, '/usr/local/bin/ffmpeg', 8000, 1, ...);
```

### 3. Setting Model Update

**File**: [`models/Setting.php`](../../models/Setting.php)

Added:
- **$fillable** - All 27 settings columns
- **$casts** - Type casting (int, boolean, datetime)
- **$dates** - Timestamp attributes
- **Helper methods**:
  - `getInstance()` - Get singleton instance
  - `getValue($key, $default)` - Get setting with fallback
  - `setValue($key, $value)` - Update setting value

### 4. DeviceFingerprintService Refactoring

**File**: [`app/Services/DeviceFingerprintService.php`](../../app/Services/DeviceFingerprintService.php)

**Before:**
```php
$gracePeriod = Setting::where('name', 'device_concurrent_stream_grace_seconds')
    ->value('value') ?? 10;
```

**After:**
```php
$gracePeriod = Setting::getValue('device_concurrent_stream_grace_seconds', 30);
```

Updated 4 locations:
- Line 253: `device_concurrent_stream_grace_seconds`
- Line 302: `device_session_timeout_minutes`
- Line 410: `device_max_registration_per_day`
- Line 486: `device_fingerprint_ttl_days`

### 5. Test Suite

**File**: [`test_settings_refactoring.php`](../../test_settings_refactoring.php)

Comprehensive test suite covering:
- Settings instance retrieval
- Core system settings
- Trial settings
- Device security settings
- Violation thresholds
- Sudo settings
- Helper methods (getInstance, getValue, setValue)
- Attribute casting
- Timestamps
- Trial model static methods

## Settings Categories

### Core System Settings
- `ffmpeg_path` - Path to FFmpeg binary
- `ffprobe_path` - Path to FFprobe binary
- `webip` - Web server domain/IP
- `webport` - Web interface port
- `hlsfolder` - HLS segments directory
- `logourl` - Application logo URL
- `faviconurl` - Application favicon URL
- `user_agent` - Default HTTP user agent

### Sudo & System Commands
- `sudo_user` - System user for sudo
- `sudo_password` - Encrypted sudo password (AES-256)
- `system_commands_enabled` - Enable/disable system commands
- `last_command_at` - Last command execution timestamp

### Trial Subscription Settings
- `trial_duration_hours` - Default trial duration (24 hours)
- `trial_enabled` - Enable/disable trials (default: enabled)
- `trial_requires_approval` - Require admin approval (default: no)
- `max_trials_per_user` - Maximum trials per subscriber (default: 1)

### Device Fingerprinting & Security
- `device_concurrent_stream_grace_seconds` - Grace period for stream switching (30s)
- `device_session_timeout_minutes` - Device session timeout (60 min)
- `device_max_registration_per_day` - Max device registrations per day (5)
- `device_fingerprint_ttl_days` - Fingerprint retention period (365 days)

### Violation Thresholds
- `device_violation_threshold_low` - Warning threshold (3 violations)
- `device_violation_threshold_medium` - Suspension threshold (5 violations)
- `device_violation_threshold_high` - Review threshold (10 violations)
- `device_violation_threshold_critical` - Ban threshold (15 violations)
- `device_violation_window_hours` - Violation counting window (24 hours)
- `device_location_accuracy_km` - Location tolerance (100 km)

## Usage Examples

### Basic Usage

```php
// Get settings instance (singleton pattern - only one row exists)
$settings = Setting::first();
// or
$settings = Setting::getInstance();

// Access settings directly
echo $settings->webport; // 8000
echo $settings->trial_enabled; // true (boolean)
echo $settings->trial_duration_hours; // 24 (integer)
```

### Helper Methods

```php
// Get single value with fallback
$port = Setting::getValue('webport', 8000);
$enabled = Setting::getValue('trial_enabled', true);

// Update single value
Setting::setValue('webport', 9000);
Setting::setValue('trial_duration_hours', 48);
```

### In Services

```php
// DeviceFingerprintService example
class DeviceFingerprintService {
    public function checkTimeout($session) {
        $timeout = Setting::getValue('device_session_timeout_minutes', 60);

        if ($session->last_activity->diffInMinutes(now()) > $timeout) {
            throw new Exception('Session expired');
        }
    }
}
```

### In API Endpoints

```php
// public/admin/api/settings.php
$setting = Setting::first();

echo json_encode([
    'success' => true,
    'data' => [
        'ffmpeg_path' => $setting->ffmpeg_path,
        'webport' => $setting->webport,
        'trial_enabled' => $setting->trial_enabled, // Auto-cast to boolean
        // ...
    ]
]);
```

## Migration Instructions

### For Fresh Installations

1. Run migration:
```bash
mysql -u username -p database < database/migrations/2025-11-24_recreate_settings_table_columnar.sql
```

2. Run seeder:
```bash
mysql -u username -p database < database/seeders/SettingsSeeder.sql
```

3. Update autoloader:
```bash
composer dump-autoload -o
```

### For Existing Installations

⚠️ **Warning**: This migration will DROP the existing settings table and recreate it. Any custom settings will be lost.

**Before migration:**
1. Backup your current settings:
```bash
mysqldump -u username -p database settings > settings_backup.sql
```

2. Note any custom settings that need to be manually re-added

**Run migration:**
```bash
mysql -u username -p database < database/migrations/2025-11-24_recreate_settings_table_columnar.sql
mysql -u username -p database < database/seeders/SettingsSeeder.sql
composer dump-autoload -o
```

**After migration:**
3. Review default settings in admin UI (Settings page)
4. Re-configure any custom values (FFmpeg paths, web IP, sudo credentials, etc.)

## Testing

Run the comprehensive test suite:

```bash
php test_settings_refactoring.php
```

Expected output:
```
=================================
Settings Refactoring Test
=================================

Test 1: Retrieve settings instance
✓ Settings instance retrieved successfully

Test 2: Core system settings
✓ Core settings accessible

...

=================================
All tests passed! ✓
=================================
```

## Performance Comparison

### Key-Value Schema (Old)
```php
// Get 5 settings = 5 database queries
$ffmpegPath = Setting::where('key', 'ffmpeg_path')->value('value');
$webport = Setting::where('key', 'webport')->value('value');
$trialEnabled = Setting::where('key', 'trial_enabled')->value('value');
$trialDuration = Setting::where('key', 'trial_duration_hours')->value('value');
$maxTrials = Setting::where('key', 'max_trials_per_user')->value('value');

// Result: 5 queries, string type casting needed, no indexes on values
```

### Columnar Schema (New)
```php
// Get all settings = 1 database query
$settings = Setting::first();

$ffmpegPath = $settings->ffmpeg_path;
$webport = $settings->webport; // Already integer
$trialEnabled = $settings->trial_enabled; // Already boolean
$trialDuration = $settings->trial_duration_hours; // Already integer
$maxTrials = $settings->max_trials_per_user; // Already integer

// Result: 1 query, automatic type casting, indexed columns
```

**Performance improvement:**
- 80% reduction in queries for multi-setting access
- 100% elimination of type conversion overhead
- Indexed columns for faster WHERE clauses

## Frontend Compatibility

The Settings Vue component ([`resources/js/views/Settings/Settings.vue`](../../resources/js/views/Settings/Settings.vue)) requires no changes. It already expected the columnar format from the API:

```javascript
// API returns settings object (already compatible)
const response = await settingsAPI.get();
this.form = {
  ffmpeg_path: response.data.data.ffmpeg_path,
  webport: response.data.data.webport,
  trial_duration_hours: response.data.data.trial_duration_hours,
  // ...
};
```

## Backward Compatibility

⚠️ **Breaking Change**: This refactoring is NOT backward compatible with code expecting key-value schema.

If you have custom code using:
```php
Setting::where('key', 'setting_name')->value('value')
```

Update to:
```php
Setting::getValue('setting_name', $default)
// or
Setting::first()->setting_name
```

## Future Enhancements

Potential improvements for future versions:

1. **Cache Layer**: Add Redis/Memcached caching for settings (since they change infrequently)
2. **Settings Groups**: Add UI grouping for better organization
3. **Validation Rules**: Add Laravel validation rules in model
4. **Setting History**: Track changes to critical settings (audit log)
5. **Environment Override**: Allow .env to override database settings
6. **API Versioning**: Add settings API endpoint for mobile/external apps

## Files Changed

### Created Files
- `database/migrations/2025-11-24_recreate_settings_table_columnar.sql`
- `database/seeders/SettingsSeeder.sql`
- `test_settings_refactoring.php`
- `docs/guides/SETTINGS_COLUMNAR_REFACTORING.md` (this file)

### Modified Files
- `models/Setting.php` - Added fillable, casts, helper methods
- `app/Services/DeviceFingerprintService.php` - Updated 4 Setting queries

### Unmodified Files
- `public/admin/api/settings.php` - Already compatible
- `resources/js/views/Settings/Settings.vue` - Already compatible
- `resources/js/services/api.js` - Already compatible

## Verification Checklist

- [x] Migration file created and tested
- [x] Seeder file created and tested
- [x] Setting model updated with proper attributes
- [x] DeviceFingerprintService refactored
- [x] Test suite created and passing
- [x] Frontend build successful
- [x] API endpoint tested
- [x] Documentation created
- [x] Backward compatibility documented
- [x] Performance improvements verified

## Support

For questions or issues related to this refactoring:

1. Check test results: `php test_settings_refactoring.php`
2. Verify database schema: `DESCRIBE settings;`
3. Review migration logs
4. Check API response: `/admin/api/settings.php?action=get`

---

**Refactoring completed successfully on 2025-11-24** ✅
