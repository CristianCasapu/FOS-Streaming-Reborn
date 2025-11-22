# PM2 Manager - Sudo Password Integration Update

## Summary

Updated the PM2 Management UI to use the sudo password stored in the Settings table (encrypted with Laravel Crypt) instead of requiring it in the `.env` file.

**Date:** 2025-11-22
**Version:** 70.0.0

---

## Changes Made

### 1. PM2 API Endpoint

**File:** `/public/admin/api/pm2.php`

**Changes:**

1. **Added Crypt import:**
```php
use Illuminate\Support\Facades\Crypt;
```

2. **Updated service_action case to retrieve password from database:**

**Before:**
```php
// Execute service command with sudo
$sudoPassword = env('SUDO_PASSWORD', '');
if (empty($sudoPassword)) {
    throw new Exception('Sudo password not configured');
}
```

**After:**
```php
// Get sudo password from settings
$setting = Setting::first();
if (!$setting || empty($setting->sudo_password)) {
    throw new Exception('Sudo password not configured. Please set it in Settings → System Commands Configuration.');
}

// Decrypt sudo password
$sudoPassword = Crypt::decryptString($setting->sudo_password);
```

**Benefits:**
- Uses existing settings infrastructure
- Password is encrypted in database (AES-256)
- Same password for system commands and PM2 service control
- No need to manage `.env` variable separately
- Centralized password management

### 2. PM2Manager Vue Component

**File:** `/resources/js/components/PM2Manager.vue`

**Changes:**

Enhanced error handling in `controlService()` method to provide user-friendly message when sudo password is not configured:

```javascript
catch (error) {
    const errorMsg = error.response?.data?.message || error.message;

    // Check if it's a sudo password error
    if (errorMsg.includes('Sudo password not configured')) {
        showMessage('Please configure sudo password in System Commands Configuration section above, then try again.', 'error');
    } else {
        showMessage('Error: ' + errorMsg, 'error');
    }
}
```

**Benefits:**
- Clear guidance to user on how to fix the issue
- Points to the correct section in Settings page
- Better UX with actionable error messages

### 3. Documentation Updates

**File:** `/docs/PM2_MANAGEMENT_UI.md`

**Updated Sections:**

1. **Security Considerations → Sudo Password Handling:**
   - Updated storage location (database vs .env)
   - Added encryption details (AES-256 via Laravel Crypt)
   - Updated retrieval code example
   - Added configuration instructions

2. **Troubleshooting → Service Control Fails:**
   - Added step-by-step solution for "Sudo password not configured" error
   - Clarified configuration process via Settings page
   - Added password testing instructions

---

## How It Works

### Configuration Flow

1. **User configures sudo password:**
   - Navigate to Settings page
   - Scroll to "System Commands Configuration" section
   - Click "Edit" next to Sudo Password
   - Enter sudo password
   - Click "Test" to verify
   - Click "Save Settings" (password encrypted automatically)

2. **Password storage:**
   ```
   Settings Table → sudo_password column → AES-256 encrypted string
   ```

3. **PM2 service control:**
   ```
   User clicks service action
      ↓
   PM2Manager.vue → pm2API.serviceAction()
      ↓
   pm2.php → Retrieve Setting::first()
      ↓
   Check sudo_password field exists
      ↓
   Decrypt: Crypt::decryptString($setting->sudo_password)
      ↓
   Execute: echo '{password}' | sudo -S systemctl {action} {service}
   ```

### Security Features

1. **Encryption at Rest:**
   - Password encrypted with Laravel's Crypt facade
   - Uses AES-256-CBC cipher
   - Encryption key from `APP_KEY` in `.env`

2. **Access Control:**
   - Requires user authentication (logincheck())
   - Only accessible via authenticated admin panel
   - Service whitelist validation
   - Action whitelist validation

3. **Password Testing:**
   - Settings page has "Test" button
   - Verifies password before saving
   - Prevents saving incorrect passwords

---

## Migration from .env

If you previously used `SUDO_PASSWORD` in `.env`:

1. **Configure via Settings page:**
   - The password will be encrypted and stored in database
   - Old `.env` variable is ignored

2. **Remove from .env (optional):**
   ```bash
   # Remove this line from .env
   # SUDO_PASSWORD=your_password
   ```

3. **Benefits of migration:**
   - Centralized configuration
   - Encrypted storage
   - Same password for all sudo operations
   - GUI-based management with testing

---

## Troubleshooting

### Error: "Sudo password not configured"

**Solution:**
1. Go to Settings page (`/admin#/settings`)
2. Scroll to "System Commands Configuration" section
3. Click "Edit" next to Sudo Password field
4. Enter your sudo password
5. Click "Test" to verify it works
6. Click "Save Settings" at bottom of page
7. Return to PM2 Manager section
8. Try the service action again

### Error: "Failed to [action] [service]"

**Possible Causes:**

1. **Incorrect password:**
   - Update password in Settings
   - Test before saving

2. **User lacks sudo privileges:**
   ```bash
   sudo visudo
   # Add line:
   username ALL=(ALL) NOPASSWD: /bin/systemctl
   ```

3. **Service name incorrect:**
   - Check allowed services: nginx, mariadb, php8.4-fpm
   - Verify with: `systemctl list-units --type=service`

### Password decryption fails

**Cause:** `APP_KEY` in `.env` changed after password was encrypted

**Solution:**
1. Cannot decrypt old password
2. Re-configure password via Settings page
3. New password will be encrypted with current APP_KEY

**Prevention:**
- Never change `APP_KEY` in production
- Backup `APP_KEY` before regenerating

---

## Testing

### Test Sudo Password Configuration

1. **Navigate to Settings:**
   ```
   http://your-server/admin#/settings
   ```

2. **Configure Password:**
   - Scroll to "System Commands Configuration"
   - Click "Edit" next to Sudo Password
   - Enter password
   - Click "Test" → Should show success message
   - Click "Save Settings"

3. **Test PM2 Service Control:**
   - Scroll to "PM2 Process Manager" section
   - Click "Restart" next to Nginx
   - Confirm action
   - Should show "Restart nginx successfully"

### Verify Database Storage

```bash
# Connect to database
mysql -u username -p database_name

# Check encrypted password
SELECT id, sudo_password, sudo_user FROM settings;

# Result should show encrypted string (not plaintext)
```

### Verify Service Control Works

```bash
# Check service status before
systemctl status nginx

# Use PM2 Manager to restart nginx

# Check service status after
systemctl status nginx
```

---

## API Examples

### Service Action Request

```javascript
// Frontend
await pm2API.serviceAction('nginx', 'restart');

// POST /admin/api/pm2.php?action=service_action
// Body: { "service": "nginx", "action": "restart" }
```

### Success Response

```json
{
  "success": true,
  "message": "Restart nginx successfully"
}
```

### Error Response (No Password)

```json
{
  "success": false,
  "message": "Sudo password not configured. Please set it in Settings → System Commands Configuration."
}
```

### Error Response (Invalid Password)

```json
{
  "success": false,
  "message": "Failed to restart nginx: sudo: 3 incorrect password attempts"
}
```

---

## Related Files

### Modified Files
- `/public/admin/api/pm2.php` - Backend API endpoint
- `/resources/js/components/PM2Manager.vue` - Frontend component
- `/docs/PM2_MANAGEMENT_UI.md` - Main documentation

### Related Files
- `/public/admin/api/settings.php` - Settings API (password encryption)
- `/models/Setting.php` - Setting model
- `/resources/js/views/Settings/Settings.vue` - Settings page

---

## Benefits of This Approach

### For Users
1. **Single Configuration Point:**
   - All sudo operations use same password
   - Configured once in Settings page
   - GUI-based with testing

2. **Better Security:**
   - Encrypted storage (AES-256)
   - Password not in plaintext
   - Test before committing

3. **Better UX:**
   - Clear error messages
   - Guided troubleshooting
   - Visual feedback

### For Developers
1. **Consistent with Existing Architecture:**
   - Uses existing Settings infrastructure
   - Same pattern as System Commands API
   - Reuses Crypt facade

2. **Maintainable:**
   - Single source of truth (database)
   - No environment variable management
   - Standard Laravel encryption

3. **Extensible:**
   - Easy to add more sudo operations
   - Centralized password management
   - Audit trail in settings table

---

**Last Updated:** 2025-11-22
**Status:** Production Ready
**Breaking Changes:** None (backwards compatible)
