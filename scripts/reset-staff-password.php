#!/usr/bin/env php
<?php

/**
 * Staff Password Reset Script
 * 
 * This script allows resetting staff user passwords by user ID.
 * Usage: composer reset-staff-password <user_id>
 * 
 * @author FOS-Streaming Development Team
 * @version 1.0.0
 */

// Prevent loading multiple times
if (defined('FOS_STAFF_RESET_LOADED')) {
    return;
}
define('FOS_STAFF_RESET_LOADED', true);

require __DIR__ . '/../config.php';

use App\Models\Staff;
use Carbon\Carbon;

/**
 * Display usage information
 */
function showUsage()
{
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║              FOS-Streaming Staff Password Reset               ║\n";
    echo "╠════════════════════════════════════════════════════════════════╣\n";
    echo "║  Usage: composer reset-staff-password <user_id>               ║\n";
    echo "║                                                                ║\n";
    echo "║  Example:                                                     ║\n";
    echo "║    composer reset-staff-password 1                            ║\n";
    echo "║    composer reset-staff-password 123                          ║\n";
    echo "║                                                                ║\n";
    echo "║  This will:                                                    ║\n";
    echo "║    • Generate a secure random password                        ║\n";
    echo "║    • Reset the staff member's password                        ║\n";
    echo "║    • Force password change on next login                      ║\n";
    echo "║    • Log the activity for audit purposes                      ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
}

/**
 * Display success message with new credentials
 */
function showSuccess($staff, $newPassword)
{
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                   PASSWORD RESET SUCCESS                      ║\n";
    echo "╠════════════════════════════════════════════════════════════════╣\n";
    echo "║  Staff ID: " . str_pad($staff->id, 47) . "║\n";
    echo "║  Username: " . str_pad($staff->username, 45) . "║\n";
    echo "║  Full Name: " . str_pad($staff->full_name ?? 'N/A', 43) . "║\n";
    echo "║  Role: " . str_pad($staff->role, 48) . "║\n";
    echo "║  Status: " . str_pad($staff->status, 47) . "║\n";
    echo "╠════════════════════════════════════════════════════════════════╣\n";
    echo "║                    NEW CREDENTIALS                            ║\n";
    echo "╠════════════════════════════════════════════════════════════════╣\n";
    echo "║  New Password: " . $newPassword . "\n";
    echo "╠════════════════════════════════════════════════════════════════╣\n";
    echo "║  ⚠ SECURITY NOTICE:                                          ║\n";
    echo "║  • Share the new password securely with the staff member     ║\n";
    echo "║  • The staff member will be forced to change their password  ║\n";
    echo "║  • This action has been logged for audit purposes            ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
}

/**
 * Display error message
 */
function showError($message)
{
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                        ERROR                                  ║\n";
    echo "╠════════════════════════════════════════════════════════════════╣\n";
    echo "║  " . $message . "\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
}

/**
 * Validate user ID
 */
function validateUserId($userId)
{
    if (!is_numeric($userId) || $userId <= 0) {
        showError("Invalid user ID. Please provide a valid positive integer.");
        return false;
    }
    
    return true;
}

/**
 * Main execution function
 */
function resetStaffPassword($userId)
{
    try {
        // Validate user ID
        if (!validateUserId($userId)) {
            exit(1);
        }

        // Find staff member
        $staff = Staff::findById($userId);
        
        if (!$staff) {
            showError("Staff member with ID {$userId} not found.");
            exit(1);
        }

        // Check if staff member is active
        if ($staff->status !== 'active') {
            showError("Staff member '{$staff->username}' is not active (status: {$staff->status}).");
            exit(1);
        }

        // Generate secure password
        $newPassword = Staff::generateSecurePassword(12);
        
        // Reset password
        $success = $staff->resetPassword($newPassword, true);
        
        if (!$success) {
            showError("Failed to reset password for staff member '{$staff->username}'.");
            exit(1);
        }

        // Log the activity
        $staff->logActivity('password_reset', [
            'script' => 'composer reset-staff-password',
            'reset_by' => 'CLI',
            'new_password_length' => strlen($newPassword)
        ]);

        // Display success message
        showSuccess($staff, $newPassword);
        
    } catch (Exception $e) {
        showError("An unexpected error occurred: " . $e->getMessage());
        exit(1);
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    // Check if user ID is provided
    if ($argc < 2) {
        showUsage();
        exit(1);
    }

    $userId = $argv[1];
    
    // Check for help flag
    if (in_array($userId, ['--help', '-h', 'help'])) {
        showUsage();
        exit(0);
    }

    // Execute password reset
    resetStaffPassword($userId);
}