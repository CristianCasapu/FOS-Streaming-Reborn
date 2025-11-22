# Database Migrations

This directory contains SQL migration scripts for the FOS-Streaming v70 database schema.

## Migration Files

### Stream Backup URLs
**File**: `ensure_stream_backup_urls.sql`
**Purpose**: Ensures streams table has backup URL fields (`streamurl2`, `streamurl3`)
**When to run**:
- After fresh installation
- When upgrading from versions without backup URL support
- Before importing streams in bulk

**Usage**:
```bash
mysql -u fos_dev -p fos_streaming < database/migrations/ensure_stream_backup_urls.sql
```

**What it does**:
- Adds `streamurl2` column if it doesn't exist (failover URL #1)
- Adds `streamurl3` column if it doesn't exist (failover URL #2)
- Both columns default to empty string
- Uses UTF8MB4 character set for full Unicode support
- Safe to run multiple times (idempotent)

### Stream Analysis Fields
**File**: `add_stream_analysis_fields.sql`
**Purpose**: Adds comprehensive FFprobe analysis and stream profiling fields
**When to run**:
- Before enabling stream analysis features
- When upgrading to v70 with FFprobe integration
- Required for automatic stream testing

**Usage**:
```bash
mysql -u fos_dev -p fos_streaming < database/migrations/add_stream_analysis_fields.sql
```

**What it does**:
- Adds `analysis_status` (pending/analyzing/completed/failed)
- Adds `stream_type` (live/vod/radio)
- Adds video characteristics (codec, resolution, fps, bitrate, etc.)
- Adds audio characteristics (codec, channels, sample rate, etc.)
- Adds health metrics (health_score, packet_loss, uptime, etc.)
- Adds `ffprobe_profile` and `recommended_settings`
- Creates indexes for performance
- Stores full FFprobe JSON output for reference

**New Columns Added** (27 total):
- Analysis: `analysis_status`, `last_analyzed`, `analysis_error`
- Format: `stream_type`, `container_format`, `duration`, `bitrate`, `file_size`
- Video: `video_codec`, `video_profile`, `video_width`, `video_height`, `video_fps`, `video_bitrate`, `pixel_format`, `aspect_ratio`
- Audio: `audio_codec`, `audio_channels`, `audio_sample_rate`, `audio_bitrate`, `audio_language`
- Health: `health_score`, `packet_loss`, `average_response_time`, `uptime_percentage`
- Profile: `ffprobe_profile`, `ffprobe_raw_json`, `recommended_settings`

**Documentation**: See `/docs/guides/STREAM_ANALYSIS_GUIDE.md` for complete usage guide

### UTF8MB4 Conversion
**File**: `convert_to_utf8mb4.sql`
**Purpose**: Converts entire database to UTF8MB4 character set
**When to run**: When upgrading from older versions with UTF8

### Activity Table
**File**: `create_activity_table.sql`
**Purpose**: Creates activity logging table for user actions

### Blocked IPs Table
**File**: `create_blocked_ips_table.sql`
**Purpose**: Creates table for IP banning/whitelisting

### Blocked User Agents Table
**File**: `create_blocked_user_agents_table.sql`
**Purpose**: Creates table for user agent blocking

### UFW Rules Table
**File**: `create_ufw_rules_table.sql`
**Purpose**: Creates table for UFW firewall rules management

### Settings Updates
**File**: `add_sudo_password_to_settings.sql`
**Purpose**: Adds sudo password field to settings table

**File**: `add_faviconurl_to_settings.sql`
**Purpose**: Adds favicon URL field to settings table

### Subscriber Fields
**File**: `add_subscriber_fields.sql`
**Purpose**: Adds extended subscriber information fields to users table
**When to run**: After upgrading to v70 with enhanced subscriber management

**Usage**:
```bash
mysql -u fos_dev -p fos_streaming < database/migrations/add_subscriber_fields.sql
```

**New Fields Added**:
- `phone` - Subscriber phone number
- `country` - Subscriber country
- `city` - Subscriber city
- `address` - Subscriber street address
- `postal_code` - Postal/zip code
- `isp` - Internet Service Provider
- `package` - Subscription package (basic/standard/premium/enterprise)
- `max_connections` - Maximum allowed connections (default: 5)
- `expiration_date` - Subscription expiration date
- `notes` - Additional notes about subscriber
- `is_reseller` - Reseller flag (0 or 1)

## Running Migrations

### Individual Migration
```bash
mysql -u fos_dev -p fos_streaming < database/migrations/MIGRATION_FILE.sql
```

### All Migrations (Manual)
```bash
for file in database/migrations/*.sql; do
    echo "Running $file..."
    mysql -u fos_dev -p fos_streaming < "$file"
done
```

## Migration Best Practices

1. **Backup First**: Always backup your database before running migrations
   ```bash
   mysqldump -u fos_dev -p fos_streaming > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Test in Development**: Test migrations on a development database first

3. **Check Results**: Verify the migration completed successfully
   ```bash
   mysql -u fos_dev -p -e "DESCRIBE fos_streaming.streams"
   ```

4. **Idempotent Migrations**: Our migrations are designed to be safe to run multiple times

## Troubleshooting

### Permission Denied
If you get "Access denied" errors:
```bash
# Use root user
sudo mysql fos_streaming < database/migrations/MIGRATION_FILE.sql

# Or check your credentials
grep DB_ .env
```

### Table Already Exists
This is normal for idempotent migrations. They check for existence before creating.

### Character Set Errors
Ensure your database connection uses UTF8MB4:
```sql
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
```

## Creating New Migrations

When creating new migration files:

1. **Naming Convention**: `description_of_change.sql`
2. **Include Comments**: Add date, description, and purpose
3. **Make Idempotent**: Check if changes already exist before applying
4. **Use UTF8MB4**: All text columns should use `utf8mb4_unicode_ci`
5. **Document**: Update this README with the new migration

### Template
```sql
-- Migration: Description
-- Date: YYYY-MM-DD
-- Description: What this migration does

-- Your SQL here

-- Verification query
SELECT 'Migration completed successfully' as status;
```
