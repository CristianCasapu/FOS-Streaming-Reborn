<?php
/**
 * FailedLoginAttempt Model
 * Tracks failed login attempts for security monitoring
 */
class FailedLoginAttempt extends FosStreaming {

    protected $table = 'failed_login_attempts';

    protected $fillable = [
        'ip_address',
        'username',
        'user_agent',
        'attempt_time',
        'reason'
    ];

    /**
     * Record a failed login attempt
     */
    public static function record($ipAddress, $username = null, $reason = 'Invalid credentials')
    {
        return self::create([
            'ip_address' => $ipAddress,
            'username' => $username,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'attempt_time' => date('Y-m-d H:i:s'),
            'reason' => $reason
        ]);
    }

    /**
     * Get recent attempts for an IP
     */
    public static function getRecentAttempts($ipAddress, $minutes = 15)
    {
        return self::where('ip_address', $ipAddress)
            ->where('attempt_time', '>', date('Y-m-d H:i:s', strtotime("-{$minutes} minutes")))
            ->count();
    }

    /**
     * Clean up old records
     */
    public static function cleanup($days = 30)
    {
        return self::where('attempt_time', '<', date('Y-m-d H:i:s', strtotime("-{$days} days")))
            ->delete();
    }
}
