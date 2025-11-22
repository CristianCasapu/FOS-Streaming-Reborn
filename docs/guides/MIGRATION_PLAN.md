# FOS-Streaming v70 - Debian 12 Migration & Security Enhancement Plan

**Project**: FOS-Streaming-v70
**Target**: Debian 12 (Bookworm) with latest system packages
**Status**: In Progress
**Last Updated**: 2025-11-21

---

## Table of Contents
1. [Executive Summary](#executive-summary)
2. [Current Architecture](#current-architecture)
3. [Target Architecture](#target-architecture)
4. [Migration Tasks](#migration-tasks)
5. [Security Improvements](#security-improvements)
6. [Testing Strategy](#testing-strategy)
7. [Rollback Plan](#rollback-plan)

---

## Executive Summary

This document outlines the comprehensive migration and modernization plan for FOS-Streaming v70, transitioning from Debian 11 to Debian 12 with modern package versions and enhanced security measures.

### Goals
- ✅ Upgrade to Debian 12 (Bookworm)
- ✅ Implement PHP 8.4 with all modern extensions
- ✅ Deploy latest MariaDB (11.x)
- ✅ Update Nginx with security hardening
- ✅ Integrate latest FFmpeg static build
- ✅ Implement comprehensive security improvements
- ✅ Maintain backward compatibility where critical

---

## Current Architecture

### System Components (Debian 11)
| Component | Current Version | Issues |
|-----------|----------------|--------|
| OS | Debian 11 (Bullseye) | EOL approaching (2026) |
| PHP | 7.3 | EOL (2021-12-06) - No security updates |
| MariaDB | 10.9 | Older version |
| Nginx | Custom build with RTMP | Needs security review |
| FFmpeg | Static build (dynamic) | Good approach, maintained |

### Security Concerns Identified
1. **Critical**: MD5 password hashing (index.php:15)
2. **High**: PHP 7.3 no longer receives security patches
3. **Medium**: Missing rate limiting on authentication
4. **Medium**: No CSRF protection visible
5. **Medium**: SQL injection risk with dynamic queries
6. **Low**: Missing security headers in nginx
7. **Low**: No stream authentication token expiry

---

## Target Architecture

### System Components (Debian 12)
| Component | Target Version | Benefits |
|-----------|---------------|----------|
| OS | Debian 12 (Bookworm) | Active support until 2028 |
| PHP | 8.4 | Latest features, JIT, security patches |
| MariaDB | 11.4 (latest stable) | Performance, modern features |
| Nginx | 1.26+ (mainline) | HTTP/3, security fixes |
| FFmpeg | Latest static (6.1+) | Modern codecs, performance |

### PHP 8.4 Extensions Required
```
Core Extensions:
- php8.4-cli, php8.4-fpm, php8.4-common
- php8.4-mysql (replaces mysqli)
- php8.4-curl, php8.4-gd, php8.4-mbstring
- php8.4-xml, php8.4-zip, php8.4-bcmath
- php8.4-intl, php8.4-opcache

Removed (deprecated):
- php-json (now core in PHP 8.x)
- php-recode (removed from PHP 7.4+)
- php-xmlrpc (moved to PECL)
```

---

## Migration Tasks

### Phase 1: Repository Setup & Analysis ✅
- [x] Clone fospackv69 repository locally
- [x] Analyze nginx-builder components
- [x] Review custom nginx modules
- [x] Identify all dependencies
- [x] Document current build process

### Phase 2: Package Updates 🔄
- [ ] Update nginx-builder for Debian 12
- [ ] Update nginx RTMP module to latest
- [ ] Update nginx GeoIP2 module
- [ ] Test nginx build on Debian 12
- [ ] Update PHP package lists
- [ ] Test MariaDB 11.x compatibility

### Phase 3: Configuration Files 🔄
- [ ] Create `install/debian12` script
- [ ] Create `improvement/php84.conf`
- [ ] Update `improvement/nginx.conf` with security headers
- [ ] Add rate limiting configurations
- [ ] Create systemd service files
- [ ] Update rc.local alternatives

### Phase 4: Application Code Updates 📝
- [ ] Replace MD5 with password_hash/verify
- [ ] Add CSRF token protection
- [ ] Implement prepared statements audit
- [ ] Add stream token expiry
- [ ] Add input validation middleware
- [ ] Update session security settings

### Phase 5: Testing 🧪
- [ ] Syntax validation (PHP 8.4)
- [ ] Database migration testing
- [ ] Stream functionality testing
- [ ] Authentication testing
- [ ] Performance benchmarking
- [ ] Security scanning

### Phase 6: Documentation 📚
- [ ] Update README.md
- [ ] Create UPGRADE.md guide
- [ ] Document security changes
- [ ] Create troubleshooting guide

---

## Security Improvements

### 1. Authentication & Authorization 🔐

#### Current Issues
```php
// index.php:15 - INSECURE: MD5 hashing
$userfind = Admin::where('username', '=', $username)
    ->where('password', '=', md5($password))
    ->count();
```

#### Proposed Solution
```php
// Use PHP 8.4 password_hash with Argon2id
$admin = Admin::where('username', '=', $username)->first();
if ($admin && password_verify($password, $admin->password)) {
    // Check if rehash needed (algorithm upgrade)
    if (password_needs_rehash($admin->password, PASSWORD_ARGON2ID)) {
        $admin->password = password_hash($password, PASSWORD_ARGON2ID);
        $admin->save();
    }
    $_SESSION['user_id'] = $admin->id;
    $_SESSION['username'] = $admin->username;
    header("location: dashboard.php");
}
```

**Implementation Tasks:**
- [ ] Create password migration script
- [ ] Update Admin model with password mutator
- [ ] Add password strength requirements
- [ ] Implement account lockout after failed attempts
- [ ] Add 2FA support (optional)

---

### 2. SQL Injection Prevention 🛡️

#### Strategy
- Audit all raw queries
- Convert to Eloquent ORM or prepared statements
- Enable strict SQL mode
- Implement query logging in development

**Files to Audit:**
- [ ] stream.php
- [ ] playlist.php
- [ ] cron.php
- [ ] All manage_*.php files

---

### 3. Stream Security 📡

#### Current Vulnerabilities
- Stream URLs: `/live/{username}/{password}/{stream}`
- No token expiry
- Credentials in URL (visible in logs)

#### Proposed Improvements

**A. Token-Based Authentication**
```php
// Generate time-limited, signed tokens
function generateStreamToken($userId, $streamId, $ttl = 3600) {
    $payload = [
        'user_id' => $userId,
        'stream_id' => $streamId,
        'expires' => time() + $ttl,
        'ip' => $_SERVER['REMOTE_ADDR']
    ];
    $signature = hash_hmac('sha256', json_encode($payload), SECRET_KEY);
    return base64_encode(json_encode($payload)) . '.' . $signature;
}

// New URL format: /live/{token}
// nginx rewrite: rewrite ^/live/(.*)$ /stream.php?token=$1 break;
```

**B. Rate Limiting**
```nginx
# nginx.conf additions
limit_req_zone $binary_remote_addr zone=streaming:10m rate=10r/s;
limit_conn_zone $binary_remote_addr zone=concurrent_streams:10m;

location ~ ^/live/ {
    limit_req zone=streaming burst=20 nodelay;
    limit_conn concurrent_streams 5;
}
```

**C. IP Whitelisting per Stream**
- [ ] Add allowed_ips column to streams table
- [ ] Implement IP validation in stream.php
- [ ] Add IP management UI

---

### 4. Nginx Security Hardening 🔒

#### Headers to Add
```nginx
# Security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';" always;

# Hide version information
server_tokens off;
more_clear_headers Server;

# DDoS protection
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;
location = /index.php {
    limit_req zone=login burst=3 nodelay;
}
```

**Implementation Tasks:**
- [ ] Add headers-more-nginx-module to build
- [ ] Configure SSL/TLS with modern ciphers (if used)
- [ ] Implement request size limits
- [ ] Add connection throttling

---

### 5. PHP Security Configuration ⚙️

#### php.ini Security Settings
```ini
; Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1  ; If using HTTPS
session.cookie_samesite = "Strict"
session.use_strict_mode = 1
session.use_only_cookies = 1

; Hide PHP version
expose_php = Off

; File upload restrictions
file_uploads = On
upload_max_filesize = 10M
max_file_uploads = 5

; Error handling
display_errors = Off
log_errors = On
error_reporting = E_ALL
```

**Note**: Some functions (exec, etc.) are needed for ffmpeg. Use sudo with specific commands instead.

---

### 6. CSRF Protection 🎯

#### Implementation
```php
// functions.php - Add CSRF helper
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}

// Add to all forms
<input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

// Validate in POST handlers
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die('CSRF validation failed');
}
```

**Files to Update:**
- [ ] All forms in manage_*.php
- [ ] settings.php
- [ ] stream_importer.php

---

### 7. Input Validation & Sanitization 🧹

#### Create Validation Middleware
```php
// lib/Validator.php
class Validator {
    public static function cleanString($input, $maxLength = 255) {
        return substr(strip_tags(trim($input)), 0, $maxLength);
    }

    public static function validateIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP);
    }

    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    public static function sanitizeStreamURL($url) {
        // Prevent file:// and other dangerous protocols
        $parsed = parse_url($url);
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https', 'rtmp', 'rtmps'])) {
            return false;
        }
        return $url;
    }
}
```

---

### 8. Logging & Monitoring 📊

#### Enhanced Logging
```php
// lib/Logger.php
class SecurityLogger {
    public static function logAuthAttempt($username, $success, $ip) {
        $log = sprintf(
            "[%s] Auth %s - User: %s, IP: %s\n",
            date('Y-m-d H:i:s'),
            $success ? 'SUCCESS' : 'FAILED',
            $username,
            $ip
        );
        file_put_contents('/home/fos-streaming/fos/logs/auth.log', $log, FILE_APPEND);
    }

    public static function logStreamAccess($userId, $streamId, $ip) {
        // Log to database for analytics
        Activity::create([
            'user_id' => $userId,
            'stream_id' => $streamId,
            'ip_address' => $ip,
            'action' => 'stream_access',
            'timestamp' => time()
        ]);
    }
}
```

---

## Testing Strategy

### 1. Syntax Validation
```bash
# PHP 8.4 syntax check all files
find . -name "*.php" -exec php -l {} \;

# Check for deprecated functions
grep -r "mysql_" *.php  # Should be none (using Eloquent)
grep -r "md5(" *.php    # Should be replaced
```

### 2. Database Testing
```sql
-- Test MariaDB 11.x compatibility
-- Backup first!
mysqldump -u root -p fos > backup_$(date +%Y%m%d).sql

-- Test SQL mode
SET GLOBAL sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Test all stored procedures/triggers (if any)
```

### 3. Stream Testing
- [ ] Test RTMP ingest
- [ ] Test HLS playback
- [ ] Test transcoding profiles
- [ ] Test concurrent streams
- [ ] Load testing with simulated viewers

### 4. Security Testing
```bash
# Run security scanners
nikto -h http://localhost:7777
sqlmap -u "http://localhost:7777/stream.php?stream=1" --batch

# PHP security audit
./vendor/bin/psalm --init
./vendor/bin/psalm
```

---

## Rollback Plan

### Quick Rollback Steps
1. Keep Debian 11 script available
2. Backup database before migration
3. Keep old PHP 7.3 packages in cache
4. Document all config changes
5. Create VM snapshot before migration

### Backup Checklist
- [ ] Database: `mysqldump -u root -p fos > backup.sql`
- [ ] Config files: `/home/fos-streaming/fos/`
- [ ] Nginx config: `/home/fos-streaming/fos/nginx/conf/`
- [ ] Custom modifications
- [ ] Stream data: `/home/fos-streaming/fos/www/hl/`

---

## Implementation Progress

### Legend
- ✅ Completed
- 🔄 In Progress
- 📝 Planned
- ⚠️ Blocked
- ❌ Cancelled

### Current Status: 🔄 Phase 1 - Repository Analysis

| Task | Status | Notes |
|------|--------|-------|
| Create migration plan | ✅ | This document |
| Clone fospackv69 repo | 🔄 | In progress |
| Analyze nginx-builder | 📝 | Pending |
| Create debian12 script | 📝 | Pending |
| Update PHP configs | 📝 | Pending |
| Security improvements | 📝 | Pending |
| Testing | 📝 | Pending |
| Documentation | 📝 | Pending |

---

## Dependencies & External Resources

### Repositories
- **fospackv69**: https://github.com/theraw/fospackv70.git
- **FOS-Streaming-v70**: https://github.com/theraw/FOS-Streaming-v70
- **nginx source**: http://nginx.org/download/
- **nginx-rtmp-module**: https://github.com/arut/nginx-rtmp-module
- **nginx-geoip2**: https://github.com/leev/ngx_http_geoip2_module

### Package Sources
- **Sury PHP**: https://packages.sury.org/php/
- **MariaDB**: https://mariadb.org/download/
- **FFmpeg Static**: https://johnvansickle.com/ffmpeg/

---

## Notes & Considerations

1. **FFmpeg sudo access**: Required for stream management. Keep current sudoers approach but limit to specific binaries.

2. **Session handling**: Currently uses PHP sessions. Consider Redis for multi-server setups.

3. **Database performance**: MariaDB 11.x has improved performance. Monitor after upgrade.

4. **PHP 8.4 JIT**: Can significantly improve performance. Configure opcache properly.

5. **Backward compatibility**: Keep debian11 script for users not ready to upgrade.

6. **Zero-downtime migration**: Not possible with this architecture. Plan maintenance window.

---

## Contact & Support

For issues or questions during migration:
- Review this document
- Check GitHub issues
- Test in staging environment first

---

**Document Version**: 1.0
**Author**: Migration Team
**Review Date**: 2025-11-21
