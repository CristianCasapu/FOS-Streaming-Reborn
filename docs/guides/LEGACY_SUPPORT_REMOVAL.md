# Legacy Support Removal - FOS-Streaming v70

**Date:** 2025-11-21
**Version:** 70.0.0

## Summary

FOS-Streaming v70 has **removed all legacy support** for older platforms. The project now exclusively supports modern, secure, and actively maintained technologies.

## What Was Removed

### 1. Operating System Support
- ❌ **Debian 11 (Bullseye)** - No longer supported
- ✅ **Debian 12 (Bookworm)** - Only supported version

### 2. PHP Support
- ❌ **PHP 7.x** - All versions removed (EOL, security risks)
- ✅ **PHP 8.4** - Only supported version

### 3. Nginx Versions
- ❌ **Nginx 1.19.x** - Old version removed
- ✅ **Nginx 1.26.x** - Only supported version

### 4. Streaming Module
- ❌ **nginx-rtmp-module** - Less actively maintained
- ✅ **nginx-http-flv-module** - Actively maintained (last update: Nov 2025)

### 5. Files Removed
- `install/debian11` - Debian 11 installation script
- `fospackv69/nginx-builder/build.sh` - Old build script for nginx 1.19.1
- `fospackv69/nginx-builder/nginx-1.19.1/` - Old nginx source directory

## Why This Change?

### Security
- **PHP 7.x is End-of-Life** - No security updates since 2022
- **Modern cryptography** - PHP 8.4 supports Argon2id natively
- **Active maintenance** - All components receive security patches

### Performance
- **PHP 8.4 JIT** - 15-30% performance improvement
- **Nginx 1.26.x** - HTTP/3, better SSL/TLS, improved performance
- **MariaDB 11.4** - Query optimization and better performance

### Maintainability
- **Single platform** - Easier to test, maintain, and support
- **Modern features** - Access to latest PHP 8.4 features
- **Reduced complexity** - No need to maintain compatibility layers

## nginx-http-flv-module Upgrade

### Why the Switch?

| Feature | nginx-rtmp-module | nginx-http-flv-module |
|---------|-------------------|----------------------|
| Last Update | Dec 2024 | **Nov 2025** ✅ |
| RTMP Support | ✅ | ✅ |
| HTTP-FLV | ❌ | ✅ |
| GOP Cache | ❌ | ✅ |
| VHost Support | ❌ | ✅ |
| JSON Stats | ❌ | ✅ |
| Backward Compatible | N/A | **100%** ✅ |

### Benefits

1. **More Actively Maintained**
   - Regular updates in 2025
   - Active bug fixes
   - Community support

2. **Additional Features**
   - HTTP-FLV streaming (firewall-friendly)
   - GOP cache (faster startup)
   - VHost support (multi-tenant)
   - Modern JSON API

3. **Backward Compatible**
   - All existing RTMP configs work
   - No breaking changes
   - Drop-in replacement

## Migration Path (For Reference Only)

> **Note**: These instructions are for historical reference. New installations should use Debian 12 directly.

### If You're Running Debian 11

**Option 1: Fresh Installation (Recommended)**
1. Backup your data
2. Install Debian 12 from scratch
3. Run the Debian 12 installer
4. Restore your data

**Option 2: Upgrade OS**
1. Backup everything
2. Upgrade Debian 11 → Debian 12
3. Run the Debian 12 installer
4. Run security migrations

### If You're Running PHP 7.x

PHP 7.x is **not supported** on Debian 12. You must:
1. Upgrade to Debian 12 (includes PHP 8.4)
2. Run password migration: `php migrate_passwords.php`

## System Requirements (Current)

### Minimum Requirements
- **OS**: Debian 12 (Bookworm) - **Required**
- **PHP**: 8.4.x - **Required**
- **MariaDB**: 11.4.x - **Required**
- **Nginx**: 1.26.x - **Required**
- **FFmpeg**: Latest static build

### Not Supported
- Debian 11 and earlier
- PHP 7.x and earlier
- Nginx 1.19.x and earlier
- nginx-rtmp-module (use nginx-http-flv-module)

## Technical Details

### Removed Build Scripts

**Old build.sh** (removed):
```bash
# Built nginx 1.19.1 with nginx-rtmp-module
# Location: fospackv69/nginx-builder/build.sh
```

**New build-debian12.sh** (current):
```bash
# Builds nginx 1.26.2 with nginx-http-flv-module
# Location: fospackv69/nginx-builder/build-debian12.sh
```

### Module Configuration Change

**Before:**
```nginx
# compile with:
--add-module=../mods/nginx-rtmp-module
```

**After:**
```nginx
# compile with:
--add-module=../mods/nginx-http-flv-module
```

### Nginx Configuration (No Change Required)

Your existing RTMP configuration works as-is:

```nginx
rtmp {
    server {
        listen 1935;

        application live {
            live on;
            # All your existing config continues to work
        }
    }
}
```

**Optional**: Add HTTP-FLV support:

```nginx
http {
    server {
        listen 8080;

        location /live {
            flv_live on;
            chunked_transfer_encoding on;
        }
    }
}
```

## Documentation Updates

All documentation has been updated to reflect Debian 12-only support:

- ✅ [README.md](README.md) - Removed Debian 11 sections
- ✅ [CHANGELOG.md](CHANGELOG.md) - Added breaking change notice
- ✅ [NGINX_HTTP_FLV_MIGRATION.md](NGINX_HTTP_FLV_MIGRATION.md) - Module migration guide
- ✅ [composer.json](composer.json) - Updated keywords

## Impact on Users

### Current Users (Debian 12)
- ✅ **No impact** - Continue using as normal
- ✅ **New features available** - HTTP-FLV streaming
- ✅ **Better security** - Latest packages

### Current Users (Debian 11)
- ⚠️ **No new updates** - Debian 11 frozen
- ⚠️ **Security risk** - PHP 7.x is EOL
- 📋 **Action required** - Plan migration to Debian 12

### New Users
- ✅ **Simple choice** - Only one platform
- ✅ **Latest tech** - Modern stack
- ✅ **Better docs** - Focused documentation

## Support Policy

### Supported
- Debian 12 (Bookworm)
- PHP 8.4.x
- Nginx 1.26.x
- MariaDB 11.4.x
- nginx-http-flv-module

### Not Supported
- Any Debian version before 12
- Any PHP version before 8.4
- Any Nginx version before 1.26
- nginx-rtmp-module (use nginx-http-flv-module)

### Community Support
- GitHub Issues: For Debian 12 only
- Bug Reports: Debian 12 platform only
- Feature Requests: Based on Debian 12 capabilities

## FAQ

### Q: Can I still use Debian 11?
**A**: No. Debian 11 support has been removed. You must upgrade to Debian 12.

### Q: What about PHP 7.4?
**A**: PHP 7.4 reached EOL in November 2022. It's a security risk and is not supported.

### Q: Will nginx-rtmp-module still work?
**A**: The old module may work but is not supported. Use nginx-http-flv-module instead.

### Q: Do I need to change my nginx config?
**A**: No. nginx-http-flv-module is 100% backward compatible with nginx-rtmp-module.

### Q: Can I run this on Ubuntu?
**A**: Not officially supported. Debian 12 only. Ubuntu may work but is untested.

### Q: What if I need Debian 11 support?
**A**: Use an older version of FOS-Streaming (v69 or earlier). These are no longer maintained.

### Q: Is HTTP-FLV mandatory?
**A**: No. HTTP-FLV is optional. RTMP continues to work exactly as before.

### Q: Will you add Debian 11 support back?
**A**: No. Moving forward with modern platforms only.

## Timeline

- **2025-11-21**: Legacy support removed
- **2025-11-21**: nginx-http-flv-module adopted
- **Future**: Debian 12 only, PHP 8.4+, modern tech stack

## Conclusion

FOS-Streaming v70 represents a commitment to:
- ✅ **Security first** - Modern, patched software
- ✅ **Performance** - Latest optimizations
- ✅ **Maintainability** - Single, focused platform
- ✅ **Future-proof** - Actively maintained components

The removal of legacy support allows the project to move forward with confidence, focusing on innovation rather than maintaining outdated platforms.

---

**For questions or support:**
- GitHub Issues: https://github.com/theraw/FOS-Streaming-v70/issues
- Documentation: See [README.md](README.md)

**Copyright © 2025 FOS-Streaming. All Rights Reserved.**
