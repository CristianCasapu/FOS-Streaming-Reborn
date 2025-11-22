# UTF8MB4 Migration Guide

## Overview

FOS-Streaming v70 now uses **UTF8MB4** character set with **utf8mb4_unicode_ci** collation across all database components. This provides full Unicode support including emojis and 4-byte characters.

## What Changed

### Before (UTF8)
```sql
CREATE DATABASE fos CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE users (
  name VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

### After (UTF8MB4) ✨
```sql
CREATE DATABASE fos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE users (
  name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Benefits of UTF8MB4

✅ **Full Unicode Support** - All languages, scripts, and symbols
✅ **Emoji Support** - 😀 🎉 ⚡ 🚀 💯
✅ **4-Byte Characters** - Chinese, Japanese, Korean extended characters
✅ **Future-Proof** - Modern MySQL/MariaDB standard
✅ **Better Compatibility** - Works with modern applications and APIs
✅ **No Data Loss** - Backward compatible with UTF8 data

## Files Modified

### 1. Environment Configuration

**[.env.example](../.env.example)** and **[.env](../.env)**
```bash
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

### 2. Configuration Files

**[config.php](../config.php)** - Lines 125-131
```php
$capsule->addConnection([
    'driver'    => env('DB_CONNECTION', 'mysql'),
    'host'      => env('DB_HOST', 'localhost'),
    'database'  => env('DB_DATABASE', 'fos_dev'),
    'username'  => env('DB_USERNAME', 'fos_dev'),
    'password'  => env('DB_PASSWORD', 'fos_dev_password'),
    'charset'   => env('DB_CHARSET', 'utf8mb4'),  // ← Updated
    'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),  // ← Updated
    'prefix'    => env('DB_PREFIX', ''),
]);
```

**[helpers.php](../helpers.php)** - Lines 73-74
```php
'database.charset' => env('DB_CHARSET', 'utf8mb4'),  // ← Updated
'database.collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),  // ← Updated
```

### 3. Installation Scripts

**[install/debian12](../install/debian12)** - Line 550
```bash
mysql -u root -p"${SQL_PASSWD}" -e "CREATE DATABASE IF NOT EXISTS fos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

**[install/debian12-enhanced](../install/debian12-enhanced)** - Lines 511, 624-626
```bash
# Database creation (line 511)
sudo mysql -u root -p"${SQL_PASSWD}" -e "CREATE DATABASE IF NOT EXISTS fos_streaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# .env file generation (lines 624-626)
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
DB_PREFIX=
```

### 4. Migration Script

**[database/migrations/convert_to_utf8mb4.sql](migrations/convert_to_utf8mb4.sql)**
- Converts existing database and all tables to UTF8MB4
- Includes verification queries
- Safe for production use

## Migration Paths

### For New Installations

✅ **No action needed!** All new installations automatically use UTF8MB4.

```bash
# Run installation script (already uses UTF8MB4)
chmod +x install/debian12-enhanced
./install/debian12-enhanced
```

The database will be created with UTF8MB4 by default.

### For Existing Installations

If you have an existing FOS-Streaming installation with UTF8, follow these steps:

#### Step 1: Backup Your Database

**CRITICAL:** Always backup before migration!

```bash
# Create timestamped backup
mysqldump -u root -p fos_streaming > fos_streaming_backup_$(date +%Y%m%d_%H%M%S).sql

# Or backup to specific location
mysqldump -u root -p fos_streaming > ~/backups/fos_streaming_before_utf8mb4.sql
```

#### Step 2: Update Configuration Files

The configuration files have already been updated. Verify they're correct:

```bash
# Check .env file
grep -E "DB_CHARSET|DB_COLLATION" .env

# Should show:
# DB_CHARSET=utf8mb4
# DB_COLLATION=utf8mb4_unicode_ci
```

If not, update manually:
```bash
sed -i 's/DB_CHARSET=utf8$/DB_CHARSET=utf8mb4/' .env
sed -i 's/DB_COLLATION=utf8_unicode_ci$/DB_COLLATION=utf8mb4_unicode_ci/' .env
```

#### Step 3: Run Migration Script

```bash
# Navigate to project directory
cd /home/fos-streaming/fos/www

# Run migration (enter MySQL root password when prompted)
mysql -u root -p fos_streaming < database/migrations/convert_to_utf8mb4.sql
```

The script will:
1. Convert database default charset
2. Convert all tables to UTF8MB4
3. Optimize tables after conversion
4. Display verification results

#### Step 4: Verify Migration

Check the migration results:

```sql
-- Connect to database
mysql -u root -p fos_streaming

-- Verify database charset
SHOW CREATE DATABASE fos_streaming;
-- Should show: CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci

-- Verify tables
SELECT table_name, table_collation
FROM information_schema.tables
WHERE table_schema = 'fos_streaming'
  AND table_type = 'BASE TABLE';
-- All should show: utf8mb4_unicode_ci

-- Verify columns
SELECT table_name, column_name, character_set_name, collation_name
FROM information_schema.columns
WHERE table_schema = 'fos_streaming'
  AND character_set_name IS NOT NULL
ORDER BY table_name, column_name;
-- All should show: utf8mb4 and utf8mb4_unicode_ci
```

#### Step 5: Restart Services

```bash
# Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# Restart Nginx
sudo systemctl restart fos-nginx

# Restart MariaDB (optional, but recommended)
sudo systemctl restart mariadb
```

#### Step 6: Test Application

1. **Test Login:** Verify you can log in to the web panel
2. **Test Streams:** Create/edit/delete a stream
3. **Test Users:** Create/edit/delete a user
4. **Test Unicode:** Try entering emojis or special characters in text fields
5. **Check Logs:** Monitor logs for any charset-related errors

```bash
# Check PHP-FPM logs
sudo tail -f /var/log/php8.4-fpm.log

# Check Nginx error logs
sudo tail -f /var/log/nginx/error.log

# Check application logs
tail -f storage/logs/laravel.log
```

## Manual Table Conversion

If you need to convert specific tables manually:

```sql
-- Convert single table
ALTER TABLE table_name
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Convert multiple tables
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE streams CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE admins CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Optimize after conversion
OPTIMIZE TABLE users, streams, admins;
```

## Troubleshooting

### Issue: "Specified key was too long"

**Error:**
```
ERROR 1071 (42000): Specified key was too long; max key length is 767 bytes
```

**Cause:** UTF8MB4 uses 4 bytes per character vs 3 bytes for UTF8. With large VARCHAR indexes, you might exceed the limit.

**Solution 1:** Use InnoDB with ROW_FORMAT=DYNAMIC (recommended)
```sql
ALTER TABLE table_name ROW_FORMAT=DYNAMIC;
ALTER TABLE table_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Solution 2:** Reduce VARCHAR length for indexed columns
```sql
-- If you have: INDEX(email) where email is VARCHAR(255)
-- Reduce to: VARCHAR(191) for indexed columns
ALTER TABLE users MODIFY email VARCHAR(191);
```

**Solution 3:** Use utf8mb4_unicode_520_ci for better sorting (requires MySQL 5.6.10+)
```sql
ALTER DATABASE fos_streaming COLLATE utf8mb4_unicode_520_ci;
```

### Issue: Existing Data Shows as ???

**Cause:** Existing data was stored in a different encoding.

**Solution:** Re-import from backup after converting
```bash
# 1. Convert database
mysql -u root -p fos_streaming < database/migrations/convert_to_utf8mb4.sql

# 2. Re-import data
mysql -u root -p fos_streaming < backup.sql
```

### Issue: Connection Charset Mismatch

**Error:** Data appears corrupted in application but looks fine in database.

**Solution:** Ensure connection uses UTF8MB4
```php
// In config.php (already done)
$capsule->addConnection([
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    // ...
]);

// For raw PDO connections
$pdo = new PDO(
    'mysql:host=localhost;dbname=fos_streaming;charset=utf8mb4',
    'user',
    'password'
);
```

### Issue: Performance Degradation

**Symptom:** Queries slower after UTF8MB4 conversion.

**Solution:**
```sql
-- 1. Rebuild indexes
ALTER TABLE table_name ENGINE=InnoDB;

-- 2. Analyze tables
ANALYZE TABLE users, streams, admins;

-- 3. Optimize tables
OPTIMIZE TABLE users, streams, admins;

-- 4. Update statistics
SHOW INDEX FROM users;
```

## Testing UTF8MB4 Support

### Test Emoji Storage

```sql
-- Insert emoji data
INSERT INTO users (name, email) VALUES ('Test User 😀', 'emoji@test.com');

-- Retrieve and verify
SELECT name FROM users WHERE email = 'emoji@test.com';
-- Should display: Test User 😀
```

### Test 4-Byte Characters

```sql
-- Chinese characters
INSERT INTO streams (name, description) VALUES ('测试流', '这是一个测试流 🎥');

-- Japanese characters with emoji
INSERT INTO categories (name) VALUES ('テストカテゴリー 📺');

-- Korean characters
INSERT INTO settings (key, value) VALUES ('한글키', '한글값 ✅');

-- Verify all stored correctly
SELECT * FROM streams WHERE name = '测试流';
SELECT * FROM categories WHERE name LIKE '%テスト%';
SELECT * FROM settings WHERE `key` = '한글키';
```

### Test Mixed Content

```php
<?php
// In your PHP application
$testData = [
    'English ABC',
    'Español ñáéíóú',
    'Français çêîô',
    'Deutsch äöüß',
    'Русский язык',
    'العربية',
    '中文汉字',
    '日本語',
    '한글',
    'Emoji 😀🎉⚡🚀💯',
    'Mixed: Hello 世界 🌍'
];

foreach ($testData as $text) {
    // Insert test
    DB::table('test')->insert(['text' => $text]);

    // Retrieve test
    $result = DB::table('test')->where('text', $text)->first();

    // Verify
    assert($result->text === $text);
}
```

## Performance Considerations

### Storage Impact

UTF8MB4 uses **up to 4 bytes per character** vs **up to 3 bytes for UTF8**.

**Impact on VARCHAR columns:**
```
UTF8:     VARCHAR(255) = max 765 bytes
UTF8MB4:  VARCHAR(255) = max 1020 bytes
```

**Recommendation:**
- For indexed VARCHAR columns, consider using VARCHAR(191) instead of VARCHAR(255)
- 191 × 4 bytes = 764 bytes (under the 767-byte limit for InnoDB)

### Index Size

Indexes on UTF8MB4 columns are larger:
```
UTF8:     INDEX on VARCHAR(100) = ~300 bytes
UTF8MB4:  INDEX on VARCHAR(100) = ~400 bytes
```

**Recommendation:**
- Use ROW_FORMAT=DYNAMIC for InnoDB tables
- Only index columns that need it
- Use prefix indexes for long VARCHAR columns: `INDEX(column(50))`

### Query Performance

UTF8MB4 collations have minimal performance impact:
- `utf8mb4_unicode_ci`: Slower, more accurate sorting
- `utf8mb4_general_ci`: Faster, less accurate sorting
- `utf8mb4_bin`: Fastest, binary comparison

**Recommendation:** Stick with `utf8mb4_unicode_ci` for best Unicode support.

## Best Practices

### 1. Always Use UTF8MB4 for New Tables

```sql
CREATE TABLE new_table (
    id INT PRIMARY KEY,
    name VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Set Connection Charset

```php
// Eloquent (already configured in config.php)
'charset'   => 'utf8mb4',
'collation' => 'utf8mb4_unicode_ci',

// PDO
$pdo->exec("SET NAMES utf8mb4");

// MySQLi
$mysqli->set_charset('utf8mb4');
```

### 3. Backup Before Migration

```bash
# Full backup
mysqldump -u root -p --all-databases > full_backup.sql

# Single database
mysqldump -u root -p fos_streaming > fos_streaming.sql

# With compression
mysqldump -u root -p fos_streaming | gzip > fos_streaming.sql.gz
```

### 4. Test in Development First

```bash
# 1. Clone production database
mysqldump -u root -p fos_streaming | mysql -u root -p fos_streaming_test

# 2. Run migration on test database
mysql -u root -p fos_streaming_test < database/migrations/convert_to_utf8mb4.sql

# 3. Test thoroughly

# 4. Apply to production
mysql -u root -p fos_streaming < database/migrations/convert_to_utf8mb4.sql
```

## Rollback Procedure

If you need to rollback to UTF8:

```sql
-- Rollback database
ALTER DATABASE fos_streaming CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- Rollback tables
ALTER TABLE users CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
ALTER TABLE streams CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;
-- ... etc

-- Optimize
OPTIMIZE TABLE users, streams;
```

Then update .env:
```bash
DB_CHARSET=utf8
DB_COLLATION=utf8_unicode_ci
```

**Note:** You may lose emoji and 4-byte character data during rollback!

## FAQ

### Q: Will this break my existing data?

**A:** No! UTF8MB4 is backward compatible with UTF8. All existing UTF8 data will work perfectly after conversion.

### Q: Do I need to update my application code?

**A:** No. The application already uses the charset from config.php, which now loads from .env.

### Q: Can I use emojis in usernames/stream names now?

**A:** Yes! After migration, you can use emojis anywhere: 😀 🎉 ⚡ 🚀 💯

### Q: What about performance?

**A:** Minimal impact. UTF8MB4 is slightly larger (4 bytes vs 3 bytes max), but the difference is negligible for most applications.

### Q: Is this required?

**A:** Recommended but not required. However, UTF8MB4 is the modern standard and prevents issues with:
- Emoji characters
- Extended Chinese/Japanese/Korean characters
- Mathematical symbols
- Ancient scripts
- Future Unicode additions

### Q: What if I only use English?

**A:** Still recommended. UTF8MB4 is the MySQL default since 8.0 and MariaDB 10.5. It's future-proof even if you only use ASCII/English today.

### Q: Can I mix UTF8 and UTF8MB4 tables?

**A:** Yes, but not recommended. Stick to one charset for consistency and to avoid conversion overhead in JOINs.

## Summary

### For New Installations
✅ Nothing to do - UTF8MB4 is automatic

### For Existing Installations
1. Backup database
2. Run migration script
3. Verify conversion
4. Restart services
5. Test application

### Benefits
- Full Unicode support
- Emoji support
- Future-proof
- Modern standard
- No breaking changes

### Files Modified
- .env and .env.example
- config.php
- helpers.php
- install/debian12
- install/debian12-enhanced

---

**Migration Status:** Complete ✅
**Last Updated:** 2025-11-22
**Database Version:** MariaDB 11.4+
**PHP Version:** 8.4+
**Character Set:** UTF8MB4
**Collation:** utf8mb4_unicode_ci
