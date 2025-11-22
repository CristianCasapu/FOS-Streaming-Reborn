# Nginx HTTP-FLV Module Migration Guide

**Date:** 2025-11-21
**FOS-Streaming Version:** v70.0.0

## Overview

FOS-Streaming has been upgraded from `nginx-rtmp-module` to `nginx-http-flv-module`, a more actively maintained and feature-rich alternative.

## Why the Change?

### nginx-rtmp-module (arut)
- **Last update:** December 24, 2024
- **Version:** 1.1.7.11-dev
- **Status:** Still maintained but less active

### nginx-http-flv-module (winshining) ✅
- **Last update:** November 3, 2025
- **Status:** Actively maintained with regular updates
- **Key advantage:** Includes ALL nginx-rtmp-module features PLUS additional enhancements

## New Features Available

The nginx-http-flv-module provides everything nginx-rtmp-module had, plus:

1. **HTTP-FLV Streaming**
   - Stream FLV over HTTP (more firewall-friendly)
   - Better compatibility with modern CDNs
   - Works better through corporate firewalls

2. **GOP Cache**
   - Improves playback startup time
   - Reduces buffering for viewers
   - Better user experience

3. **VHost Support**
   - Multiple domains on a single IP
   - Better multi-tenant support

4. **JSON-Style Statistics**
   - Modern API for statistics
   - Easier integration with monitoring tools

## Compatibility

- **Nginx versions:** 1.2.6+ (tested with 1.26.2)
- **Backward compatible:** All existing RTMP configurations work as-is
- **No breaking changes:** Your existing streaming setup will continue to work

## What Changed

### Build Scripts Updated

#### [fospackv69/nginx-builder/build-debian12.sh](fospackv69/nginx-builder/build-debian12.sh#L124)
```bash
# Changed from:
--add-module=../mods/nginx-rtmp-module

# To:
--add-module=../mods/nginx-http-flv-module
```

#### [fospackv69/nginx-builder/build.sh](fospackv69/nginx-builder/build.sh#L41)
```bash
# Changed from:
--add-module=../mods/nginx-rtmp-module

# To:
--add-module=../mods/nginx-http-flv-module
```

### Module Location
- **New module cloned to:** `fospackv69/nginx-builder/mods/nginx-http-flv-module/`
- **Repository:** https://github.com/winshining/nginx-http-flv-module

## Migration Steps

### For New Installations

The build scripts will automatically clone and use nginx-http-flv-module:

```bash
cd /home/casapu/projects/FOS-Streaming-v69/fospackv69/nginx-builder
sudo bash build-debian12.sh
```

### For Existing Installations

1. **Rebuild nginx with the new module:**
   ```bash
   cd /home/casapu/projects/FOS-Streaming-v69/fospackv69/nginx-builder
   sudo bash build-debian12.sh
   ```

2. **Your existing configuration will work as-is** - no changes needed to nginx.conf

3. **Optional: Enable HTTP-FLV** (see below)

## Using HTTP-FLV (Optional)

While your existing RTMP configuration works as-is, you can now also add HTTP-FLV support:

### Add to your nginx.conf:

```nginx
http {
    server {
        listen 8080;

        location /live {
            flv_live on; # Enable HTTP-FLV
            chunked_transfer_encoding on; # Necessary for HTTP-FLV

            add_header 'Access-Control-Allow-Origin' '*' always;
            add_header 'Access-Control-Allow-Credentials' 'true' always;
            add_header 'Access-Control-Allow-Methods' 'GET, OPTIONS' always;
        }
    }
}

rtmp {
    server {
        listen 1935;

        application live {
            live on;
            # Your existing RTMP config continues to work
        }
    }
}
```

### Access your streams:

- **RTMP (existing):** `rtmp://your-server:1935/live/stream-key`
- **HTTP-FLV (new):** `http://your-server:8080/live?app=live&stream=stream-key`

### Players that support HTTP-FLV:
- flv.js (recommended for web)
- VLC
- OBS
- mpv

## Testing

### Verify nginx compilation:

```bash
/home/fos-streaming/fos/nginx/sbin/nginx -V 2>&1 | grep nginx-http-flv-module
```

You should see `--add-module=../mods/nginx-http-flv-module` in the output.

### Test RTMP streaming:

```bash
# Your existing RTMP tests continue to work
ffmpeg -i input.mp4 -c copy -f flv rtmp://localhost:1935/live/test
```

### Test HTTP-FLV (if enabled):

```bash
# View the stream via HTTP-FLV
ffplay "http://localhost:8080/live?app=live&stream=test"
```

## Rollback (If Needed)

If you need to rollback to nginx-rtmp-module:

1. Edit the build scripts and change back to `--add-module=../mods/nginx-rtmp-module`
2. Rebuild nginx

However, this should not be necessary as nginx-http-flv-module is fully backward compatible.

## Additional Resources

- **nginx-http-flv-module Documentation:** https://github.com/winshining/nginx-http-flv-module
- **Original nginx-rtmp-module:** https://github.com/arut/nginx-rtmp-module
- **flv.js Player:** https://github.com/bilibili/flv.js

## Support

If you encounter any issues:
1. Check nginx error logs: `/home/fos-streaming/fos/logs/error.log`
2. Verify the module is compiled: `nginx -V`
3. Test with existing RTMP first before trying HTTP-FLV features

## Summary

✅ **Backward compatible** - All existing RTMP configs work
✅ **More actively maintained** - November 2025 updates
✅ **New features** - HTTP-FLV, GOP cache, VHost support
✅ **Better compatibility** - Works with nginx 1.26.x
✅ **Future-proof** - Active development continues

Your FOS-Streaming platform is now using the most modern and actively maintained RTMP/HTTP-FLV module available!
