# Sudo Password & System Commands Feature

**Date:** 2025-11-22
**Version:** FOS-Streaming v70
**Feature:** Secure sudo password storage and system command execution from web interface

---

## Overview

This feature allows administrators to securely store sudo credentials in the system settings and execute system commands directly from the web interface. This enables automated package installation, service management, and system configuration without SSH access.

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│ Vue.js Settings Component                                    │
│ - Sudo password input (encrypted on save)                   │
│ - System commands enabled toggle                            │
│ - Quick action buttons                                      │
└─────────────────────────────────────────────────────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ Settings API (/public/admin/api/settings.php)              │
│ - Encrypts sudo password with Laravel Crypt                 │
│ - Stores in database                                        │
│ - Test sudo password functionality                          │
└─────────────────────────────────────────────────────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ System Commands API (/public/admin/api/system-commands.php)│
│ - Execute commands                                          │
│ - Install packages                                          │
│ - Restart services                                          │
│ - Get execution logs                                        │
└─────────────────────────────────────────────────────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ SystemCommands Helper (/app/SystemCommands.php)            │
│ - Whitelist-based command validation                       │
│ - Secure sudo password handling                            │
│ - Command execution with audit logging                     │
└─────────────────────────────────────────────────────────────┘
                              ▼
┌─────────────────────────────────────────────────────────────┐
│ SystemCommandLog Model (/models/SystemCommandLog.php)      │
│ - Audit trail for all command executions                   │
│ - Tracks: command, output, exit code, execution time       │
│ - Records: admin ID, IP address, user agent                │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Changes

### Migration File
**Location:** `/database/migrations/add_sudo_password_to_settings.sql`

**New Fields in `settings` table:**
- `sudo_password` (VARCHAR 500) - Encrypted sudo password
- `sudo_user` (VARCHAR 100) - System user for sudo commands (default: 'casapu')
- `system_commands_enabled` (TINYINT 1) - Enable/disable system commands
- `last_command_at` (DATETIME) - Last command execution timestamp

**New Table: `system_command_logs`**
- Comprehensive audit trail for all command executions
- Tracks success/failure, execution time, output
- Includes admin ID, IP address, user agent

### Running the Migration

```bash
# Navigate to project directory
cd /home/casapu/projects/FOS-Streaming-v69

# Run migration
mysql -u fos_dev -p fos_dev < database/migrations/add_sudo_password_to_settings.sql
```

---

## Security Features

### 1. **Password Encryption**
- Sudo password is encrypted using Laravel's `Crypt::encryptString()`
- Uses AES-256-CBC encryption
- Never stored in plain text
- Automatically decrypted only when needed for command execution

### 2. **Command Whitelist**
Only pre-approved commands can be executed. The whitelist includes:

**Package Management:**
- `apt-get update`
- `apt-get install -y ufw`
- `apt-get install -y fail2ban`
- `apt-get remove -y`
- `apt-get upgrade -y`

**UFW Firewall:**
- `ufw status`
- `ufw enable`
- `ufw disable`
- `ufw allow`
- `ufw deny`
- `ufw delete`
- `ufw reload`

**fail2ban:**
- `fail2ban-client status`
- `fail2ban-client start`
- `fail2ban-client stop`
- `fail2ban-client reload`
- `fail2ban-client ban`
- `fail2ban-client unban`

**Service Management:**
- `systemctl start/stop/restart/reload/status`
- `systemctl enable/disable`

**Nginx:**
- `nginx -t`
- `nginx -s reload`

**System Info:**
- `df -h`
- `free -h`
- `uptime`
- `netstat -tlnp`
- `ss -tlnp`

### 3. **Audit Logging**
Every command execution is logged:
- Command executed
- Admin who executed it
- Execution time
- Exit code
- Output
- IP address
- User agent
- Timestamp

### 4. **Enable/Disable Toggle**
System commands can be completely disabled via `system_commands_enabled` flag.

---

## API Endpoints

### Settings API

**Base URL:** `/public/admin/api/settings.php`

#### 1. Get Settings
```http
GET /admin/api/settings.php?action=get
```

**Response:**
```json
{
  "success": true,
  "data": {
    "sudo_user": "casapu",
    "sudo_password_set": true,
    "system_commands_enabled": true,
    "last_command_at": "2025-11-22 10:30:00"
  }
}
```

#### 2. Update Settings (with sudo password)
```http
POST /admin/api/settings.php?action=update
Content-Type: application/json

{
  "sudo_user": "casapu",
  "sudo_password": "secret",
  "system_commands_enabled": true
}
```

**Response:**
```json
{
  "success": true,
  "message": "Settings updated successfully",
  "data": {
    "sudo_password_set": true,
    "system_commands_enabled": true
  }
}
```

#### 3. Test Sudo Password
```http
GET /admin/api/settings.php?action=test_sudo
```

**Response:**
```json
{
  "success": true,
  "message": "Sudo password is working correctly. Current user: casapu",
  "data": {
    "output": "casapu",
    "exit_code": 0,
    "execution_time": 0.123
  }
}
```

#### 4. Clear Sudo Credentials
```http
GET /admin/api/settings.php?action=clear_sudo
```

---

### System Commands API

**Base URL:** `/public/admin/api/system-commands.php`

#### 1. Execute Command
```http
POST /admin/api/system-commands.php?action=execute
Content-Type: application/json

{
  "command": "apt-get install -y ufw",
  "description": "Install UFW firewall"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Command executed successfully",
  "data": {
    "command": "apt-get install -y ufw",
    "output": "Reading package lists...\nBuilding dependency tree...\nufw is already installed",
    "exit_code": 0,
    "execution_time": 1.234
  }
}
```

#### 2. Install Package
```http
POST /admin/api/system-commands.php?action=install_package
Content-Type: application/json

{
  "package": "fail2ban"
}
```

#### 3. Restart Service
```http
POST /admin/api/system-commands.php?action=restart_service
Content-Type: application/json

{
  "service": "nginx"
}
```

#### 4. Get Service Status
```http
POST /admin/api/system-commands.php?action=service_status
Content-Type: application/json

{
  "service": "php8.4-fpm"
}
```

#### 5. Get Command Logs
```http
GET /admin/api/system-commands.php?action=get_logs&limit=50
```

#### 6. Get Quick Actions
```http
GET /admin/api/system-commands.php?action=quick_actions
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "install_ufw",
      "name": "Install UFW Firewall",
      "description": "Install UFW (Uncomplicated Firewall)",
      "command": "apt-get install -y ufw",
      "category": "packages"
    },
    {
      "id": "restart_nginx",
      "name": "Restart Nginx",
      "description": "Restart Nginx web server",
      "command": "systemctl restart nginx",
      "category": "services"
    }
  ]
}
```

---

## Usage Examples

### 1. Store Sudo Password

**Setup:**
1. Navigate to **Settings** page
2. Scroll to **System Commands** section
3. Enter sudo user: `casapu`
4. Enter sudo password: `secret`
5. Enable "System Commands"
6. Click "Test Password" to verify
7. Save settings

### 2. Install UFW Firewall

**Via API:**
```bash
curl -X POST http://localhost:7777/admin/api/system-commands.php?action=install_package \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"package": "ufw"}'
```

**Expected Result:**
```json
{
  "success": true,
  "message": "Package 'ufw' installed successfully",
  "data": {
    "output": "UFW installed successfully",
    "exit_code": 0
  }
}
```

### 3. Restart Nginx

**Via API:**
```bash
curl -X POST http://localhost:7777/admin/api/system-commands.php?action=restart_service \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"service": "nginx"}'
```

### 4. Check Service Status

**Via API:**
```bash
curl -X POST http://localhost:7777/admin/api/system-commands.php?action=service_status \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"service": "php8.4-fpm"}'
```

---

## Quick Actions Available

The system provides pre-defined quick actions for common tasks:

1. **Install UFW Firewall**
2. **Install fail2ban**
3. **Restart Nginx**
4. **Restart PHP-FPM**
5. **Restart MariaDB**
6. **Enable UFW**
7. **Start fail2ban**
8. **Update Package List**

---

## Command Execution Flow

```
1. Admin submits command via Settings UI
              ↓
2. Vue.js sends request to system-commands API
              ↓
3. API validates admin authentication
              ↓
4. SystemCommands::execute() called
              ↓
5. Validates command against whitelist
              ↓
6. Retrieves and decrypts sudo password
              ↓
7. Builds command: echo 'secret' | sudo -S -p "" <command>
              ↓
8. Executes command and captures output
              ↓
9. Logs execution to system_command_logs table
              ↓
10. Returns result to Vue.js component
              ↓
11. Display success/error message to admin
```

---

## Files Created/Modified

### New Files

1. **`/database/migrations/add_sudo_password_to_settings.sql`**
   - Database migration for sudo password fields

2. **`/models/SystemCommandLog.php`**
   - Eloquent model for command execution logs

3. **`/app/SystemCommands.php`**
   - Helper class for secure command execution

4. **`/public/admin/api/system-commands.php`**
   - API endpoint for system command operations

5. **`/docs/guides/SUDO_PASSWORD_FEATURE.md`**
   - This documentation file

### Modified Files

1. **`/public/admin/api/settings.php`**
   - Added sudo password handling
   - Added test_sudo and clear_sudo actions

---

## Security Best Practices

### ✅ DO:
- Always use encryption for sudo password storage
- Test sudo password after setting it
- Review command logs regularly
- Use specific commands instead of wildcards
- Disable system commands when not needed
- Keep the whitelist updated with only necessary commands

### ❌ DON'T:
- Store sudo password in plain text
- Execute arbitrary commands without whitelist validation
- Share sudo password outside the application
- Disable audit logging
- Execute commands without proper authentication
- Allow commands that can harm the system

---

## Troubleshooting

### Issue: "Sudo password not configured"
**Solution:** Navigate to Settings and configure the sudo password first.

### Issue: "Command not allowed"
**Solution:** The command is not in the whitelist. Add it to the `$allowedCommands` array in `/app/SystemCommands.php`.

### Issue: "System commands are disabled"
**Solution:** Enable system commands in Settings page by toggling the "System Commands Enabled" switch.

### Issue: Command fails with exit code 1
**Solution:**
1. Check the command syntax
2. Verify sudo password is correct
3. Check if user has sudo permissions
4. Review command logs for detailed error message

### Issue: "Failed to encrypt sudo password"
**Solution:** Ensure Laravel encryption key is set in `.env` file (`APP_KEY`).

---

## Future Enhancements

Potential improvements for future versions:

1. **Command Templates** - Pre-configured command templates with variable substitution
2. **Scheduled Commands** - Cron-like scheduling for automated tasks
3. **Command Queue** - Queue system for long-running commands
4. **Real-time Output** - WebSocket-based live command output
5. **Multi-server Support** - Execute commands on multiple servers
6. **Role-based Access** - Different command permissions per admin role
7. **Command Approval Workflow** - Require approval for critical commands

---

## Support

For issues or questions:
- **GitHub Issues:** https://github.com/CristianCasapu/FOS-Streaming-Reborn/issues
- **Documentation:** `/docs/guides/`
- **API Reference:** This document

---

**Status:** ✅ **Implemented and Ready for Testing**

All backend components are complete. Next step is to create the Vue.js Settings component with sudo password fields and system commands UI.
