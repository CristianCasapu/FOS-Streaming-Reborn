<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Staff Model
 * 
 * Represents staff members (administrators/operators) in the FOS-Streaming platform
 * with role-based access control (RBAC) features.
 */
class Staff extends Model
{
    protected $table = 'staff';
    public $timestamps = true;
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'permissions',
        'restrictions',
        'full_name',
        'phone',
        'status',
        'last_login',
        'login_count',
        'last_ip',
        'password_changed_at',
        'force_password_change',
        'tfa_enabled',
        'tfa_secret',
        'tfa_enabled_at',
        'two_factor_enabled',
        'two_factor_secret',
        'api_token',
        'api_token_expires_at',
        'notes',
        'created_by'
    ];

    protected $dates = [
        'last_login',
        'password_changed_at',
        'tfa_enabled_at',
        'api_token_expires_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'permissions' => 'array',
        'restrictions' => 'array',
        'login_count' => 'integer',
        'force_password_change' => 'boolean',
        'tfa_enabled' => 'boolean',
        'two_factor_enabled' => 'boolean'
    ];

    /**
     * Find staff member by ID
     * 
     * @param int $id
     * @return Staff|null
     */
    public static function findById($id)
    {
        return static::find($id);
    }

    /**
     * Find staff member by username
     * 
     * @param string $username
     * @return Staff|null
     */
    public static function findByUsername($username)
    {
        return static::where('username', $username)->first();
    }

    /**
     * Reset staff member password
     * 
     * @param string $newPassword Plain text password
     * @param bool $forceChange Whether to force password change on next login
     * @return bool Success status
     */
    public function resetPassword($newPassword, $forceChange = true)
    {
        $hashedPassword = md5($newPassword);
        
        $updateData = [
            'password' => $hashedPassword,
            'password_changed_at' => date('Y-m-d H:i:s')
        ];

        if ($forceChange) {
            $updateData['force_password_change'] = true;
        }

        return $this->update($updateData);
    }

    /**
     * Generate a secure random password
     * 
     * @param int $length Password length
     * @return string Generated password
     */
    public static function generateSecurePassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }

    /**
     * Check if staff member has specific permission
     * 
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        if (!$this->permissions) {
            return false;
        }

        return in_array($permission, $this->permissions);
    }

    /**
     * Check if staff member has specific role
     * 
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Get all active staff members
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getActiveStaff()
    {
        return static::where('status', 'active')->get();
    }

    /**
     * Log password reset activity
     * 
     * @param string $action Action performed
     * @param array $details Additional details
     * @return void
     */
    public function logActivity($action, $details = [])
    {
        $logData = [
            'staff_id' => $this->id,
            'username' => $this->username,
            'action' => $action,
            'details' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Composer Script',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Insert into audit logs if table exists
        try {
            Capsule::table('audit_logs')->insert($logData);
        } catch (\Exception $e) {
            // Silently fail if audit_logs table doesn't exist
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
