# Admin Path Configuration

## Overview

FOS Streaming v70 uses a configurable admin path to separate and isolate the administrative interface from subscriber access paths. This enhances security by obscuring the admin panel location and preventing unauthorized access attempts.

## Configuration

### Environment Variable

The admin path is configured via the `.env` file:

```env
ADMIN_PATH=/admin
```

You can change this to any path you prefer:
```env
ADMIN_PATH=/control-panel
ADMIN_PATH=/management
ADMIN_PATH=/secret-admin-xyz
```

**Security Recommendation**: Use a non-obvious path that's difficult to guess. Avoid common paths like `/admin`, `/administrator`, `/wp-admin`, etc. in production.

## URL Structure

### Admin Routes
- **Admin Panel**: `http://yoursite.com/admin` (or your custom path)
- **Admin API**: `http://yoursite.com/admin/api/`
- **Login**: `http://yoursite.com/admin#/login`
- **Dashboard**: `http://yoursite.com/admin#/dashboard`

### Public Routes
- **Homepage**: `http://yoursite.com/`
- **Subscriber Portal**: `http://yoursite.com/` (to be implemented)
- **Playlist Downloads**: `http://yoursite.com/get.php` (legacy routes remain accessible)

## Security Features

### 1. Path Isolation
The admin interface is completely isolated from public routes. Subscribers accessing playlist downloads will never interact with admin routes.

### 2. Middleware Protection
All admin API endpoints include security middleware:
- **Path validation**: Ensures requests come from the admin path
- **Rate limiting**: Prevents brute-force login attempts (5 attempts per 15 minutes by default)
- **Security logging**: Logs all authentication events to `storage/logs/security.log`
- **CSRF protection**: Validates tokens on state-changing requests

### 3. Rate Limiting Configuration
Adjust rate limiting in `.env`:
```env
MAX_LOGIN_ATTEMPTS=5    # Maximum failed login attempts
LOGIN_TIMEOUT=900       # Timeout in seconds (15 minutes)
```

### 4. Security Logging
Enable/disable security event logging:
```env
SECURITY_LOGGING=true
```

Logs are stored in: `storage/logs/security.log`

## Implementation Details

### Frontend (Vue.js)
- Base path configured in: `resources/js/config.js`
- Router uses hash history: `/admin#/login`, `/admin#/dashboard`
- API calls automatically prefixed with admin path

### Backend (PHP)
- Admin route detection in: `public/index.php`
- Admin APIs located in: `public/admin/api/`
- Security middleware in: `public/admin/api/middleware.php`

## Development

### Testing Different Paths

1. Update `.env`:
   ```env
   ADMIN_PATH=/new-path
   ```

2. Update `resources/js/config.js`:
   ```javascript
   export const ADMIN_PATH = '/new-path';
   ```

3. Restart servers:
   ```bash
   # Terminal 1
   npm run dev

   # Terminal 2
   php artisan serve
   ```

4. Access admin panel:
   ```
   http://localhost:8001/new-path
   ```

## Production Deployment

### Apache (.htaccess)
```apache
# Redirect old admin paths to 404
RedirectMatch 404 ^/wp-admin
RedirectMatch 404 ^/administrator

# Let public/index.php handle admin routing
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# Block common admin paths
location ~ ^/(wp-admin|administrator) {
    return 404;
}
```

## Security Best Practices

1. **Change the default admin path** before deploying to production
2. **Use HTTPS** in production to encrypt authentication traffic
3. **Enable rate limiting** to prevent brute-force attacks
4. **Monitor security logs** regularly for suspicious activity
5. **Use strong passwords** for all admin accounts
6. **Consider IP whitelisting** for admin access if applicable

## Troubleshooting

### Admin panel returns 404
- Check `ADMIN_PATH` in `.env` matches your URL
- Verify `server.php` router is being used (check `php artisan serve` output)
- Ensure `public/index.php` includes admin path routing logic

### API calls fail
- Verify API paths include admin prefix: `/admin/api/auth.php`
- Check browser console for CORS errors
- Ensure `resources/js/config.js` has correct `ADMIN_PATH`

### Rate limiting too strict
- Adjust `MAX_LOGIN_ATTEMPTS` and `LOGIN_TIMEOUT` in `.env`
- Clear rate limit cache: `rm cache/login_attempts_*`

## Files Modified

- `.env` - Admin path configuration
- `public/index.php` - Admin route detection
- `public/admin/api/auth.php` - Authentication API
- `public/admin/api/middleware.php` - Security middleware
- `resources/js/config.js` - Frontend configuration
- `resources/js/services/api.js` - API path configuration
- `server.php` - Development server router

## Future Enhancements

- [ ] IP-based access control
- [ ] Two-factor authentication (2FA)
- [ ] Session timeout configuration
- [ ] Admin activity logging
- [ ] Geo-blocking for admin access
