# PM2 Worker Auto-Loader Implementation Plan

**Created:** 2025-11-23
**Version:** 1.0
**Status:** Planning Phase

---

## 📋 Overview

This document outlines the implementation plan for converting the PM2 worker management system from **hardcoded configuration** to a **database-driven auto-loader system**.

### Current State
- Workers are hardcoded in `/public/admin/api/pm2.php`
- Configuration is static in `ecosystem.config.js`
- Adding new workers requires code changes

### Desired State
- Workers are stored in database table `pm2_workers`
- Dynamic ecosystem.config.js generation from database
- CRUD operations for workers via Admin UI
- Automatic reload when workers are added/modified

---

## 🎯 Goals

1. **Flexibility**: Add/remove/modify workers without code changes
2. **Scalability**: Support unlimited workers
3. **Maintainability**: Central configuration in database
4. **Auto-Discovery**: Automatically load new workers
5. **Version Control**: Track worker configuration changes
6. **Backwards Compatibility**: Existing workers continue to work

---

## 📊 Database Schema Design

### Table: `pm2_workers`

```sql
CREATE TABLE `pm2_workers` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Worker Identification
  `name` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Worker process name (e.g., stream-import-worker)',
  `display_name` VARCHAR(255) NOT NULL COMMENT 'Human-readable name',
  `description` TEXT NULL COMMENT 'Worker purpose and functionality',

  -- Script Configuration
  `script` VARCHAR(500) NOT NULL COMMENT 'Path to worker script (e.g., ./workers/stream-import-worker.js)',
  `cwd` VARCHAR(500) NULL COMMENT 'Working directory (null = project root)',
  `args` TEXT NULL COMMENT 'Command-line arguments (JSON array)',

  -- Execution Mode
  `exec_mode` ENUM('fork', 'cluster') DEFAULT 'fork' COMMENT 'PM2 execution mode',
  `instances` INT(11) DEFAULT 1 COMMENT 'Number of instances (1-16)',

  -- Resource Limits
  `max_memory_restart` VARCHAR(20) DEFAULT '500M' COMMENT 'Auto-restart memory limit (e.g., 500M, 1G)',
  `max_restarts` INT(11) DEFAULT 10 COMMENT 'Max restart attempts before giving up',
  `min_uptime` VARCHAR(20) DEFAULT '10s' COMMENT 'Minimum uptime before restart (e.g., 10s, 1m)',
  `restart_delay` INT(11) DEFAULT 5000 COMMENT 'Delay between restarts (milliseconds)',

  -- Auto-Restart Configuration
  `autorestart` TINYINT(1) DEFAULT 1 COMMENT 'Enable auto-restart on crash',
  `cron_restart` VARCHAR(100) NULL COMMENT 'Cron expression for scheduled restarts',

  -- Logging Configuration
  `log_level` ENUM('debug', 'info', 'warn', 'error') DEFAULT 'warn' COMMENT 'Log verbosity level',
  `error_file` VARCHAR(500) NULL COMMENT 'Error log file path',
  `out_file` VARCHAR(500) NULL COMMENT 'Output log file path',
  `log_file` VARCHAR(500) NULL COMMENT 'Combined log file path',
  `merge_logs` TINYINT(1) DEFAULT 1 COMMENT 'Merge logs from all instances',

  -- Environment Variables
  `env_vars` TEXT NULL COMMENT 'Environment variables (JSON object)',

  -- Watch Mode (Development)
  `watch` TINYINT(1) DEFAULT 0 COMMENT 'Watch for file changes (development only)',
  `ignore_watch` TEXT NULL COMMENT 'Paths to ignore in watch mode (JSON array)',

  -- Graceful Shutdown
  `kill_timeout` INT(11) DEFAULT 5000 COMMENT 'Time to wait before force kill (milliseconds)',
  `listen_timeout` INT(11) DEFAULT 3000 COMMENT 'Time to wait for app to listen (milliseconds)',

  -- Worker State
  `enabled` TINYINT(1) DEFAULT 1 COMMENT 'Is worker enabled?',
  `auto_start` TINYINT(1) DEFAULT 1 COMMENT 'Auto-start on system boot?',
  `priority` INT(11) DEFAULT 100 COMMENT 'Start order priority (lower = start first)',

  -- Worker Category/Tags
  `category` VARCHAR(100) NULL COMMENT 'Worker category (e.g., import, analysis, cleanup)',
  `tags` TEXT NULL COMMENT 'Worker tags (JSON array)',

  -- Metadata
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT(11) NULL COMMENT 'Admin user who created this worker',
  `updated_by` INT(11) NULL COMMENT 'Admin user who last updated this worker',

  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `enabled` (`enabled`),
  KEY `category` (`category`),
  KEY `priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='PM2 Worker Configuration';
```

### Indexes Strategy
- **Primary Key**: `id` for internal reference
- **Unique Key**: `name` to prevent duplicate worker names
- **Index on `enabled`**: Fast filtering of active workers
- **Index on `category`**: Group workers by category
- **Index on `priority`**: Order workers by start priority

---

## 🏗️ Architecture Components

### 1. Database Layer
- **Migration**: `database/migrations/create_pm2_workers_table.sql`
- **Seeder**: `database/seeds/pm2_workers_seeder.sql`
- **Model**: `models/PM2Worker.php`

### 2. API Layer
- **Endpoint**: `/public/admin/api/pm2-workers.php` (CRUD operations)
- **Updated**: `/public/admin/api/pm2.php` (load from database)

### 3. Service Layer
- **Class**: `app/Services/PM2WorkerService.php`
  - Load workers from database
  - Generate ecosystem.config.js
  - Validate worker configuration
  - Sync with PM2 daemon

### 4. Frontend Layer
- **Component**: `resources/js/components/PM2WorkerManager.vue` (new)
- **Updated**: `resources/js/components/PM2Manager.vue`
- **API Service**: `resources/js/services/api.js` (add pm2Workers endpoints)

---

## 🔄 Auto-Loader Workflow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Admin adds/updates worker in database                    │
└─────────────────────┬───────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. PM2WorkerService generates ecosystem.config.js           │
│    - Loads all enabled workers from database                │
│    - Converts DB fields to PM2 configuration format         │
│    - Writes to ecosystem.config.js                          │
└─────────────────────┬───────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. PM2 daemon reloads configuration                         │
│    - Executes: pm2 restart ecosystem.config.js              │
│    - Or: pm2 reload ecosystem.config.js (zero-downtime)     │
└─────────────────────┬───────────────────────────────────────┘
                      ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Workers start/restart based on new configuration         │
└─────────────────────────────────────────────────────────────┘
```

---

## 📝 Implementation Steps

### Phase 1: Database Setup ✅ Ready to implement

**Step 1.1**: Create migration file
- File: `database/migrations/2025-11-23_create_pm2_workers_table.sql`
- Creates `pm2_workers` table with all fields
- Includes indexes and constraints

**Step 1.2**: Create seeder file
- File: `database/seeds/pm2_workers_seeder.sql`
- Seeds existing workers:
  - `stream-import-worker`
  - `ffprobe-worker`

**Step 1.3**: Create Eloquent model
- File: `models/PM2Worker.php`
- Extends `FosStreaming` base class
- Includes casts for JSON fields
- Validation rules
- Scopes for enabled/disabled workers

### Phase 2: Service Layer ⏳ Next

**Step 2.1**: Create PM2WorkerService
- File: `app/Services/PM2WorkerService.php`
- Methods:
  - `getAllWorkers()`: Load all enabled workers
  - `getWorkerByName($name)`: Get specific worker
  - `generateEcosystemConfig()`: Generate ecosystem.config.js
  - `syncWithPM2()`: Apply changes to PM2
  - `validateWorkerConfig($data)`: Validate configuration

**Step 2.2**: Create ecosystem.config.js generator
- Template-based generation
- Converts DB fields to PM2 format
- Handles environment-specific configs

### Phase 3: API Endpoints ⏳ Next

**Step 3.1**: Create `/public/admin/api/pm2-workers.php`
- Actions:
  - `list`: Get all workers
  - `get`: Get single worker
  - `create`: Add new worker
  - `update`: Update worker
  - `delete`: Delete worker
  - `toggle`: Enable/disable worker
  - `sync`: Regenerate ecosystem.config.js and reload PM2

**Step 3.2**: Update `/public/admin/api/pm2.php`
- Replace hardcoded workers with database lookup
- Use PM2WorkerService to load workers

### Phase 4: Frontend Components ⏳ Next

**Step 4.1**: Create PM2WorkerManager component
- Full CRUD UI for managing workers
- Form for creating/editing workers
- Validation
- Real-time sync with PM2

**Step 4.2**: Update PM2Manager component
- Remove hardcoded worker references
- Load workers dynamically from API

**Step 4.3**: Update API service
- Add `pm2WorkersAPI` methods

### Phase 5: Testing & Documentation ⏳ Future

**Step 5.1**: Test auto-loader
- Create new worker via UI
- Verify ecosystem.config.js generation
- Verify PM2 picks up new worker

**Step 5.2**: Create documentation
- Admin guide for managing workers
- Developer guide for creating new workers
- Migration guide from hardcoded to database

---

## 🔧 Technical Details

### JSON Fields in Database

Several fields store JSON data:

1. **`args`**: Command-line arguments
   ```json
   ["--config", "custom.json", "--verbose"]
   ```

2. **`env_vars`**: Environment variables
   ```json
   {
     "NODE_ENV": "production",
     "LOG_LEVEL": "warn",
     "DB_HOST": "localhost"
   }
   ```

3. **`ignore_watch`**: Files/folders to ignore
   ```json
   ["node_modules", "logs", "*.log", "storage", "cache"]
   ```

4. **`tags`**: Worker categorization
   ```json
   ["import", "queue-processor", "high-priority"]
   ```

### PM2Worker Model Casts

```php
protected $casts = [
    'enabled' => 'boolean',
    'auto_start' => 'boolean',
    'autorestart' => 'boolean',
    'watch' => 'boolean',
    'merge_logs' => 'boolean',
    'instances' => 'integer',
    'max_restarts' => 'integer',
    'restart_delay' => 'integer',
    'kill_timeout' => 'integer',
    'listen_timeout' => 'integer',
    'priority' => 'integer',
    'args' => 'array',
    'env_vars' => 'array',
    'ignore_watch' => 'array',
    'tags' => 'array',
];
```

### Ecosystem Config Generation Example

```javascript
// Generated from database
module.exports = {
  apps: [
    {
      name: 'stream-import-worker',
      script: './workers/stream-import-worker.js',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_restarts: 10,
      min_uptime: '10s',
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        LOG_LEVEL: 'warn'
      },
      // ... more fields from database
    },
    // ... more workers
  ]
};
```

---

## ✅ Benefits of Database-Driven Approach

1. **Dynamic Configuration**: No code changes needed
2. **Admin UI**: Manage workers through web interface
3. **Version Control**: Track changes in database
4. **Per-Environment Config**: Different configs for dev/staging/prod
5. **Bulk Operations**: Enable/disable multiple workers
6. **Worker Templates**: Clone existing workers
7. **Historical Data**: Track worker configuration history
8. **Role-Based Access**: Control who can modify workers
9. **Validation**: Centralized validation rules
10. **API-First**: RESTful API for programmatic access

---

## 🚀 Migration Path

### For Existing Installations

1. **Run migration**: Creates `pm2_workers` table
2. **Run seeder**: Populates existing workers
3. **Test**: Verify workers load correctly
4. **Switch**: Update pm2.php to use database
5. **Remove**: Delete hardcoded worker definitions

### Backwards Compatibility

- Existing `ecosystem.config.js` remains functional
- Can run migration without breaking existing setup
- Gradual migration: one worker at a time

---

## 📚 Future Enhancements

1. **Worker Templates**: Pre-configured worker templates
2. **Worker Groups**: Group related workers
3. **Health Monitoring**: Track worker health metrics
4. **Alert Rules**: Notify when worker fails
5. **Resource Scheduling**: CPU/memory allocation per worker
6. **Worker Dependencies**: Define startup order
7. **Worker Logs UI**: View logs in admin panel
8. **Worker Metrics**: CPU, memory, uptime graphs
9. **Worker Scaling**: Auto-scale based on load
10. **Import/Export**: Backup/restore worker configs

---

## 🎬 Getting Started

Ready to implement? Start with:

1. Review this plan
2. Create migration file (Phase 1.1)
3. Create model (Phase 1.3)
4. Create seeder (Phase 1.2)
5. Test database setup
6. Proceed to Phase 2

---

## 📞 Questions & Decisions

### Open Questions

1. **Worker Versioning**: Should we track configuration versions?
2. **Worker Templates**: Include pre-built templates in seeder?
3. **Multi-Tenancy**: Support per-admin/per-user workers?
4. **Worker Logs**: Store in database or keep as files?
5. **Worker Metrics**: Integrate with monitoring service?

### Decisions Made

- ✅ Use database for worker configuration
- ✅ Auto-generate ecosystem.config.js
- ✅ Keep existing workers during migration
- ✅ Use JSON for complex fields
- ✅ Support categories and tags

---

## 📄 Files to Create/Modify

### New Files
- `database/migrations/2025-11-23_create_pm2_workers_table.sql`
- `database/seeds/pm2_workers_seeder.sql`
- `models/PM2Worker.php`
- `app/Services/PM2WorkerService.php`
- `public/admin/api/pm2-workers.php`
- `resources/js/components/PM2WorkerManager.vue`
- `docs/guides/PM2_WORKER_MANAGEMENT.md`

### Modified Files
- `public/admin/api/pm2.php`
- `resources/js/components/PM2Manager.vue`
- `resources/js/services/api.js`

---

**Status**: Ready for implementation
**Next Step**: Phase 1.1 - Create migration file
