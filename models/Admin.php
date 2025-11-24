<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admin extends Model
{
    use SoftDeletes;

    protected $table = 'admins';

    protected $fillable = [
        'username',
        'email',
        'password',
        'role',
        'permissions',
        'restrictions',
        'tfa_enabled',
        'tfa_secret',
        'tfa_enabled_at',
        'full_name',
        'phone',
        'status',
        'last_login',
        'login_count',
        'password_changed_at',
        'force_password_change',
        'last_ip',
        'two_factor_enabled',
        'two_factor_secret',
        'api_token',
        'api_token_expires_at',
        'notes',
        'created_by'
    ];

    protected $hidden = [
        'password',
        'tfa_secret',
        'two_factor_secret',
        'api_token'
    ];

    protected $casts = [
        'permissions' => 'array',
        'restrictions' => 'array',
        'tfa_enabled' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'force_password_change' => 'boolean',
        'login_count' => 'integer',
        'tfa_enabled_at' => 'datetime',
        'last_login' => 'datetime',
        'password_changed_at' => 'datetime',
        'api_token_expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Check if admin has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Super admin has all permissions
        if ($this->role === 'admin') {
            return true;
        }

        // Check in permissions array
        if ($this->permissions && in_array($permission, $this->permissions)) {
            return true;
        }

        return false;
    }

    /**
     * Check if admin has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if admin is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(string $ip): void
    {
        $this->last_login = now();
        $this->last_ip = $ip;
        $this->login_count++;
        $this->save();
    }
}