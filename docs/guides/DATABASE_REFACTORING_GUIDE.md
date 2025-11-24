# Database Refactoring Guide - Subscribers & Subscriptions

**Date**: 2025-11-24
**Version**: v70.6
**Status**: ✅ Complete - Ready for Deployment

---

## Overview

This guide documents the comprehensive refactoring of the database schema to properly separate concerns between:
- **Subscribers** (customers/users)
- **Subscriptions** (active service contracts)
- **Packages** (service tier definitions)

### Key Changes

1. **`users` table renamed to `subscribers`**
   - Removed: `isp`, `package`, `expiration_date`, `max_connections`, `is_reseller`
   - Subscribers are now clean customer records with contact information only

2. **`subscriptions` table enhanced**
   - Added: `max_concurrent_connections`, `current_connections`, `device_fingerprint`, `last_ip_address`
   - Moved from subscribers: `isp`, `user_agent`, `expire_date`
   - Subscriptions now manage all service-related data

3. **`packages` table updated**
   - Renamed: `max_connections` → `max_concurrent_devices`
   - Added: `bandwidth_limit_mbps`, `video_quality`, `allow_recording`, `allow_timeshifting`, `features`

---

## Migration Files Created

All migrations are Laravel-style and located in `database/migrations/laravel/`:

1. **`2025_11_24_000001_refactor_users_to_subscribers.php`**
   - Renames `users` table to `subscribers`
   - Removes redundant columns
   - Updates foreign keys in related tables

2. **`2025_11_24_000002_update_subscriptions_table.php`**
   - Adds new connection tracking fields
   - Adds device security fields
   - Migrates data from old structure

3. **`2025_11_24_000003_update_packages_table.php`**
   - Renames max_connections to max_concurrent_devices
   - Adds quality of service fields
   - Sets default values for existing packages

---

## Seeders Created

All seeders are located in `database/seeders/`:

1. **`PackagesSeeder.php`** - Seeds Basic, Standard, Premium, Enterprise packages
2. **`BouquetsSeeder.php`** - Seeds 8 default bouquets (Sports, Movies, News, etc.)
3. **`PackageBouquetSeeder.php`** - Links packages to bouquets
4. **`PM2WorkersSeeder.php`** - Seeds 5 PM2 worker configurations
5. **`SettingsSeeder.php`** - Seeds default system settings

---

## Models Updated

### Subscriber Model (`models/Subscriber.php`)
```php
protected $table = 'subscribers';  // Changed from 'users'
protected $fillable = [
    'username', 'password', 'email', 'phone',
    'country', 'city', 'address', 'postal_code',
    'notes', 'enabled'
];
// Removed: isp, package, max_connections, is_reseller, expiration_date
```

### User Model (`models/User.php`)
```php
// Deprecated - extends Subscriber for backward compatibility
class User extends Subscriber {
    // No additional properties
}
```

### Subscription Model (`models/Subscription.php`)
```php
protected $fillable = [
    'subscriber_id', 'package_id',
    'device', 'device_mac', 'device_fingerprint',
    'ip_address', 'last_ip_address', 'isp', 'user_agent',
    'last_connected', 'connection_count',
    'max_concurrent_connections', 'current_connections',  // NEW
    'expire_date', 'is_active', 'auto_renew', 'notes'
];
```

### Package Model (`models/Package.php`)
```php
protected $fillable = [
    'name', 'description',
    'max_concurrent_devices',  // Renamed from max_connections
    'bandwidth_limit_mbps',     // NEW
    'video_quality',            // NEW
    'allow_recording',          // NEW
    'allow_timeshifting',       // NEW
    'features',                 // NEW (JSON)
    'price', 'duration_days', 'is_active'
];
```

---

## API Endpoints Updated

### Deleted
- ❌ `public/admin/api/users.php` (replaced by subscribers.php)

### Updated
- ✅ `public/admin/api/subscribers.php`
  - Removed: isp, package, max_connections, expiration_date, is_reseller fields

- ✅ `public/admin/api/subscriptions.php`
  - Added: max_concurrent_connections, current_connections, device_fingerprint, last_ip_address

- ⚠️  `public/admin/api/packages.php`
  - Needs update: Change max_connections to max_concurrent_devices
  - Add new fields to API responses

---

## Deployment Steps

### 1. Backup Database
```bash
# Create backup before migration
mysqldump -u root -p fos_streaming > backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Run Migrations
```bash
cd /home/casapu/projects/FOS-Streaming-v69
php database/migrate.php
```

Expected output:
```
=== Running Migrations ===

→ Running: 2025_11_24_000001_refactor_users_to_subscribers
✓ Completed: 2025_11_24_000001_refactor_users_to_subscribers

→ Running: 2025_11_24_000002_update_subscriptions_table
✓ Completed: 2025_11_24_000002_update_subscriptions_table

→ Running: 2025_11_24_000003_update_packages_table
✓ Completed: 2025_11_24_000003_update_packages_table

✓ Successfully ran 3 migration(s)
```

### 3. Run Seeders
```bash
php database/seed.php
```

Expected output:
```
=== Running Database Seeders ===

→ Seeding packages...
  ✓ Packages seeded successfully!

→ Seeding bouquets...
  ✓ Bouquets seeded successfully!

→ Seeding package-bouquet relationships...
  ✓ Package-Bouquet relationships seeded successfully!

→ Seeding PM2 workers...
  ✓ PM2 workers seeded successfully!

→ Seeding settings...
  ✓ Settings seeded successfully!

✓ All seeders completed successfully!
```

### 4. Rebuild Autoloader
```bash
composer dump-autoload
```

### 5. Rebuild Frontend
```bash
npm run build
```

### 6. Restart Services
```bash
# Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# Restart Nginx
sudo systemctl restart nginx

# Restart PM2 workers
npm run pm2:restart
```

---

## Testing Checklist

After deployment, verify:

- [ ] **Subscribers Management**
  - [ ] Can view subscribers list
  - [ ] Can create new subscriber (without old fields)
  - [ ] Can edit subscriber
  - [ ] Can delete subscriber
  - [ ] Old fields (isp, package, is_reseller) are not shown

- [ ] **Subscriptions Management**
  - [ ] Can view subscriptions list
  - [ ] Can create subscription with new fields
  - [ ] Can edit subscription
  - [ ] New fields are displayed: max_concurrent_connections, current_connections
  - [ ] Device security fields work: device_fingerprint, last_ip_address

- [ ] **Packages Management**
  - [ ] Can view packages list
  - [ ] Packages show new fields: max_concurrent_devices, bandwidth_limit_mbps, video_quality
  - [ ] Can edit package with new features
  - [ ] Package-bouquet relationships are intact

- [ ] **PM2 Workers**
  - [ ] PM2 workers are automatically loaded in Settings → PM2 Manager
  - [ ] All 5 workers appear in the list
  - [ ] Workers can be started/stopped from UI

- [ ] **Database Integrity**
  - [ ] Foreign keys are working correctly
  - [ ] No orphaned records
  - [ ] All relationships work (subscribers → subscriptions → packages → bouquets)

---

## Rollback Procedure

If issues arise, rollback using:

```bash
# Restore from backup
mysql -u root -p fos_streaming < backup_YYYYMMDD_HHMMSS.sql

# Or run migration down() methods manually
```

---

## Frontend Updates Required

The following Vue.js components will need updates to work with the new schema:

### Priority 1 (Critical)
1. **`resources/js/views/Subscribers/SubscribersList.vue`**
   - Remove fields: isp, package, max_connections, is_reseller

2. **`resources/js/views/Subscriptions/SubscriptionsList.vue`**
   - Add fields: max_concurrent_connections, current_connections, device_fingerprint

3. **`resources/js/views/Packages/PackagesList.vue`**
   - Update: max_connections → max_concurrent_devices
   - Add new fields: bandwidth_limit_mbps, video_quality, allow_recording, allow_timeshifting

### Priority 2 (Enhancement)
4. **`resources/js/services/api.js`**
   - Remove usersAPI (if exists)
   - Update subscribersAPI endpoints
   - Update subscriptionsAPI with new fields
   - Update packagesAPI with new fields

5. **`resources/js/router/index.js`**
   - Remove /users routes (if exists)
   - Ensure /subscribers routes exist

---

## Breaking Changes

⚠️ **IMPORTANT**: No backward compatibility for these changes:

1. **API Endpoint Removed**:
   - `public/admin/api/users.php` → Use `subscribers.php` instead

2. **Database Table Renamed**:
   - `users` → `subscribers`

3. **Column Names Changed**:
   - Packages: `max_connections` → `max_concurrent_devices`

4. **Removed Fields**:
   - Subscribers: `isp`, `package`, `max_connections`, `expiration_date`, `is_reseller`
   - These fields now belong to subscriptions or have been removed entirely

---

## Success Criteria

✅ Migration is successful when:

1. All 3 migrations run without errors
2. All 5 seeders populate data correctly
3. Subscribers can be created/edited without old fields
4. Subscriptions include all new tracking fields
5. Packages show updated naming and new features
6. PM2 workers appear automatically in Settings page
7. All API endpoints respond correctly
8. Frontend displays data without errors
9. No foreign key constraint violations
10. All relationships work correctly

---

## Support

For issues during deployment:

1. Check logs:
   - `/home/fos-streaming/fos/logs/error.log`
   - `/home/fos-streaming/fos/logs/php-fpm.log`

2. Verify database:
   ```bash
   mysql -u root -p fos_streaming
   SHOW TABLES;
   DESCRIBE subscribers;
   DESCRIBE subscriptions;
   DESCRIBE packages;
   ```

3. Check migrations table:
   ```sql
   SELECT * FROM migrations ORDER BY batch DESC;
   ```

---

**Document Version**: 1.0
**Last Updated**: 2025-11-24
**Author**: Claude Code + Development Team
