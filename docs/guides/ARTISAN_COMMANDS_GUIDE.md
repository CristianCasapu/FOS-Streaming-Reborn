# Artisan Commands Guide

## Overview

FOS-Streaming v70 includes a comprehensive Laravel-style Artisan CLI for managing your application. This guide covers all available database commands with examples and best practices.

**Last Updated**: 2025-11-24
**Version**: v70.0.0

---

## Table of Contents

1. [Installation](#installation)
2. [Available Commands](#available-commands)
3. [Database Commands](#database-commands)
4. [Bash Completion](#bash-completion)
5. [Command Reference](#command-reference)
6. [Common Workflows](#common-workflows)
7. [Tips & Best Practices](#tips--best-practices)

---

## Installation

The Artisan CLI is included with FOS-Streaming v70. Make sure the artisan file is executable:

```bash
chmod +x artisan
```

### Verify Installation

```bash
php artisan list
```

You should see a list of all available commands.

---

## Available Commands

### Quick Reference

| Command | Description |
|---------|-------------|
| `php artisan list` | List all available commands |
| `php artisan help [command]` | Display help for a command |
| `php artisan migrate` | Run database migrations |
| `php artisan migrate:fresh` | Drop all tables and re-migrate |
| `php artisan migrate:fresh --seed` | Fresh migration + seeding |
| `php artisan migrate:status` | Show migration status |
| `php artisan db:seed` | Run database seeders |
| `php artisan db:seed [class]` | Run specific seeder |
| `php artisan db:wipe` | Drop all database tables |
| `php artisan serve` | Start development server |

---

## Database Commands

### 1. migrate

**Purpose**: Run pending database migrations

```bash
# Run all pending migrations
php artisan migrate

# Force run in production (dangerous!)
php artisan migrate --force

# See what SQL will be executed
php artisan migrate --pretend
```

**What it does**:
- Creates `migrations` table if not exists
- Scans `/database/migrations/laravel/` for migration files
- Runs only migrations that haven't been executed yet
- Records migrations in `migrations` table with batch number
- Stops on first error

**Output Example**:
```
Running Migrations
═══════════════════════════════════════════════════

→ Migrating: 2025_11_24_010001_create_staff_table
  ✓ Migrated:  2025_11_24_010001_create_staff_table

→ Migrating: 2025_11_24_010002_create_categories_table
  ✓ Migrated:  2025_11_24_010002_create_categories_table

✓ Successfully ran 2 migration(s)
```

**Options**:
- `--force, -f`: Force run in production environment
- `--pretend, -p`: Display SQL without executing

**Safety**:
- ✓ Safe to run multiple times (skips already-run migrations)
- ✓ Blocked in production without `--force`
- ✓ Transactional (rolls back on error)

---

### 2. migrate:fresh

**Purpose**: Drop all tables and re-run all migrations

```bash
# Drop all tables and migrate
php artisan migrate:fresh

# Drop, migrate, and seed
php artisan migrate:fresh --seed

# Force in production
php artisan migrate:fresh --force
```

**What it does**:
1. Prompts for confirmation (unless `--force`)
2. Drops ALL tables in the database
3. Runs all migrations from scratch
4. Optionally seeds database with `--seed`

**Output Example**:
```
╔════════════════════════════════════════════════════════════╗
║  ⚠  WARNING: This will DELETE ALL DATA!                   ║
╚════════════════════════════════════════════════════════════╝

Are you sure you want to drop all tables? (yes/no) [no]: yes

Dropping All Tables
═══════════════════════════════════════════════════
  ✓ Dropped: subscribers
  ✓ Dropped: streams
  ✓ Dropped: packages
  ...

✓ All tables dropped successfully

Running Fresh Migrations
═══════════════════════════════════════════════════
  ✓ Migrated: 2025_11_24_010001_create_staff_table
  ...

✓ Successfully ran 24 migration(s)

✓ Database refreshed successfully!
```

**Options**:
- `--seed, -s`: Run seeders after migrations
- `--force, -f`: Skip confirmation and production check

**Use Cases**:
- 🔄 **Development**: Fresh start during development
- 🧪 **Testing**: Reset test database
- 🚫 **Production**: NEVER use in production!

**Safety**:
- ⚠️ **DESTRUCTIVE**: Deletes ALL data
- ✓ Requires confirmation
- ✓ Blocked in production without `--force`

---

### 3. migrate:status

**Purpose**: Show which migrations have been run

```bash
php artisan migrate:status
```

**Output Example**:
```
Migration Status
═══════════════════════════════════════════════════

+----------+--------------------------------------------------+----------+
| Status   | Migration                                        | Batch    |
+----------+--------------------------------------------------+----------+
| ✓ Ran    | 2025_11_24_010001_create_staff_table            | Batch 1  |
| ✓ Ran    | 2025_11_24_010002_create_categories_table       | Batch 1  |
| ⊘ Pending| 2025_11_24_100001_create_packages_table         | -        |
+----------+--------------------------------------------------+----------+

Total: 24 migrations
Ran: 23
Pending: 1
```

**Use Cases**:
- 🔍 Check if migrations are up to date
- 📊 See migration history
- 🐛 Debug migration issues

---

### 4. db:seed

**Purpose**: Populate database with seed data

```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed PackagesSeeder

# Force in production
php artisan db:seed --force
```

**What it does**:
- Runs all seeders in `/database/seeders/` directory
- Seeders are safe to run multiple times (check for existing data)
- Populates: packages, bouquets, PM2 workers, settings, etc.

**Output Example**:
```
Database Seeding
═══════════════════════════════════════════════════

→ Seeding: PackagesSeeder
  ✓ Seeded: PackagesSeeder

→ Seeding: BouquetsSeeder
  ✓ Seeded: BouquetsSeeder

→ Seeding: PM2WorkersSeeder
  ✓ Seeded: PM2WorkersSeeder

✓ All seeders completed successfully!
```

**Available Seeders**:
- `PackagesSeeder` - 4 subscription tiers
- `BouquetsSeeder` - 8 stream groups
- `PackageBouquetSeeder` - Package-bouquet relationships
- `PM2WorkersSeeder` - 8 background workers
- `SettingsSeeder` - Application settings
- `AdminRolesSeeder` - Role definitions
- `ResellersSeeder` - Sample reseller accounts
- `V2RayServersSeeder` - V2Ray server nodes

**Options**:
- `--force, -f`: Force run in production

**Safety**:
- ✓ Safe to run multiple times
- ✓ Checks for existing data before inserting
- ✓ Blocked in production without `--force`

---

### 5. db:wipe

**Purpose**: Drop all database tables

```bash
# Wipe database
php artisan db:wipe

# Force in production
php artisan db:wipe --force
```

**What it does**:
1. Requires typing "DELETE" to confirm
2. Drops all tables in the database
3. Provides next steps

**Output Example**:
```
╔════════════════════════════════════════════════════════════╗
║  ⚠  WARNING: This will DELETE ALL DATABASE TABLES!        ║
╚════════════════════════════════════════════════════════════╝

Are you absolutely sure? Type "DELETE" to confirm: DELETE

Wiping Database
═══════════════════════════════════════════════════
  ✓ Dropped: subscribers
  ✓ Dropped: streams
  ...

✓ Successfully dropped 38 table(s)

Next steps:
  Run: php artisan migrate to recreate tables
  Run: php artisan db:seed to seed data
```

**Options**:
- `--force, -f`: Skip confirmation

**Safety**:
- ⚠️ **DESTRUCTIVE**: Deletes ALL tables
- ✓ Requires typing "DELETE" to confirm
- ✓ Blocked in production without `--force`

---

## Bash Completion

Enable tab completion for faster command typing.

### Installation

**Option 1: System-wide (requires sudo)**

```bash
sudo cp artisan-completion.bash /etc/bash_completion.d/artisan
source ~/.bashrc
```

**Option 2: User-specific**

```bash
# Add to ~/.bashrc
echo "source $(pwd)/artisan-completion.bash" >> ~/.bashrc
source ~/.bashrc
```

### Usage

```bash
# Type and press TAB
php artisan mig[TAB]
# Completes to: php artisan migrate

# Show all commands
php artisan [TAB][TAB]

# Complete options
php artisan migrate --[TAB][TAB]
# Shows: --force --pretend --help
```

---

## Command Reference

### Get Help for Any Command

```bash
# General help
php artisan help

# Command-specific help
php artisan help migrate
php artisan help migrate:fresh
php artisan help db:seed
```

### List All Commands

```bash
php artisan list
```

**Output**:
```
FOS Streaming v70

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display help
  -q, --quiet           Do not output any message
  -V, --version         Display version
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase verbosity

Available commands:
  help           Display help
  list           List commands
  serve          Serve the application

 db
  db:seed        Seed the database
  db:wipe        Drop all tables

 migrate
  migrate        Run migrations
  migrate:fresh  Drop all tables and re-migrate
  migrate:status Show migration status
```

---

## Common Workflows

### Fresh Installation

```bash
# 1. Create database (first time only)
php database/recreate_database.php

# 2. Run migrations
php artisan migrate

# 3. Seed data
php artisan db:seed

# 4. Create admin account
php database/seeders/AdminSeeder.php

# 5. Verify
php database/verify_deployment.php
```

### Quick Reset (Development)

```bash
# Drop all tables, migrate, and seed in one command
php artisan migrate:fresh --seed

# Create admin
php database/seeders/AdminSeeder.php
```

### Update Database Schema

```bash
# Run new migrations only
php artisan migrate

# Check status
php artisan migrate:status
```

### Re-seed Data

```bash
# Re-run all seeders (safe)
php artisan db:seed

# Re-run specific seeder
php artisan db:seed PackagesSeeder
```

### Check Migration Status

```bash
# See what's been run
php artisan migrate:status

# Count pending migrations
php artisan migrate:status | grep Pending | wc -l
```

---

## Tips & Best Practices

### Production Safety

**Never run these in production without backup:**
- `php artisan migrate:fresh`
- `php artisan db:wipe`

**Always test migrations:**
```bash
# Use --pretend to see SQL
php artisan migrate --pretend

# Test on staging first
php artisan migrate --force
```

### Development Speed

**Use migrate:fresh during development:**
```bash
# Fast reset
php artisan migrate:fresh --seed
```

**Use bash completion:**
```bash
# Install once
source artisan-completion.bash

# Then just type and TAB
php artisan mig[TAB] --s[TAB]
```

### Migration Management

**Check before migrating:**
```bash
php artisan migrate:status
```

**Run migrations incrementally:**
```bash
# Only runs new migrations
php artisan migrate
```

**Never edit run migrations:**
- Create new migrations for changes
- Keep migration history intact

### Seeding Best Practices

**Seeders should be idempotent:**
```php
// Good: Check before inserting
if (!Package::where('name', 'Basic')->exists()) {
    Package::create([...]);
}

// Bad: Always insert (causes duplicates)
Package::create([...]);
```

**Run seeders after schema changes:**
```bash
php artisan migrate
php artisan db:seed
```

### Troubleshooting

**Command not found:**
```bash
# Make executable
chmod +x artisan

# Or use php explicitly
php artisan list
```

**Migration fails:**
```bash
# Check status
php artisan migrate:status

# View last error in output
php artisan migrate

# Reset and retry
php artisan migrate:fresh
```

**Seeding fails:**
```bash
# Run specific seeder to isolate issue
php artisan db:seed PackagesSeeder

# Check database connection
php artisan migrate:status
```

---

## Comparison with Old Scripts

### Before (Old Scripts)

```bash
# Multiple steps, verbose
php database/recreate_database.php
php database/migrate.php
php database/seed.php
php database/seeders/AdminSeeder.php
```

### After (Artisan)

```bash
# Single command
php artisan migrate:fresh --seed

# Then create admin
php database/seeders/AdminSeeder.php
```

### Benefits

✓ **Faster**: Single commands for common tasks
✓ **Safer**: Production checks built-in
✓ **Clearer**: Better error messages and progress
✓ **Standard**: Laravel-style commands familiar to developers
✓ **Autocomplete**: Bash completion for speed
✓ **Consistent**: All commands follow same pattern

---

## Environment-Specific Behavior

### Development (APP_ENV=local or development)

- ✓ All commands work without `--force`
- ✓ Prompts for destructive operations
- ✓ Detailed output

### Production (APP_ENV=production)

- ⚠️ Destructive commands blocked without `--force`
- ⚠️ Extra confirmation required
- ⚠️ Minimal output

**Override in production:**
```bash
php artisan migrate --force
php artisan migrate:fresh --force --seed
php artisan db:wipe --force
```

---

## Advanced Usage

### Scripting

```bash
#!/bin/bash
# Reset development database

php artisan migrate:fresh --seed --force
php database/seeders/AdminSeeder.php
php database/verify_deployment.php
```

### CI/CD Pipeline

```yaml
# .github/workflows/deploy.yml
steps:
  - name: Run migrations
    run: php artisan migrate --force

  - name: Verify
    run: php database/verify_deployment.php
```

### Docker

```dockerfile
# In Dockerfile
RUN php artisan migrate --force
RUN php artisan db:seed --force
```

---

## Related Documentation

- **Fresh Deployment**: [FRESH_DEPLOYMENT_DATABASE_GUIDE.md](FRESH_DEPLOYMENT_DATABASE_GUIDE.md)
- **Project Overview**: [CLAUDE.md](../../CLAUDE.md)
- **Database Migrations**: `/database/migrations/laravel/`
- **Database Seeders**: `/database/seeders/`

---

## Quick Command Cheat Sheet

```bash
# List commands
php artisan list

# Get help
php artisan help migrate

# Fresh install
php artisan migrate:fresh --seed

# Update schema
php artisan migrate

# Check status
php artisan migrate:status

# Re-seed
php artisan db:seed

# Wipe database
php artisan db:wipe

# Start server
php artisan serve
```

---

**Document Version**: 1.0
**Last Updated**: 2025-11-24
**Compatibility**: FOS-Streaming v70.0.0
