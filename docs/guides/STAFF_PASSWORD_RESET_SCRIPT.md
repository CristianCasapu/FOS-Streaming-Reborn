# Staff Password Reset Script

## Overview

The Staff Password Reset Script is a composer-based utility that allows administrators to reset staff user passwords by their user ID. This script generates secure random passwords and provides comprehensive logging for audit purposes.

## Features

- **Secure Password Generation**: Creates strong 12-character passwords with mixed case, numbers, and special characters
- **MD5 Compatibility**: Uses MD5 hashing to maintain compatibility with the existing staff authentication system
- **Force Password Change**: Automatically forces staff members to change their password on next login
- **Comprehensive Logging**: Logs all password reset activities for audit trail
- **Input Validation**: Validates user IDs and provides helpful error messages
- **User-Friendly Interface**: Displays formatted success/error messages with clear instructions

## Usage

### Basic Usage

```bash
composer reset-staff-password <user_id>
```

### Examples

```bash
# Reset password for staff member with ID 1
composer reset-staff-password 1

# Reset password for staff member with ID 123
composer reset-staff-password 123
```

### Help

```bash
# Show usage information
composer reset-staff-password --help
```

## How It Works

1. **Validation**: The script first validates that the provided user ID is a valid positive integer
2. **User Lookup**: Searches for the staff member with the specified ID in the database
3. **Status Check**: Verifies that the staff member is active (not suspended or inactive)
4. **Password Generation**: Creates a secure 12-character random password
5. **Password Reset**: Updates the staff member's password using MD5 hashing
6. **Force Change Flag**: Sets the `force_password_change` flag to true
7. **Audit Logging**: Records the password reset activity in the audit logs
8. **Success Display**: Shows the new credentials in a formatted table

## Password Format

Generated passwords include:
- Uppercase letters (A-Z)
- Lowercase letters (a-z)
- Numbers (0-9)
- Special characters (!@#$%^&*)

Example generated password: `aB3!xK9@mN4p`

## Security Features

### Input Validation
- Validates user ID format (must be positive integer)
- Checks staff member existence before attempting password reset
- Verifies staff member status (must be active)

### Audit Trail
- Logs password reset activity with timestamp
- Records the staff member ID and username
- Captures execution context (CLI, script name)
- Stores additional details (new password length)

### Error Handling
- Graceful error handling with user-friendly messages
- Proper exit codes for automation/scripts
- Comprehensive exception handling

## Output Format

### Success Output
```
╔════════════════════════════════════════════════════════════════╗
║                   PASSWORD RESET SUCCESS                      ║
╠════════════════════════════════════════════════════════════════╣
║  Staff ID: 1                                              ║
║  Username: admin                                           ║
║  Full Name: System Administrator                           ║
║  Role: admin                                               ║
║  Status: active                                            ║
╠════════════════════════════════════════════════════════════════╣
║                    NEW CREDENTIALS                            ║
╠════════════════════════════════════════════════════════════════╣
║  New Password: aB3!xK9@mN4p
╠════════════════════════════════════════════════════════════════╣
║  ⚠ SECURITY NOTICE:                                          ║
║  • Share the new password securely with the staff member     ║
║  • The staff member will be forced to change their password  ║
║  • This action has been logged for audit purposes            ║
╚════════════════════════════════════════════════════════════════╝
```

### Error Output
```
╔════════════════════════════════════════════════════════════════╗
║                        ERROR                                  ║
╠════════════════════════════════════════════════════════════════╣
║  Staff member with ID 123 not found.
╚════════════════════════════════════════════════════════════════╝
```

## Database Requirements

### Staff Table
The script works with the existing `staff` table structure:

- `id` (primary key)
- `username` (unique)
- `password` (MD5 hashed)
- `status` (enum: active, inactive, suspended)
- `full_name`
- `role`
- `password_changed_at`
- `force_password_change`

### Audit Logs (Optional)
If the `audit_logs` table exists, the script will log activities there. If not, it will silently continue without logging.

## Implementation Details

### Files Created
1. **`app/Models/Staff.php`** - Eloquent model for staff operations
2. **`scripts/reset-staff-password.php`** - Main script file
3. **Updated `composer.json`** - Added script definition

### Key Methods
- `Staff::findById($id)` - Find staff member by ID
- `Staff::generateSecurePassword($length)` - Generate secure random password
- `$staff->resetPassword($newPassword, $forceChange)` - Reset staff password
- `$staff->logActivity($action, $details)` - Log audit activity

## Troubleshooting

### Common Issues

1. **"Staff member not found"**
   - Verify the user ID exists in the staff table
   - Check if the staff member was deleted

2. **"Staff member is not active"**
   - Staff member status must be 'active'
   - Check staff member status in database

3. **Database connection errors**
   - Verify database configuration in `.env`
   - Ensure database server is running
   - Check database credentials

4. **Permission errors**
   - Ensure the script has read access to configuration files
   - Verify database user has UPDATE permissions on staff table

### Testing

Test the script with various scenarios:

```bash
# Test help functionality
php scripts/reset-staff-password.php --help

# Test invalid user ID
php scripts/reset-staff-password.php abc

# Test non-existent user
php scripts/reset-staff-password.php 999

# Test valid user (if exists)
php scripts/reset-staff-password.php 1
```

## Integration

### Composer Scripts
The script is registered in `composer.json` under the `scripts` section:

```json
"reset-staff-password": "@php scripts/reset-staff-password.php"
```

### Automation
This script can be integrated into deployment scripts or admin panels:

```bash
# In deployment scripts
composer reset-staff-password $STAFF_ID

# In admin panels (via system calls)
exec("composer reset-staff-password " . $userId);
```

## Security Considerations

1. **Password Storage**: Uses MD5 hashing for compatibility with existing system
2. **Audit Trail**: All password resets are logged for compliance
3. **Access Control**: Should be used only by authorized administrators
4. **Password Sharing**: New passwords should be shared securely with staff members
5. **Force Change**: Staff members are forced to change password on next login

## Future Enhancements

Potential improvements for future versions:

1. **Email Integration**: Send new passwords via secure email
2. **Two-Factor Authentication**: Integration with 2FA systems
3. **Password Policy**: Customizable password requirements
4. **Batch Operations**: Reset multiple staff passwords at once
5. **Web Interface**: Admin panel integration
6. **Advanced Logging**: More detailed audit information

## Support

For issues or questions about the Staff Password Reset Script:

1. Check this documentation for common solutions
2. Review error messages for specific guidance
3. Test with different user IDs to isolate the issue
4. Verify database connectivity and staff table structure

## Version History

- **v1.0.0** - Initial implementation with basic password reset functionality
  - Secure password generation
  - MD5 hashing compatibility
  - Audit logging
  - Comprehensive error handling
  - User-friendly interface