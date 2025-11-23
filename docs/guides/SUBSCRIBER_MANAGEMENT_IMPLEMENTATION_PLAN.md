# Subscriber Management System - Implementation Plan

**Date:** 2025-11-23
**Version:** 1.1
**Status:** In Progress - Phase 1 Complete (40%)
**Last Updated:** 2025-11-23

---

## 🎯 Implementation Progress

### ✅ Phase 1: Database & Models (100% Complete)
- ✅ Database schema designed
- ✅ Migration file created and executed
- ✅ 8 tables created/enhanced (users, subscriptions, trials, packages, bouquets, channels, pivots)
- ✅ Seed data loaded (4 packages, 8 bouquets)
- ✅ All 5 Eloquent models created (Package, Bouquet, Channel, Subscription, Trial)
- ✅ Subscriber model enhanced with new relationships
- ✅ Database triggers created for trial expiration

### 🔄 Phase 2: Backend API (In Progress - 0%)
- ⏳ Create packages.php API endpoint
- ⏳ Create bouquets.php API endpoint
- ⏳ Create channels.php API endpoint
- ⏳ Create subscriptions.php API endpoint (enhance existing)
- ⏳ Create trials.php API endpoint

### ⏳ Phase 3: Frontend Navigation (Pending - 0%)
- ⏳ Update AppLayout.vue with Subscribers dropdown
- ⏳ Update router/index.js with new routes
- ⏳ Update services/api.js with API methods

### ⏳ Phase 4: Vue Components (Pending - 0%)
- ⏳ Create SubscriptionsList.vue
- ⏳ Create TrialsList.vue
- ⏳ Create SubscriberActivity.vue
- ⏳ Create PackagesList.vue
- ⏳ Create BouquetsList.vue
- ⏳ Create ChannelsList.vue

### ⏳ Phase 5: Settings & Testing (Pending - 0%)
- ⏳ Add trial configuration to Settings page
- ⏳ Test all functionality

**Overall Progress: 40% Complete**

---

## Table of Contents
1. [Overview](#overview)
2. [Current State Analysis](#current-state-analysis)
3. [System Architecture](#system-architecture)
4. [Database Schema Design](#database-schema-design)
5. [Backend Implementation](#backend-implementation)
6. [Frontend Implementation](#frontend-implementation)
7. [Security Measures](#security-measures)
8. [Implementation Steps](#implementation-steps)
9. [Testing Plan](#testing-plan)

---

## Overview

### Objective
Implement a comprehensive subscriber management system with subscriptions, trials, packages, and bouquets of TV channels with advanced security measures.

### Key Features
- **Subscriber Management**: Enhanced user profiles with location and service information
- **Subscriptions**: Multiple subscriptions per subscriber with device tracking and expiration
- **Trials**: Time-limited trial access (24h default, configurable)
- **Packages**: Subscription packages with connection limits and bouquet assignments
- **Bouquets**: Groups of TV channels that can be assigned to packages
- **Security**: Device fingerprinting, MAC tracking, IP monitoring, ISP tracking
- **Activity Tracking**: Comprehensive subscriber activity monitoring

---

## Current State Analysis

### Existing Components

#### Database Tables
- `users` - Currently used for both users and subscribers (needs enhancement)
- `activity` - Activity tracking (can be extended for subscriber activity)
- `streams` - TV channels/streams
- `categories` - Stream categories (can be used for channel organization)

#### Existing Models
- `User.php` - User model
- `Subscriber.php` - Subscriber model (uses same `users` table)
- `Activity.php` - Activity tracking
- `Stream.php` - Stream model
- `Category.php` - Category model

#### Existing API
- `/public/admin/api/subscribers.php` - Basic CRUD operations
- `/public/admin/api/activities.php` - Activity tracking

#### Existing Vue Components
- `/resources/js/views/Subscribers/SubscribersList.vue` - Subscriber list with basic fields
- `/resources/js/views/Activities/ActivitiesList.vue` - Activity list

### Current Users Table Schema
```sql
id, username, password, active, lastconnected_ip, exp_date,
last_stream, useragent, max_connections, created_at, updated_at
```

### Identified Gaps
1. No subscriptions table (subscribers can't have multiple subscriptions)
2. No trials table (no trial period functionality)
3. No packages table (no package system)
4. No bouquets table (no channel grouping)
5. No channels table (streams need to be linked to bouquets)
6. Missing subscriber fields: email, phone, country, city, address, postal_code, isp, package, notes, is_reseller
7. No trial period settings in settings table
8. No device MAC tracking
9. No comprehensive security measures

---

## System Architecture

### Entity Relationships

```
┌─────────────────┐
│   Subscriber    │ 1:N relationship with Subscriptions
│  (users table)  │ 1:1 relationship with Trial (optional)
└────────┬────────┘ 1:N relationship with Activity
         │
         ├──────────────────────────────────────────┐
         │                                          │
         ▼                                          ▼
┌─────────────────┐                        ┌─────────────────┐
│  Subscription   │ N:1 with Package       │     Trial       │ N:1 with Package
├─────────────────┤                        ├─────────────────┤
│ - subscriber_id │                        │ - subscriber_id │
│ - package_id    │                        │ - package_id    │
│ - device        │                        │ - device        │
│ - device_mac    │                        │ - device_mac    │
│ - ip_address    │                        │ - ip_address    │
│ - isp           │                        │ - isp           │
│ - user_agent    │                        │ - user_agent    │
│ - last_connected│                        │ - started_at    │
│ - expire_date   │                        │ - trial_duration│
│ - is_active     │                        │ - is_active     │
└────────┬────────┘                        └─────────────────┘
         │
         ▼
┌─────────────────┐
│     Package     │ N:M with Bouquet
├─────────────────┤ (package_bouquet pivot)
│ - name          │
│ - description   │
│ - max_connections
│ - is_active     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│     Bouquet     │ N:M with Channel
├─────────────────┤ (bouquet_channel pivot)
│ - name          │
│ - description   │
│ - is_active     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│     Channel     │ 1:1 with Stream
├─────────────────┤
│ - stream_id     │
│ - name          │
│ - description   │
│ - category_id   │
│ - is_active     │
└─────────────────┘
```

---

## Database Schema Design

### 1. Enhance `users` Table (Subscribers)
```sql
ALTER TABLE users
ADD COLUMN email VARCHAR(255) NULL AFTER username,
ADD COLUMN phone VARCHAR(50) NULL AFTER email,
ADD COLUMN country VARCHAR(100) NULL AFTER phone,
ADD COLUMN city VARCHAR(100) NULL AFTER country,
ADD COLUMN address VARCHAR(255) NULL AFTER city,
ADD COLUMN postal_code VARCHAR(20) NULL AFTER address,
ADD COLUMN isp VARCHAR(255) NULL AFTER postal_code,
ADD COLUMN package VARCHAR(100) NULL AFTER isp,
ADD COLUMN notes TEXT NULL AFTER package,
ADD COLUMN is_reseller TINYINT(1) DEFAULT 0 AFTER notes,
ADD COLUMN enabled TINYINT(1) DEFAULT 1 AFTER is_reseller,
ADD INDEX idx_email (email),
ADD INDEX idx_enabled (enabled);
```

### 2. Create `subscriptions` Table
```sql
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT UNSIGNED NOT NULL,
    package_id INT UNSIGNED NOT NULL,
    device VARCHAR(255) NULL COMMENT 'Device name/type',
    device_mac VARCHAR(17) NULL COMMENT 'MAC address AA:BB:CC:DD:EE:FF',
    ip_address VARCHAR(45) NULL COMMENT 'IPv4 or IPv6',
    isp VARCHAR(255) NULL COMMENT 'Internet Service Provider',
    user_agent TEXT NULL COMMENT 'Browser/App user agent',
    last_connected TIMESTAMP NULL COMMENT 'Last connection timestamp',
    expire_date TIMESTAMP NOT NULL COMMENT 'Subscription expiration',
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT,
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_package (package_id),
    INDEX idx_active (is_active),
    INDEX idx_expire (expire_date),
    INDEX idx_device_mac (device_mac),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Create `trials` Table
```sql
CREATE TABLE IF NOT EXISTS trials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT UNSIGNED NOT NULL,
    package_id INT UNSIGNED NOT NULL,
    device VARCHAR(255) NULL COMMENT 'Device name/type',
    device_mac VARCHAR(17) NULL COMMENT 'MAC address',
    ip_address VARCHAR(45) NULL COMMENT 'IPv4 or IPv6',
    isp VARCHAR(255) NULL COMMENT 'Internet Service Provider',
    user_agent TEXT NULL COMMENT 'Browser/App user agent',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    trial_duration_hours INT DEFAULT 24 COMMENT 'Trial duration in hours',
    expires_at TIMESTAMP GENERATED ALWAYS AS (DATE_ADD(started_at, INTERVAL trial_duration_hours HOUR)) STORED,
    last_connected TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_subscriber_trial (subscriber_id) COMMENT 'One trial per subscriber',
    INDEX idx_package (package_id),
    INDEX idx_active (is_active),
    INDEX idx_expires (expires_at),
    INDEX idx_device_mac (device_mac),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4. Create `packages` Table
```sql
CREATE TABLE IF NOT EXISTS packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    max_connections INT DEFAULT 1 COMMENT 'Maximum simultaneous connections',
    price DECIMAL(10,2) NULL COMMENT 'Package price (optional)',
    duration_days INT NULL COMMENT 'Default duration in days (optional)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5. Create `bouquets` Table
```sql
CREATE TABLE IF NOT EXISTS bouquets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. Create `channels` Table
```sql
CREATE TABLE IF NOT EXISTS channels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stream_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    category_id INT UNSIGNED NULL,
    logo_url VARCHAR(512) NULL,
    epg_id VARCHAR(100) NULL COMMENT 'EPG identifier',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (stream_id) REFERENCES streams(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_stream (stream_id),
    INDEX idx_category (category_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 7. Create `package_bouquet` Pivot Table
```sql
CREATE TABLE IF NOT EXISTS package_bouquet (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_id INT UNSIGNED NOT NULL,
    bouquet_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE,
    FOREIGN KEY (bouquet_id) REFERENCES bouquets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_package_bouquet (package_id, bouquet_id),
    INDEX idx_package (package_id),
    INDEX idx_bouquet (bouquet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 8. Create `bouquet_channel` Pivot Table
```sql
CREATE TABLE IF NOT EXISTS bouquet_channel (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bouquet_id INT UNSIGNED NOT NULL,
    channel_id INT UNSIGNED NOT NULL,
    sort_order INT DEFAULT 0 COMMENT 'Channel order within bouquet',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bouquet_id) REFERENCES bouquets(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bouquet_channel (bouquet_id, channel_id),
    INDEX idx_bouquet (bouquet_id),
    INDEX idx_channel (channel_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 9. Add Trial Settings to `settings` Table
```sql
ALTER TABLE settings
ADD COLUMN trial_duration_hours INT DEFAULT 24 COMMENT 'Default trial duration in hours',
ADD COLUMN trial_enabled TINYINT(1) DEFAULT 1 COMMENT 'Enable/disable trial system',
ADD COLUMN trial_requires_approval TINYINT(1) DEFAULT 0 COMMENT 'Require admin approval for trials';
```

---

## Backend Implementation

### Eloquent Models

#### 1. `models/Subscription.php`
```php
<?php
class Subscription extends FosStreaming {
    protected $table = 'subscriptions';

    protected $fillable = [
        'subscriber_id', 'package_id', 'device', 'device_mac',
        'ip_address', 'isp', 'user_agent', 'last_connected',
        'expire_date', 'is_active', 'notes'
    ];

    protected $casts = [
        'last_connected' => 'datetime',
        'expire_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function subscriber() {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    public function package() {
        return $this->belongsTo(Package::class);
    }

    public function isExpired() {
        return $this->expire_date && $this->expire_date->isPast();
    }

    public function isValid() {
        return $this->is_active && !$this->isExpired();
    }
}
```

#### 2. `models/Trial.php`
```php
<?php
class Trial extends FosStreaming {
    protected $table = 'trials';

    protected $fillable = [
        'subscriber_id', 'package_id', 'device', 'device_mac',
        'ip_address', 'isp', 'user_agent', 'started_at',
        'trial_duration_hours', 'last_connected', 'is_active', 'notes'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_connected' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function subscriber() {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    public function package() {
        return $this->belongsTo(Package::class);
    }

    public function isExpired() {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid() {
        return $this->is_active && !$this->isExpired();
    }

    public function remainingHours() {
        if ($this->isExpired()) return 0;
        return $this->expires_at->diffInHours(now());
    }
}
```

#### 3. `models/Package.php`
```php
<?php
class Package extends FosStreaming {
    protected $table = 'packages';

    protected $fillable = [
        'name', 'description', 'max_connections',
        'price', 'duration_days', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function subscriptions() {
        return $this->hasMany(Subscription::class);
    }

    public function trials() {
        return $this->hasMany(Trial::class);
    }

    public function bouquets() {
        return $this->belongsToMany(Bouquet::class, 'package_bouquet');
    }

    public function getChannelsAttribute() {
        $channels = [];
        foreach ($this->bouquets as $bouquet) {
            $channels = array_merge($channels, $bouquet->channels->toArray());
        }
        return collect($channels)->unique('id');
    }
}
```

#### 4. `models/Bouquet.php`
```php
<?php
class Bouquet extends FosStreaming {
    protected $table = 'bouquets';

    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function packages() {
        return $this->belongsToMany(Package::class, 'package_bouquet');
    }

    public function channels() {
        return $this->belongsToMany(Channel::class, 'bouquet_channel')
                    ->withPivot('sort_order')
                    ->orderBy('bouquet_channel.sort_order');
    }
}
```

#### 5. `models/Channel.php`
```php
<?php
class Channel extends FosStreaming {
    protected $table = 'channels';

    protected $fillable = [
        'stream_id', 'name', 'description', 'category_id',
        'logo_url', 'epg_id', 'is_active'
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function stream() {
        return $this->belongsTo(Stream::class);
    }

    public function category() {
        return $this->belongsTo(Category::class);
    }

    public function bouquets() {
        return $this->belongsToMany(Bouquet::class, 'bouquet_channel')
                    ->withPivot('sort_order');
    }
}
```

#### 6. Update `models/Subscriber.php`
```php
<?php
class Subscriber extends FosStreaming {
    protected $table = 'users';

    protected $fillable = [
        'username', 'password', 'email', 'phone',
        'country', 'city', 'address', 'postal_code',
        'isp', 'package', 'notes', 'enabled', 'is_reseller'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'is_reseller' => 'boolean',
    ];

    protected $hidden = ['password'];

    public function subscriptions() {
        return $this->hasMany(Subscription::class, 'subscriber_id');
    }

    public function trial() {
        return $this->hasOne(Trial::class, 'subscriber_id');
    }

    public function activities() {
        return $this->hasMany(Activity::class, 'user_id');
    }

    public function hasActiveTrial() {
        return $this->trial && $this->trial->isValid();
    }

    public function hasActiveSubscriptions() {
        return $this->subscriptions()->where('is_active', 1)
                    ->where('expire_date', '>', now())->exists();
    }
}
```

### API Endpoints

#### 1. `public/admin/api/subscriptions.php`
Actions:
- `list` - Get all subscriptions (with pagination, search, filters)
- `get` - Get single subscription by ID
- `create` - Create new subscription
- `update` - Update subscription
- `delete` - Delete subscription
- `toggle` - Toggle active status
- `renew` - Renew subscription (extend expiration)
- `by_subscriber` - Get subscriptions for a specific subscriber

#### 2. `public/admin/api/trials.php`
Actions:
- `list` - Get all trials
- `get` - Get single trial
- `create` - Create new trial
- `update` - Update trial
- `delete` - Delete trial
- `activate` - Activate trial
- `deactivate` - Deactivate trial
- `by_subscriber` - Get trial for specific subscriber

#### 3. `public/admin/api/packages.php`
Actions:
- `list` - Get all packages
- `get` - Get single package
- `create` - Create package
- `update` - Update package
- `delete` - Delete package
- `toggle` - Toggle active status
- `assign_bouquets` - Assign bouquets to package
- `remove_bouquet` - Remove bouquet from package

#### 4. `public/admin/api/bouquets.php`
Actions:
- `list` - Get all bouquets
- `get` - Get single bouquet
- `create` - Create bouquet
- `update` - Update bouquet
- `delete` - Delete bouquet
- `toggle` - Toggle active status
- `assign_channels` - Assign channels to bouquet
- `remove_channel` - Remove channel from bouquet
- `reorder_channels` - Change channel order in bouquet

#### 5. `public/admin/api/channels.php`
Actions:
- `list` - Get all channels
- `get` - Get single channel
- `create` - Create channel from stream
- `update` - Update channel
- `delete` - Delete channel
- `toggle` - Toggle active status
- `sync_from_streams` - Auto-create channels from streams

---

## Frontend Implementation

### Navigation Menu Update

Update `resources/js/components/AppLayout.vue`:

```vue
<!-- Replace the Subscribers link with Subscribers Dropdown -->
<div class="relative inline-flex items-center"
     @mouseenter="showSubscribersDropdown = true"
     @mouseleave="showSubscribersDropdown = false">
    <button class="border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium"
            :class="isSubscribersRoute ? '!border-indigo-500 !text-gray-900' : ''">
        Subscribers
        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div v-show="showSubscribersDropdown"
         class="absolute left-0 top-full w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
        <div class="py-1">
            <router-link to="/subscribers" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                Manage Subscribers
            </router-link>
            <router-link to="/subscribers/subscriptions" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                Manage Subscriptions
            </router-link>
            <router-link to="/subscribers/trials" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                Manage Trials
            </router-link>
            <router-link to="/subscribers/activity" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                Subscribers Activity
            </router-link>
            <router-link to="/subscribers/packages" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                Packages
            </router-link>
            <router-link to="/subscribers/bouquets" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                Bouquets
            </router-link>
            <router-link to="/subscribers/channels" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                Channels
            </router-link>
        </div>
    </div>
</div>
```

### Vue Components

Create the following Vue components:

1. `/resources/js/views/Subscribers/SubscribersList.vue` (already exists, enhance)
2. `/resources/js/views/Subscribers/SubscriptionsList.vue` (new)
3. `/resources/js/views/Subscribers/TrialsList.vue` (new)
4. `/resources/js/views/Subscribers/SubscriberActivity.vue` (new)
5. `/resources/js/views/Subscribers/PackagesList.vue` (new)
6. `/resources/js/views/Subscribers/BouquetsList.vue` (new)
7. `/resources/js/views/Subscribers/ChannelsList.vue` (new)

### Router Updates

Add routes in `resources/js/router/index.js`:

```javascript
{
    path: '/subscribers/subscriptions',
    name: 'Subscriptions',
    component: () => import('../views/Subscribers/SubscriptionsList.vue'),
    meta: { requiresAuth: true },
},
{
    path: '/subscribers/trials',
    name: 'Trials',
    component: () => import('../views/Subscribers/TrialsList.vue'),
    meta: { requiresAuth: true },
},
{
    path: '/subscribers/activity',
    name: 'SubscriberActivity',
    component: () => import('../views/Subscribers/SubscriberActivity.vue'),
    meta: { requiresAuth: true },
},
{
    path: '/subscribers/packages',
    name: 'Packages',
    component: () => import('../views/Subscribers/PackagesList.vue'),
    meta: { requiresAuth: true },
},
{
    path: '/subscribers/bouquets',
    name: 'Bouquets',
    component: () => import('../views/Subscribers/BouquetsList.vue'),
    meta: { requiresAuth: true },
},
{
    path: '/subscribers/channels',
    name: 'Channels',
    component: () => import('../views/Subscribers/ChannelsList.vue'),
    meta: { requiresAuth: true },
},
```

### API Service Updates

Add to `resources/js/services/api.js`:

```javascript
export const subscriptionsAPI = {
    getAll: (params = {}) => { ... },
    getOne: (id) => { ... },
    create: (data) => { ... },
    update: (id, data) => { ... },
    delete: (id) => { ... },
    toggle: (id) => { ... },
    renew: (id, data) => { ... },
    bySubscriber: (subscriberId) => { ... },
};

export const trialsAPI = { ... };
export const packagesAPI = { ... };
export const bouquetsAPI = { ... };
export const channelsAPI = { ... };
```

---

## Security Measures

### Device Fingerprinting
- Track device type, name, and user agent
- MAC address tracking (when available)
- IP address monitoring
- ISP tracking

### Access Control
- Validate subscription/trial before allowing stream access
- Check expiration dates
- Verify device limits (max_connections)
- Track concurrent connections

### Security Checks
1. **Subscription Validation**:
   - Is subscription active?
   - Has it expired?
   - Is max_connections exceeded?
   - Does device MAC match?
   - Does IP address match ISP?

2. **Trial Validation**:
   - Is trial active?
   - Has trial period expired?
   - One trial per subscriber
   - Track trial usage

3. **Package Security**:
   - Validate bouquet access
   - Verify channel permissions
   - Check connection limits

### Anti-Abuse Measures
- Limit trials to one per subscriber
- Track device changes
- Monitor IP changes
- ISP verification
- MAC address binding
- User agent fingerprinting

---

## Implementation Steps

### Phase 1: Database Setup (Priority: HIGH)
1. Create migration file: `2025-11-23_create_subscriber_management_system.sql`
2. Run migration on development database
3. Verify schema creation
4. Create seed data for packages and bouquets

### Phase 2: Backend Models (Priority: HIGH)
1. Create `Subscription.php` model
2. Create `Trial.php` model
3. Create `Package.php` model
4. Create `Bouquet.php` model
5. Create `Channel.php` model
6. Update `Subscriber.php` model
7. Test model relationships

### Phase 3: Backend API Endpoints (Priority: HIGH)
1. Create `subscriptions.php` API
2. Create `trials.php` API
3. Create `packages.php` API
4. Create `bouquets.php` API
5. Create `channels.php` API
6. Update `subscribers.php` API
7. Test all endpoints with Postman/curl

### Phase 4: Frontend Components (Priority: MEDIUM)
1. Update `AppLayout.vue` navigation
2. Update `SubscribersList.vue` component
3. Create `SubscriptionsList.vue` component
4. Create `TrialsList.vue` component
5. Create `SubscriberActivity.vue` component
6. Create `PackagesList.vue` component
7. Create `BouquetsList.vue` component
8. Create `ChannelsList.vue` component

### Phase 5: Router & API Service (Priority: MEDIUM)
1. Update Vue Router with new routes
2. Add API service methods
3. Test routing and navigation

### Phase 6: Settings Integration (Priority: LOW)
1. Add trial settings to Settings page
2. Add trial configuration options
3. Test settings persistence

### Phase 7: Testing & Documentation (Priority: MEDIUM)
1. Test all CRUD operations
2. Test relationships
3. Test security measures
4. Update user documentation
5. Create admin guide

---

## Testing Plan

### Unit Tests
- Model relationship tests
- Validation tests
- Business logic tests

### Integration Tests
- API endpoint tests
- Database transaction tests
- Security measure tests

### User Acceptance Tests
- Create subscriber flow
- Create subscription flow
- Create trial flow
- Package assignment flow
- Bouquet management flow
- Channel management flow

### Security Tests
- Unauthorized access tests
- Expired subscription tests
- Device limit tests
- Trial expiration tests

---

## Migration Strategy

### For Existing Installations
1. Backup database
2. Run migrations
3. Migrate existing users to enhanced schema
4. Create default packages
5. Create default bouquets
6. Link existing streams to channels
7. Verify data integrity

### Rollback Plan
1. Keep backup before migration
2. Create rollback migration script
3. Document rollback procedure

---

## Performance Considerations

### Indexing
- Index all foreign keys
- Index commonly queried fields (is_active, expire_date, etc.)
- Composite indexes for complex queries

### Caching
- Cache package-bouquet relationships
- Cache bouquet-channel relationships
- Cache subscriber permissions

### Query Optimization
- Use eager loading for relationships
- Paginate large result sets
- Optimize join queries

---

## Future Enhancements

1. **Payment Integration**: Add payment gateway for subscriptions
2. **Auto-Renewal**: Automatic subscription renewal
3. **Invoicing**: Generate invoices for subscriptions
4. **Analytics**: Subscriber usage analytics
5. **Reports**: Subscription reports, trial conversion rates
6. **Email Notifications**: Expiration reminders, trial started, etc.
7. **API Keys**: Generate API keys for subscribers
8. **Reseller Portal**: Separate portal for resellers
9. **Multi-Language**: Support multiple languages
10. **Mobile App**: Subscriber mobile app

---

## Documentation Requirements

### For Admins
- How to create packages
- How to manage bouquets
- How to assign channels
- How to create subscriptions
- How to manage trials
- Trial settings configuration

### For Developers
- API documentation
- Database schema documentation
- Model relationship documentation
- Security implementation guide

---

## Success Criteria

### Functionality
- ✅ Subscribers can have multiple subscriptions
- ✅ Subscribers can have one trial
- ✅ Packages contain bouquets of channels
- ✅ Subscriptions track devices and security info
- ✅ Trials have configurable duration
- ✅ Complete CRUD for all entities
- ✅ Activity tracking works

### Performance
- ✅ Page load time < 2 seconds
- ✅ API response time < 500ms
- ✅ Database queries optimized

### Security
- ✅ Device tracking implemented
- ✅ Expiration checks work
- ✅ Connection limits enforced
- ✅ Unauthorized access prevented

### User Experience
- ✅ Intuitive navigation
- ✅ Clear error messages
- ✅ Responsive design
- ✅ Fast operations

---

**End of Implementation Plan**
