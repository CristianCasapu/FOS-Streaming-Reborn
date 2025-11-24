# Composer Commands Guide

## Overview

FOS-Streaming v70 includes an extensive set of Composer scripts for common development tasks. This guide covers all custom scripts with examples and best practices.

**Last Updated**: 2025-11-24
**Version**: v70.0.0

---

## Table of Contents

1. [Quick Reference](#quick-reference)
2. [Testing Commands](#testing-commands)
3. [Code Quality Commands](#code-quality-commands)
4. [Database Commands](#database-commands)
5. [Development Commands](#development-commands)
6. [Deployment Commands](#deployment-commands)
7. [Bash Completion](#bash-completion)
8. [Common Workflows](#common-workflows)

---

## Quick Reference

### All Available Scripts

| Command | Description |
|---------|-------------|
| `composer test` | Run all tests |
| `composer test:unit` | Run unit tests only |
| `composer test:feature` | Run feature tests only |
| `composer test:coverage` | Generate test coverage report |
| `composer lint` | Auto-fix code style (Laravel Pint) |
| `composer lint:test` | Check code style without fixing |
| `composer analyze` | Static analysis (PHPStan) |
| `composer format` | Alias for lint |
| `composer check` | Run lint + analyze + test |
| `composer security-check` | Security vulnerability scan |
| `composer organize-docs` | Organize documentation |
| `composer serve` | Start development server |
| `composer serve:dev` | Start dev server (all interfaces) |
| `composer fresh` | Fresh database with seeding |
| `composer migrate` | Run database migrations |
| `composer migrate:fresh` | Alias for fresh |
| `composer migrate:status` | Show migration status |
| `composer db:seed` | Seed database |
| `composer db:wipe` | Drop all tables |
| `composer clear-cache` | Clear all caches |
| `composer cache:clear` | Alias for clear-cache |
| `composer optimize` | Optimize application |
| `composer check-syntax` | Check PHP syntax |
| `composer phpcs` | Check PSR-12 compliance |
| `composer phpcs:fix` | Auto-fix PSR-12 violations |
| `composer deploy:check` | Pre-deployment checks |
| `composer dev` | Quick dev setup |
| `composer prod:deploy` | Production deployment |

---

## Testing Commands

### Run All Tests

```bash
composer test
```

**What it does**:
- Runs PHPUnit test suite
- Uses Artisan test command
- Disables timeout for long-running tests

**Output Example**:
```
   PASS  Tests\Unit\StreamTest
  ✓ stream can be created
  ✓ stream has correct attributes

   PASS  Tests\Feature\AuthenticationTest
  ✓ user can login
  ✓ user can logout

  Tests:  15 passed
  Time:   2.34s
```

### Run Unit Tests Only

```bash
composer test:unit
```

**Use for**: Fast unit tests, TDD workflow

### Run Feature Tests Only

```bash
composer test:feature
```

**Use for**: Integration tests, API tests

### Generate Coverage Report

```bash
composer test:coverage
```

**Output**: HTML coverage report in `/coverage` directory

**View report**:
```bash
# Open in browser
xdg-open coverage/index.html
# Or on Mac
open coverage/index.html
```

---

## Code Quality Commands

### Auto-fix Code Style (Laravel Pint)

```bash
composer lint
# Or
composer format
```

**What it does**:
- Automatically fixes code style issues
- Uses Laravel Pint (built on PHP-CS-Fixer)
- Applies Laravel coding standards

**Output Example**:
```
  FIXED  app/Http/Controllers/StreamController.php
    ⇂ Unnecessary blank lines removed
    ⇂ Proper method spacing applied

  FIXED  models/Stream.php
    ⇂ Array indentation corrected

  Fixed 2 files in 0.5s
```

### Check Code Style (No Changes)

```bash
composer lint:test
```

**Use for**:
- CI/CD pipelines
- Pre-commit checks
- See what needs fixing without modifying files

**Output**:
```
  FAIL  app/Http/Controllers/StreamController.php
    ⨯ Line 42: Expected 1 blank line, found 2

  1 file would be fixed
```

### Static Analysis (PHPStan)

```bash
composer analyze
```

**What it does**:
- Finds bugs without running code
- Type checking
- Dead code detection
- Undefined variables/methods

**Output Example**:
```
 [OK] No errors

 Analyzed 234 files
 Memory: 512 MB
```

**Or with errors**:
```
 ------ -----------------------------------------------
  Line   app/Models/Stream.php
 ------ -----------------------------------------------
  45     Property Stream::$status has no type specified
  78     Method Stream::getActiveStreams() return type
 ------ -----------------------------------------------

 [ERROR] Found 2 errors
```

### Comprehensive Check

```bash
composer check
```

**What it does** (in order):
1. Check code style (`lint:test`)
2. Run static analysis (`analyze`)
3. Run all tests (`test`)

**Use for**:
- Before committing
- Before deploying
- CI/CD pipelines

**Output**:
```
> pint --test
  ✓ Code style passed

> phpstan analyse
  ✓ No errors found

> phpunit
  ✓ Tests: 15 passed

✓ All checks passed!
```

### Security Vulnerability Check

```bash
composer security-check
```

**What it does**:
- Scans dependencies for known vulnerabilities
- Checks against security advisories
- Reports CVEs

### Check PHP Syntax

```bash
composer check-syntax
```

**What it does**:
- Checks all PHP files for syntax errors
- Fast validation before running tests
- Useful after mass changes

### PSR-12 Code Standard

```bash
# Check compliance
composer phpcs

# Auto-fix violations
composer phpcs:fix
```

**Standards**: PSR-12 Extended Coding Style

---

## Database Commands

### Fresh Database with Seeding

```bash
composer fresh
# Or
composer migrate:fresh
```

**What it does**:
- Drops all tables
- Runs all migrations
- Seeds database with default data
- ⚠️ Destructive! Deletes all data

**Equivalent to**:
```bash
php artisan migrate:fresh --seed
```

### Run Migrations

```bash
composer migrate
```

**What it does**:
- Runs pending migrations only
- Safe to run multiple times

### Show Migration Status

```bash
composer migrate:status
```

**Output**:
```
Migration Status
═══════════════════════════════════════════════════

+--------+---------------------------------------+---------+
| Status | Migration                             | Batch   |
+--------+---------------------------------------+---------+
| ✓ Ran  | 2025_11_24_create_staff_table        | Batch 1 |
| ⊘ Pending| 2025_11_24_create_packages_table   | -       |
+--------+---------------------------------------+---------+
```

### Seed Database

```bash
composer db:seed
```

**Safe**: Checks for existing data before inserting

### Wipe Database

```bash
composer db:wipe
```

**Warning**: Drops all tables! Requires `--force` in production

---

## Development Commands

### Start Development Server

```bash
# Standard (localhost only)
composer serve

# All interfaces (accessible from network)
composer serve:dev
```

**Output**:
```
FOS Streaming development server started: http://127.0.0.1:8000
Press Ctrl+C to stop the server
```

**Access**:
- Local: `http://localhost:8000`
- Network: `http://192.168.1.x:8000` (with serve:dev)

### Clear Caches

```bash
composer clear-cache
# Or
composer cache:clear
```

**What it clears**:
- Template cache (`/cache/*.php`)
- Application cache
- Route cache
- Config cache
- View cache

### Optimize Application

```bash
composer optimize
```

**What it does**:
- Cache routes
- Cache config
- Cache views
- Optimize autoloader

**Use for**: Production deployments

### Quick Dev Setup

```bash
composer dev
```

**What it does** (in order):
1. Clear all caches
2. Run migrations
3. Start development server

**Perfect for**: Starting fresh dev session

### Organize Documentation

```bash
composer organize-docs
```

**What it does**:
- Organizes docs into proper directories
- Validates markdown structure
- Updates table of contents

---

## Deployment Commands

### Pre-Deployment Checks

```bash
composer deploy:check
```

**What it does** (in order):
1. Check PHP syntax
2. Check code style (`lint:test`)
3. Run static analysis (`analyze`)
4. Run all tests (`test`)

**Use before**: Deploying to staging/production

**Output**:
```
> check-syntax
  ✓ Syntax check passed

> lint:test
  ✓ Code style passed

> analyze
  ✓ No errors found

> test
  ✓ Tests: 15 passed

✓ Ready to deploy!
```

### Production Deployment

```bash
composer prod:deploy
```

**What it does** (in order):
1. Install production dependencies (`--no-dev`)
2. Optimize autoloader
3. Run migrations with `--force`
4. Optimize application

**Warning**: Only run on production servers!

**Equivalent to**:
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
```

---

## Bash Completion

Enable tab completion for faster command typing.

### Installation

**Option 1: System-wide (requires sudo)**

```bash
sudo cp composer-completion.bash /etc/bash_completion.d/composer-fos
source ~/.bashrc
```

**Option 2: User-specific**

```bash
# Add to ~/.bashrc
echo "source $(pwd)/composer-completion.bash" >> ~/.bashrc
source ~/.bashrc
```

### Usage

```bash
# Type and press TAB
composer te[TAB]
# Completes to: composer test

# Show all commands
composer [TAB][TAB]

# Complete colons
composer migrate:[TAB][TAB]
# Shows: migrate:fresh migrate:status

# Complete options
composer test --[TAB][TAB]
# Shows: --filter --testsuite --group
```

---

## Common Workflows

### Daily Development

```bash
# Start your day
composer dev

# Make changes...

# Before committing
composer check

# If issues found
composer lint           # Auto-fix style
composer test           # Run tests
```

### Test-Driven Development (TDD)

```bash
# Write test first
composer test:unit --filter MyNewFeatureTest

# Make it pass
composer test:unit

# Check coverage
composer test:coverage
```

### Before Committing

```bash
# Run all checks
composer check

# Or step-by-step
composer lint:test      # Check style
composer analyze        # Static analysis
composer test           # Run tests
```

### Database Reset

```bash
# Complete reset
composer fresh

# Just re-seed
composer db:seed
```

### Pre-Deployment

```bash
# Run comprehensive checks
composer deploy:check

# If all pass, deploy
composer prod:deploy    # On production server
```

### Code Review Prep

```bash
# Fix code style
composer lint

# Check for bugs
composer analyze

# Ensure tests pass
composer test

# Check coverage
composer test:coverage
```

### Performance Optimization

```bash
# Clear caches
composer clear-cache

# Optimize
composer optimize

# Check it works
composer serve
```

---

## Tips & Best Practices

### Development Speed

**Use short commands:**
```bash
# Instead of
php artisan migrate:fresh --seed

# Use
composer fresh
```

**Chain commands:**
```bash
composer lint && composer test
```

**Use bash completion:**
```bash
source composer-completion.bash
composer te[TAB]  # Autocompletes to 'test'
```

### Code Quality

**Run checks before every commit:**
```bash
# Add to git pre-commit hook
composer check
```

**Fix style automatically:**
```bash
# Don't fix manually, use Pint
composer lint
```

**Trust static analysis:**
```bash
# PHPStan catches bugs
composer analyze
```

### Testing

**Run fast tests frequently:**
```bash
# Quick feedback loop
composer test:unit
```

**Run all tests before push:**
```bash
composer test
```

**Check coverage periodically:**
```bash
composer test:coverage
# Aim for 80%+ coverage
```

### Deployment

**Never skip checks:**
```bash
# Always run before deploy
composer deploy:check
```

**Use proper deploy command:**
```bash
# On production
composer prod:deploy
```

**Verify after deploy:**
```bash
composer migrate:status
php artisan optimize
```

---

## Troubleshooting

### "Script not found"

```bash
# List available scripts
composer list

# Or check composer.json
cat composer.json | grep -A 30 "scripts"
```

### Tests Fail

```bash
# Run specific test
composer test --filter MyTest

# Check syntax first
composer check-syntax

# Clear cache
composer clear-cache
```

### Lint Fails

```bash
# See what needs fixing
composer lint:test

# Auto-fix
composer lint

# Check again
composer lint:test
```

### Analysis Errors

```bash
# See errors
composer analyze

# Check config
cat phpstan.neon

# Increase memory
composer analyze -- --memory-limit=4G
```

### Deploy Check Fails

```bash
# Run checks individually
composer check-syntax
composer lint:test
composer analyze
composer test

# Fix issues as found
```

---

## Command Categories

### Quick Reference by Category

**Testing**:
- `test` - All tests
- `test:unit` - Unit only
- `test:feature` - Feature only
- `test:coverage` - With coverage

**Code Quality**:
- `lint` - Auto-fix style
- `lint:test` - Check style
- `analyze` - Static analysis
- `format` - Alias for lint
- `check` - All quality checks
- `phpcs` - PSR-12 check
- `phpcs:fix` - PSR-12 fix

**Database**:
- `fresh` - Fresh with seed
- `migrate` - Run migrations
- `migrate:fresh` - Fresh with seed
- `migrate:status` - Show status
- `db:seed` - Seed data
- `db:wipe` - Drop tables

**Development**:
- `serve` - Start server
- `serve:dev` - Server (all IPs)
- `clear-cache` - Clear caches
- `optimize` - Optimize app
- `dev` - Quick dev setup

**Deployment**:
- `deploy:check` - Pre-deploy checks
- `prod:deploy` - Production deploy

**Utilities**:
- `security-check` - Security scan
- `organize-docs` - Organize docs
- `check-syntax` - PHP syntax
- `ide-helper` - IDE helpers

---

## Comparison with Direct Commands

### Before (Manual Commands)

```bash
# Multiple steps, error-prone
./vendor/bin/phpunit
./vendor/bin/pint
./vendor/bin/phpstan analyse
php artisan migrate:fresh --seed
php artisan serve
```

### After (Composer Scripts)

```bash
# Single commands, consistent
composer test
composer lint
composer analyze
composer fresh
composer serve
```

### Benefits

✓ **Shorter** - Less typing
✓ **Consistent** - Same commands everywhere
✓ **Documented** - Self-documenting in composer.json
✓ **Autocomplete** - Tab completion support
✓ **Portable** - Works on any system
✓ **Chainable** - Easy to combine with `&&`

---

## Integration with CI/CD

### GitHub Actions Example

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4

      - name: Install Dependencies
        run: composer install

      - name: Run Checks
        run: composer deploy:check
```

### GitLab CI Example

```yaml
# .gitlab-ci.yml
test:
  image: php:8.4
  script:
    - composer install
    - composer deploy:check
```

### Docker Example

```dockerfile
# Run tests in Docker
RUN composer install
RUN composer test
```

---

## Related Documentation

- **Artisan Commands**: [ARTISAN_COMMANDS_GUIDE.md](ARTISAN_COMMANDS_GUIDE.md)
- **Fresh Deployment**: [FRESH_DEPLOYMENT_DATABASE_GUIDE.md](FRESH_DEPLOYMENT_DATABASE_GUIDE.md)
- **Project Overview**: [CLAUDE.md](../../CLAUDE.md)

---

## Quick Command Cheat Sheet

```bash
# Development
composer dev              # Clear cache + migrate + serve
composer serve            # Start dev server
composer fresh            # Fresh database

# Testing
composer test             # All tests
composer test:unit        # Unit tests only
composer test:coverage    # With coverage

# Code Quality
composer lint             # Auto-fix style
composer analyze          # Static analysis
composer check            # All checks

# Database
composer migrate          # Run migrations
composer db:seed          # Seed database
composer migrate:status   # Check status

# Deployment
composer deploy:check     # Pre-deploy checks
composer prod:deploy      # Production deploy

# Utilities
composer clear-cache      # Clear all caches
composer security-check   # Security scan
```

---

**Document Version**: 1.0
**Last Updated**: 2025-11-24
**Compatibility**: FOS-Streaming v70.0.0
