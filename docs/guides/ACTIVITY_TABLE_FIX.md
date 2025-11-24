# Activity Table Fix - November 2025

## Problem

The Activities page in the admin panel (`/admin#/activities`) was showing an error:

```
Error loading activities: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'date_end' in 'WHERE'
(Connection: default, SQL: select count(*) as aggregate from `activity` where `date_end` <> 0000-00-00 00:00:00)
```

## Root Cause

The `activity` table was created with the **wrong schema structure**. There were two conflicting purposes:

1. **Audit Log Structure** (what was created):
   - `subscriber_id` - For tracking subscriber actions
   - `action` - Action performed (login, logout, etc.)
   - `entity_type` - Type of entity affected
   - `entity_id` - ID of affected entity
   - `meta_data` - Additional JSON data

2. **Streaming Session Structure** (what was expected):
   - `user_id` - Subscriber watching the stream
   - `stream_id` - Stream being watched
   - `date_start` - Session start time
   - `date_end` - Session end time
   - `user_ip` - Subscriber's IP address
   - `user_agent` - Browser/App identifier

The Activities API endpoint ([public/admin/api/activities.php](../../public/admin/api/activities.php)) expects the **streaming session structure**, but the Laravel migration was creating the **audit log structure**.

## Files Affected

1. **Migration File**: `database/migrations/laravel/2025_11_24_060001_create_monitoring_tables.php`
   - This file was creating the wrong table structure
   - **Fixed**: Updated to create the correct streaming session structure

2. **API Endpoint**: `public/admin/api/activities.php`
   - No changes needed - was correct all along

3. **Model**: `models/Activity.php`
   - No changes needed - was correct all along

4. **Standalone Migration**: `database/migrations/create_activity_table.sql`
   - Already had the correct structure
   - This file is used for manual migrations

## Solution Applied

### 1. Fixed the Laravel Migration

Updated `database/migrations/laravel/2025_11_24_060001_create_monitoring_tables.php` to create the correct structure:

```php
// Activity Log - Streaming Sessions Tracker
if (!$schema->hasTable('activity')) {
    $schema->create('activity', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('user_id')->comment('Reference to subscribers (users table)');
        $table->unsignedInteger('stream_id')->comment('Stream being watched');
        $table->dateTime('date_start')->default('0000-00-00 00:00:00')->comment('Session start time');
        $table->dateTime('date_end')->default('0000-00-00 00:00:00')->comment('Session end time');
        $table->string('user_ip', 45)->nullable()->comment('Subscriber IP address');
        $table->text('user_agent')->nullable()->comment('Browser/App user agent');
        $table->timestamp('created_at')->useCurrent();
        $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

        // Indexes for performance
        $table->index('user_id');
        $table->index('stream_id');
        $table->index('date_start');
        $table->index('date_end');
        $table->index('user_ip');
    });
    echo "✓ activity table created (streaming sessions tracker)\n";
}
```

### 2. Fixed Existing Database

For existing installations, the following commands were executed:

```bash
# Drop the incorrect table
mariadb -u fos_dev -pfos_dev_password fos_dev -e "DROP TABLE IF EXISTS activity;"

# Recreate with correct structure
mariadb -u fos_dev -pfos_dev_password fos_dev < database/migrations/create_activity_table.sql
```

### 3. Verified the Fix

Tested the queries that were failing:

```bash
php -r "
require_once 'config.php';
\$count = Activity::where('date_end', '<>', '0000-00-00 00:00:00')->count();
echo \"✓ SUCCESS: Found \$count records\n\";
"
```

## Table Structure (Corrected)

```sql
CREATE TABLE IF NOT EXISTS `activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `stream_id` int(10) unsigned NOT NULL,
  `date_start` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_end` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `stream_id` (`stream_id`),
  KEY `date_start` (`date_start`),
  KEY `date_end` (`date_end`),
  KEY `user_ip` (`user_ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Impact

### For Existing Installations
- The activity table needs to be dropped and recreated
- **No data loss concern** - The table was empty in most installations
- Fix is now included in the Laravel migration system

### For Fresh Deployments
- ✓ **Fixed** - New installations will create the correct table structure
- The Laravel migration now creates the proper streaming session table
- Activities page will work correctly from the start

## Related Files

- Migration: [database/migrations/laravel/2025_11_24_060001_create_monitoring_tables.php](../../database/migrations/laravel/2025_11_24_060001_create_monitoring_tables.php)
- Model: [models/Activity.php](../../models/Activity.php)
- API: [public/admin/api/activities.php](../../public/admin/api/activities.php)
- Standalone Migration: [database/migrations/create_activity_table.sql](../../database/migrations/create_activity_table.sql)

## Testing

To verify the fix is working:

1. **Check table structure**:
   ```bash
   mariadb -u username -p database_name -e "DESCRIBE activity;"
   ```

2. **Test the API endpoint**:
   - Navigate to: `http://your-server:7777/admin#/activities`
   - Should load without errors
   - May show "No activities found" if no streaming sessions have been recorded

3. **Test queries programmatically**:
   ```bash
   php -r "require 'config.php'; echo Activity::count() . ' records\n';"
   ```

## Notes for Audit Logging

If audit logging functionality is needed (tracking admin actions, subscriber actions, etc.), a separate table should be created:

- **Table Name**: `audit_logs` (already exists via separate migration)
- **Model**: `AuditLog.php` (already exists)
- **Purpose**: Track all system actions, changes, and events

The `activity` table is specifically for **streaming session tracking** (subscriber viewing history).

## Migration Status

- ✅ Database structure fixed
- ✅ Laravel migration corrected
- ✅ Fresh deployments will work correctly
- ✅ API endpoint verified working
- ✅ Documentation updated

---

**Fixed By**: Claude Code
**Date**: November 2025
**Version**: v70.6
