# Channels to Streams Refactoring - Complete Guide

**Date**: 2025-11-24
**Version**: v70.6
**Status**: Backend Complete, Frontend In Progress

## Overview

This refactoring eliminates the redundant `channels` table and `bouquet_channel` pivot table. Bouquets now directly reference streams via a `stream_ids` JSON column, simplifying the architecture significantly.

---

## Changes Completed

### ✅ Database Layer

1. **Updated Migrations**
   - Modified [`database/migrations/laravel/2025_11_24_100002_create_bouquets_table.php`](../../database/migrations/laravel/2025_11_24_100002_create_bouquets_table.php)
     - Changed `channels` column to `stream_ids` (JSON)
     - Updated comments to reflect streams instead of channels

   - **Removed Files**:
     - `database/migrations/laravel/2025_11_24_100003_create_channels_table.php`
     - `database/migrations/laravel/2025_11_24_100008_create_bouquet_channel_table.php`

2. **Updated Seeders**
   - [`database/seeders/BouquetsSeeder.php`](../../database/seeders/BouquetsSeeder.php)
     - Changed `channels` column to `stream_ids`
     - Updated comments

### ✅ Backend Models

1. **Bouquet Model** ([`models/Bouquet.php`](../../models/Bouquet.php))
   - Replaced `channels()` relationship with `streams()` method
   - Uses `stream_ids` JSON array to fetch Stream models
   - New methods:
     - `streams()` - Get streams in bouquet
     - `hasStream($streamId)` - Check if stream exists
     - `addStream($streamId)` - Add stream to bouquet
     - `removeStream($streamId)` - Remove stream from bouquet
     - `setStreams($streamIds)` - Replace all streams
     - `reorderStreams($streamIds)` - Reorder streams
     - `addStreams($streamIds)` - Add multiple streams
     - `removeStreams($streamIds)` - Remove multiple streams
     - `cleanupInvalidStreamIds()` - Remove non-existent stream IDs

   - New attributes:
     - `stream_count` - Count of streams
     - `running_stream_count` - Count of running streams
     - `activeStreams` - Get only active streams
     - `streamsWithDetails` - Get streams with full details

2. **Package Model** ([`models/Package.php`](../../models/Package.php))
   - Replaced `channels` attribute with `streams`
   - Changed `channel_count` to `stream_count`
   - Added legacy alias `channel_count` for compatibility

3. **Removed Files**:
   - `models/Channel.php`

### ✅ Backend APIs

1. **Bouquets API** ([`public/admin/api/bouquets.php`](../../public/admin/api/bouquets.php))
   - Updated all endpoints to work with `stream_ids` instead of `channel_ids`
   - Changed action names:
     - `assign_channels` → `assign_streams`
     - `remove_channel` → `remove_stream`
     - `reorder_channels` → `reorder_streams`
     - Added: `add_stream`, `cleanup_invalid_streams`, `available_streams`

   - Response changes:
     - `channel_count` → `stream_count`
     - Added `running_stream_count`
     - `channels` → `streams` in detailed responses

2. **Removed Files**:
   - `public/admin/api/channels.php`

---

## Changes Needed (Frontend)

### 🔶 Vue Components

1. **BouquetsList.vue** ([`resources/js/views/Subscribers/BouquetsList.vue`](../../resources/js/views/Subscribers/BouquetsList.vue))
   - Replace `channelsAPI` import with `streamsAPI`
   - Change `channel_count` to `stream_count`
   - Update "Manage Channels" to "Manage Streams"
   - Update `manageChannels()` to `manageStreams()`
   - Change `fetchAllChannels()` to `fetchAllStreams()`
   - Update modal titles and labels
   - Change `selectedChannels` to `selectedStreams`
   - Update API calls to use `stream_ids` instead of `channel_ids`

2. **BouquetDetail.vue** ([`resources/js/views/Subscribers/BouquetDetail.vue`](../../resources/js/views/Subscribers/BouquetDetail.vue))
   - Similar changes as BouquetsList.vue
   - Update stream display logic
   - Change references from channels to streams

3. **PackagesList.vue** and **PackageDetail.vue**
   - Update `channel_count` to `stream_count`
   - Update any channel references to streams

4. **Removed Files**:
   - `resources/js/views/Subscribers/ChannelsList.vue` ✅

### 🔶 Router Configuration

**File**: [`resources/js/router/index.js`](../../resources/js/router/index.js)

Remove channel routes:
```javascript
// REMOVE THESE:
{
    path: '/streams/channels',
    name: 'ChannelsList',
    component: () => import('../views/Subscribers/ChannelsList.vue'),
    meta: { requiresAuth: true }
},
```

### 🔶 API Service

**File**: [`resources/js/services/api.js`](../../resources/js/services/api.js)

Remove channelsAPI:
```javascript
// REMOVE THIS SECTION:
export const channelsAPI = {
    getAll: (params = {}) => { ... },
    getOne: (id) => { ... },
    create: (data) => { ... },
    update: (id, data) => { ... },
    delete: (id) => { ... },
    // ...
};
```

Update bouquetsAPI if needed to use new action names.

### 🔶 Navigation Menu

**File**: [`resources/js/components/AppLayout.vue`](../../resources/js/components/AppLayout.vue)

Remove "Channels" link from Streams dropdown menu.

---

## Testing Checklist

### Database Migration Test

```bash
# 1. Backup current database (if needed)
mysqldump -u root -p fos_streaming > backup_before_refactoring.sql

# 2. Drop and recreate database for fresh install
mysql -u root -p
DROP DATABASE fos_streaming;
CREATE DATABASE fos_streaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
exit

# 3. Run migrations
php database/migrate.php

# 4. Run seeders
php database/seed.php

# 5. Verify tables
mysql -u root -p fos_streaming
SHOW TABLES;
DESC bouquets;  # Should have stream_ids column
# Should NOT see: channels, bouquet_channel tables
```

### Backend API Test

```bash
# Test bouquets API
curl -X GET "http://localhost:7777/admin/api/bouquets.php?action=list"

# Test available streams
curl -X GET "http://localhost:7777/admin/api/bouquets.php?action=available_streams"

# Test creating a bouquet with streams
curl -X POST "http://localhost:7777/admin/api/bouquets.php?action=create" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Bouquet", "description": "Test", "stream_ids": [1, 2, 3]}'
```

### Frontend Build Test

```bash
# 1. Clear cache
rm -rf node_modules/.vite

# 2. Build frontend
npm run build

# 3. Check for errors
# Look for any references to Channel, channelsAPI, etc.
grep -r "channelsAPI" resources/js/
grep -r "channel_count" resources/js/
```

### Browser Test

1. Login to admin panel: `http://localhost:7777/admin#/login`
2. Navigate to Streams → Bouquets
3. Create a new bouquet
4. Click "Manage Streams" button
5. Add/remove streams from bouquet
6. Verify stream count displays correctly
7. Check package details show correct stream count

---

## Database Schema Changes

### Before
```
streams (id, name, ...)
  ↓
channels (id, stream_id, name, ...)
  ↓
bouquet_channel (bouquet_id, channel_id, sort_order)
  ↓
bouquets (id, name, channels JSON - unused)
```

### After
```
streams (id, name, ...)
  ↓
bouquets (id, name, stream_ids JSON [1,2,3...])
```

---

## API Changes

### Bouquets API

| Old Action | New Action | Parameters Changed |
|------------|------------|-------------------|
| `assign_channels` | `assign_streams` | `channel_ids` → `stream_ids` |
| N/A | `add_stream` | NEW: `stream_id` |
| `remove_channel` | `remove_stream` | `channel_id` → `stream_id` |
| `reorder_channels` | `reorder_streams` | `channel_ids` → `stream_ids` |
| N/A | `available_streams` | NEW: Get all streams |
| N/A | `cleanup_invalid_streams` | NEW: Clean bouquet |

### Response Changes

| Old Field | New Field |
|-----------|-----------|
| `channel_count` | `stream_count` |
| `channels` array | `streams` array |
| N/A | `running_stream_count` (NEW) |

---

## Benefits of This Refactoring

1. **Simplified Architecture**: Removed 2 tables and 1 model
2. **Direct Relationships**: Bouquets → Streams (no intermediate)
3. **Better Performance**: One less JOIN in queries
4. **Easier Maintenance**: Less code to maintain
5. **Flexible Ordering**: JSON array preserves stream order naturally
6. **Clearer Intent**: Streams are the actual content, not channels

---

## Rollback Plan (If Needed)

If issues arise, you can restore the old structure by:

1. Restore database from backup:
   ```bash
   mysql -u root -p fos_streaming < backup_before_refactoring.sql
   ```

2. Restore deleted files from git:
   ```bash
   git checkout HEAD -- models/Channel.php
   git checkout HEAD -- public/admin/api/channels.php
   git checkout HEAD -- database/migrations/laravel/2025_11_24_100003_create_channels_table.php
   git checkout HEAD -- database/migrations/laravel/2025_11_24_100008_create_bouquet_channel_table.php
   git checkout HEAD -- resources/js/views/Subscribers/ChannelsList.vue
   ```

3. Rebuild frontend:
   ```bash
   npm run build
   ```

---

## Next Steps

1. ✅ Backend refactoring (COMPLETED)
2. 🔶 Update Vue components to use streams
3. 🔶 Update router configuration
4. 🔶 Update API service
5. 🔶 Update navigation menu
6. 🔶 Test fresh migration
7. 🔶 Test in browser
8. 🔶 Update CLAUDE.md with new architecture
9. 🔶 Commit changes with descriptive message

---

**Last Updated**: 2025-11-24
**Author**: Claude Code + Development Team
