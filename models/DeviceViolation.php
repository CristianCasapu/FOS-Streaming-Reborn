<?php
/**
 * Device Violation Model
 *
 * Tracks and manages security violations related to device usage
 */

class DeviceViolation extends FosStreaming {

    protected $table = 'device_violations';

    protected $fillable = [
        'subscriber_id',
        'subscription_id',
        'device_fingerprint_id',
        'violation_type',
        'severity',
        'details',
        'action_taken',
        'ip_address',
        'device_info'
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime'
    ];

    /**
     * Violation types
     */
    const TYPE_CONCURRENT_LIMIT = 'concurrent_limit';
    const TYPE_LOCATION_MISMATCH = 'location_mismatch';
    const TYPE_DEVICE_MISMATCH = 'device_mismatch';
    const TYPE_SHARING_DETECTED = 'sharing_detected';
    const TYPE_SUSPICIOUS_PATTERN = 'suspicious_pattern';
    const TYPE_IP_JUMP = 'ip_jump';
    const TYPE_RAPID_SWITCHING = 'rapid_switching';

    /**
     * Severity levels
     */
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Actions taken
     */
    const ACTION_LOGGED = 'logged';
    const ACTION_WARNING = 'warning';
    const ACTION_STREAM_BLOCKED = 'stream_blocked';
    const ACTION_DEVICE_BLOCKED = 'device_blocked';
    const ACTION_SUBSCRIPTION_SUSPENDED = 'subscription_suspended';
    const ACTION_ACCOUNT_BANNED = 'account_banned';

    /**
     * Get the subscriber associated with this violation
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    /**
     * Get the subscription associated with this violation
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the device fingerprint associated with this violation
     */
    public function deviceFingerprint()
    {
        return $this->belongsTo(DeviceFingerprint::class, 'device_fingerprint_id');
    }

    /**
     * Check if violation is critical
     */
    public function isCritical()
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Check if action was taken
     */
    public function hasActionTaken()
    {
        return $this->action_taken !== self::ACTION_LOGGED;
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColorAttribute()
    {
        return [
            self::SEVERITY_LOW => 'green',
            self::SEVERITY_MEDIUM => 'yellow',
            self::SEVERITY_HIGH => 'orange',
            self::SEVERITY_CRITICAL => 'red'
        ][$this->severity] ?? 'gray';
    }

    /**
     * Get human-readable violation type
     */
    public function getViolationTypeTextAttribute()
    {
        return [
            self::TYPE_CONCURRENT_LIMIT => 'Concurrent Stream Limit Exceeded',
            self::TYPE_LOCATION_MISMATCH => 'Location Mismatch Detected',
            self::TYPE_DEVICE_MISMATCH => 'Device Mismatch Detected',
            self::TYPE_SHARING_DETECTED => 'Account Sharing Detected',
            self::TYPE_SUSPICIOUS_PATTERN => 'Suspicious Activity Pattern',
            self::TYPE_IP_JUMP => 'Rapid IP Address Change',
            self::TYPE_RAPID_SWITCHING => 'Rapid Device Switching'
        ][$this->violation_type] ?? 'Unknown Violation';
    }

    /**
     * Get action taken text
     */
    public function getActionTakenTextAttribute()
    {
        return [
            self::ACTION_LOGGED => 'Logged Only',
            self::ACTION_WARNING => 'Warning Issued',
            self::ACTION_STREAM_BLOCKED => 'Stream Blocked',
            self::ACTION_DEVICE_BLOCKED => 'Device Blocked',
            self::ACTION_SUBSCRIPTION_SUSPENDED => 'Subscription Suspended',
            self::ACTION_ACCOUNT_BANNED => 'Account Banned'
        ][$this->action_taken] ?? 'Unknown Action';
    }

    /**
     * Take action based on violation
     */
    public function takeAction($action = null)
    {
        if (!$action) {
            $action = $this->determineAction();
        }

        switch ($action) {
            case self::ACTION_STREAM_BLOCKED:
                $this->blockStream();
                break;

            case self::ACTION_DEVICE_BLOCKED:
                $this->blockDevice();
                break;

            case self::ACTION_SUBSCRIPTION_SUSPENDED:
                $this->suspendSubscription();
                break;

            case self::ACTION_ACCOUNT_BANNED:
                $this->banAccount();
                break;

            case self::ACTION_WARNING:
                $this->issueWarning();
                break;
        }

        $this->action_taken = $action;
        $this->save();

        return $this;
    }

    /**
     * Determine appropriate action based on severity and history
     */
    private function determineAction()
    {
        // Get violation count in the last 24 hours
        $recentViolations = static::where('subscriber_id', $this->subscriber_id)
            ->where('created_at', '>', now()->subHours(24))
            ->count();

        // Critical violations = immediate action
        if ($this->severity === self::SEVERITY_CRITICAL) {
            return self::ACTION_DEVICE_BLOCKED;
        }

        // High severity with multiple violations
        if ($this->severity === self::SEVERITY_HIGH) {
            if ($recentViolations > 3) {
                return self::ACTION_DEVICE_BLOCKED;
            }
            return self::ACTION_STREAM_BLOCKED;
        }

        // Medium severity
        if ($this->severity === self::SEVERITY_MEDIUM) {
            if ($recentViolations > 5) {
                return self::ACTION_STREAM_BLOCKED;
            }
            return self::ACTION_WARNING;
        }

        // Low severity - just log
        return self::ACTION_LOGGED;
    }

    /**
     * Block the stream
     */
    private function blockStream()
    {
        if ($this->subscription) {
            // End all active sessions for this subscription
            DeviceSession::whereHas('deviceBinding', function($q) {
                $q->where('subscription_id', $this->subscription_id);
            })
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'ended_at' => now()
            ]);
        }
    }

    /**
     * Block the device
     */
    private function blockDevice()
    {
        if ($this->deviceFingerprint) {
            $this->deviceFingerprint->block('Violation: ' . $this->violation_type);
        }
    }

    /**
     * Suspend subscription
     */
    private function suspendSubscription()
    {
        if ($this->subscription) {
            $this->subscription->update(['is_active' => false]);
        }
    }

    /**
     * Ban account
     */
    private function banAccount()
    {
        if ($this->subscriber) {
            $this->subscriber->update(['enabled' => false]);

            // Suspend all subscriptions
            $this->subscriber->subscriptions()
                ->update(['is_active' => false]);

            // Block all devices
            $this->subscriber->deviceFingerprints()
                ->update(['is_blocked' => true]);
        }
    }

    /**
     * Issue warning
     */
    private function issueWarning()
    {
        // Log warning (could send email/notification here)
        Activity::create([
            'user_id' => $this->subscriber_id,
            'description' => 'Security Warning: ' . $this->violation_type_text,
            'type' => 'warning'
        ]);
    }

    /**
     * Get violation statistics for a subscriber
     */
    public static function getStatistics($subscriberId, $days = 30)
    {
        $violations = static::where('subscriber_id', $subscriberId)
            ->where('created_at', '>', now()->subDays($days))
            ->get();

        return [
            'total' => $violations->count(),
            'by_type' => $violations->groupBy('violation_type')
                ->map->count(),
            'by_severity' => $violations->groupBy('severity')
                ->map->count(),
            'by_action' => $violations->groupBy('action_taken')
                ->map->count(),
            'critical_count' => $violations->where('severity', self::SEVERITY_CRITICAL)->count(),
            'devices_blocked' => $violations->where('action_taken', self::ACTION_DEVICE_BLOCKED)->count()
        ];
    }

    /**
     * Scope: Recent violations
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>', now()->subHours($hours));
    }

    /**
     * Scope: By severity
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope: By violation type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('violation_type', $type);
    }

    /**
     * Scope: With action taken
     */
    public function scopeWithAction($query)
    {
        return $query->where('action_taken', '!=', self::ACTION_LOGGED);
    }

    /**
     * Scope: Critical violations
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }
}