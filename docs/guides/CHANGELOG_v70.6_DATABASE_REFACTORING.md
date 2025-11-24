# Changelog - v70.6 Database Refactoring

**Release Date**: 2025-11-24
**Type**: Major Schema Refactoring
**Status**: ✅ Complete

---

## Overview

Major database refactoring to properly separate concerns between subscribers, subscriptions, and packages. This update implements proper multi-subscription architecture where subscribers can have multiple active subscriptions.

---

## 🔄 Database Schema Changes

### Tables Renamed
- ✅ `users` → `subscribers`

### Subscribers Table (`subscribers`)
**Removed Columns**:
- `isp` (moved to subscriptions)
- `package` (replaced by package relationships through subscriptions)
- `max_connections` (moved to subscriptions as `max_concurrent_connections`)
- `expiration_date` (moved to subscriptions as `expire_date`)
- `is_reseller` (removed - subscribers cannot be resellers in new model)

**Retained Columns**:
- `username`, `password`, `email`, `phone`
- `country`, `city`, `address`, `postal_code`
- `notes`, `enabled`, `created_at`, `updated_at`

### Subscriptions Table (`subscriptions`)
**New Columns**:
- `max_concurrent_connections` (default: from package)
- `current_connections` (tracks active connections)
- `device_fingerprint` (security: unique device identifier)
- `last_ip_address` (security: tracks IP changes)

**Existing Columns** (Enhanced):
- `subscriber_id`, `package_id`
- `device`, `device_mac`, `ip_address`, `isp`, `user_agent`
- `last_connected`, `connection_count`
- `expire_date`, `is_active`, `auto_renew`, `notes`

### Packages Table (`packages`)
**Renamed Columns**:
- `max_connections` → `max_concurrent_devices`

**New Columns**:
- `bandwidth_limit_mbps` (Integer, nullable - bandwidth cap in Mbps)
- `video_quality` (Enum: SD, HD, FHD, UHD - maximum quality allowed)
- `allow_recording` (Boolean - enable stream recording)
- `allow_timeshifting` (Boolean - enable pause/rewind live TV)
- `features` (JSON - additional package features)

**Default Values Set**:
- Basic: 5 Mbps, SD, no recording/timeshift
- Standard: 10 Mbps, HD, timeshift only
- Premium: 25 Mbps, FHD, recording + timeshift
- Enterprise: Unlimited bandwidth, UHD, all features

---

## 📁 Files Created

### Migrations
- `database/migrations/laravel/2025_11_24_000001_refactor_users_to_subscribers.php`
- `database/migrations/laravel/2025_11_24_000002_update_subscriptions_table.php`
- `database/migrations/laravel/2025_11_24_000003_update_packages_table.php`

### Seeders
- `database/seeders/DatabaseSeeder.php` (Main seeder)
- `database/seeders/PackagesSeeder.php`
- `database/seeders/BouquetsSeeder.php`
- `database/seeders/PackageBouquetSeeder.php`
- `database/seeders/PM2WorkersSeeder.php`
- `database/seeders/SettingsSeeder.php`

### Migration Runners
- `database/migrate.php` (Run Laravel-style migrations)
- `database/seed.php` (Run database seeders)

### Documentation
- `docs/guides/DATABASE_REFACTORING_GUIDE.md` (Complete deployment guide)
- `docs/guides/CHANGELOG_v70.6_DATABASE_REFACTORING.md` (This file)

---

## 🔧 Models Updated

### Subscriber Model (`models/Subscriber.php`)
- Changed table from `users` to `subscribers`
- Removed fillable fields: `isp`, `package`, `max_connections`, `is_reseller`
- Removed methods: `isReseller()`, `scopeResellers()`
- All relationship methods intact

### User Model (`models/User.php`)
- **Deprecated** - Now extends Subscriber for backward compatibility
- Direct all new code to use Subscriber model

### Subscription Model (`models/Subscription.php`)
- Added fillable fields: `device_fingerprint`, `last_ip_address`, `max_concurrent_connections`, `current_connections`
- Added casts for new integer fields
- All methods updated to handle new fields

### Package Model (`models/Package.php`)
- Updated fillable: `max_connections` → `max_concurrent_devices`
- Added fillable: `bandwidth_limit_mbps`, `video_quality`, `allow_recording`, `allow_timeshifting`, `features`
- Added casts: features as array, booleans for recording/timeshift

---

## 🌐 API Endpoints Updated

### Deleted
- ❌ `public/admin/api/users.php`

### Updated

#### `public/admin/api/subscribers.php`
**Removed from create/update**:
- `isp`, `package`, `max_connections`, `expiration_date`, `is_reseller`

**Retained**:
- `username`, `password`, `email`, `phone`, `country`, `city`, `address`, `postal_code`, `notes`, `enabled`

#### `public/admin/api/subscriptions.php`
**Added to responses**:
- `device_fingerprint`, `last_ip_address`
- `max_concurrent_connections`, `current_connections`

**Updated create action**:
- Auto-sets `max_concurrent_connections` from package if not provided
- Auto-sets `current_connections` to 0

**Updated update action**:
- All new fields can be updated

---

## 🎯 PM2 Workers Auto-Loading

PM2 workers are now automatically seeded and loaded into the Settings page:

1. **stream-import-worker** - Imports streams from M3U playlists
2. **ffprobe-worker** - Analyzes stream technical details (2 instances, cluster mode)
3. **stream-manager-worker** - Manages stream lifecycle
4. **stream-monitor-worker** - Health checks and logging
5. **website-health-worker** - Website uptime monitoring

All workers appear in **Settings → PM2 Background Workers** after running seeders.

---

## 💔 Breaking Changes

### ⚠️ NO BACKWARD COMPATIBILITY

1. **API Endpoint Removed**:
   ```
   DELETE: /admin/api/users.php
   USE:    /admin/api/subscribers.php instead
   ```

2. **Database Table Renamed**:
   ```
   OLD: users
   NEW: subscribers
   ```

3. **Model Changes**:
   ```php
   // DEPRECATED
   $user = User::find($id);
   $user->isp;  // No longer exists
   $user->is_reseller;  // No longer exists

   // CORRECT
   $subscriber = Subscriber::find($id);
   $subscription = $subscriber->subscriptions()->first();
   $subscription->isp;  // Now here
   ```

4. **API Request Changes**:
   ```javascript
   // BEFORE
   POST /admin/api/users.php?action=create
   {
     "username": "john",
     "isp": "Comcast",
     "package": "Premium",
     "max_connections": 5,
     "is_reseller": true
   }

   // AFTER
   POST /admin/api/subscribers.php?action=create
   {
     "username": "john"
     // isp, package, etc. removed
   }

   // Then create subscription separately
   POST /admin/api/subscriptions.php?action=create
   {
     "subscriber_id": 1,
     "package_id": 3,
     "isp": "Comcast",
     "max_concurrent_connections": 5
   }
   ```

---

## 🚀 Migration Path

### For Existing Installations

1. **Backup Database**:
   ```bash
   mysqldump -u root -p fos_streaming > backup_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Run Migrations**:
   ```bash
   cd /home/casapu/projects/FOS-Streaming-v69
   php database/migrate.php
   ```

3. **Run Seeders**:
   ```bash
   php database/seed.php
   ```

4. **Rebuild Autoloader**:
   ```bash
   composer dump-autoload
   ```

5. **Rebuild Frontend**:
   ```bash
   npm run build
   ```

6. **Restart Services**:
   ```bash
   sudo systemctl restart php8.4-fpm nginx
   npm run pm2:restart
   ```

### For New Installations

All migrations and seeders run automatically during installation. No additional steps required.

---

## ✅ Testing Completed

- [x] Migrations run without errors
- [x] Seeders populate all tables correctly
- [x] Subscriber CRUD operations work
- [x] Subscription CRUD with new fields
- [x] Package model updates correctly
- [x] Foreign keys and relationships intact
- [x] PM2 workers auto-load in settings
- [x] API endpoints return correct data
- [x] Eloquent models work with new schema

---

## 📊 Impact Assessment

### High Impact Areas
1. **Subscriber Management** - Major field removals
2. **Subscription Management** - New required fields
3. **Package Configuration** - Enhanced with QoS features

### Medium Impact Areas
1. **API Clients** - Need to update field mappings
2. **Frontend Components** - Form fields must be updated
3. **Reporting** - Queries need adjustment for new structure

### Low Impact Areas
1. **Authentication** - Subscriber login unchanged
2. **Streaming** - Stream playback unaffected
3. **Categories/Bouquets** - No changes

---

## 🎓 Key Benefits

1. **Proper Separation of Concerns**:
   - Subscribers = Customer records
   - Subscriptions = Service contracts
   - Packages = Service definitions

2. **Multi-Subscription Support**:
   - One subscriber can have multiple active subscriptions
   - Each subscription can have different packages
   - Independent expiration dates per subscription

3. **Enhanced Security**:
   - Device fingerprinting
   - IP address tracking
   - Concurrent connection limits per subscription

4. **Improved Scalability**:
   - Clean data model
   - Better indexing
   - Optimized queries

5. **Package Features**:
   - Bandwidth limits per package
   - Quality of service tiers
   - Feature flags (recording, timeshifting)

---

## 📝 Notes

- All migrations are reversible with `down()` methods
- Foreign keys are properly maintained
- Data integrity checks are in place
- PM2 workers configuration is database-driven
- Settings table updated with trial configuration

---

## 🔗 Related Documentation

- [DATABASE_REFACTORING_GUIDE.md](DATABASE_REFACTORING_GUIDE.md) - Complete deployment guide
- [SUBSCRIBER_MANAGEMENT_COMPLETED.md](SUBSCRIBER_MANAGEMENT_COMPLETED.md) - Subscriber system docs
- [PM2_BACKGROUND_WORKERS_GUIDE.md](PM2_BACKGROUND_WORKERS_GUIDE.md) - PM2 workers guide

---

**Version**: v70.6
**Status**: ✅ Production Ready
**Next Version**: v70.7 (Frontend Vue.js updates)
