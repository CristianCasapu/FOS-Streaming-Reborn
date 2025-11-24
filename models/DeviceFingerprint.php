<?php
/**
 * Device Fingerprint Model
 *
 * Tracks unique device identifiers for anti-sharing protection
 */

class DeviceFingerprint extends FosStreaming {

    protected $table = 'device_fingerprints';

    protected $fillable = [
        'subscriber_id',
        'device_id',
        'hardware_id',
        'browser_fingerprint',
        'canvas_fingerprint',
        'webgl_fingerprint',
        'audio_fingerprint',
        'timezone',
        'screen_resolution',
        'platform',
        'user_agent',
        'is_trusted',
        'is_blocked'
    ];

    protected $casts = [
        'is_trusted' => 'boolean',
        'is_blocked' => 'boolean',
        'last_seen' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the subscriber that owns this device
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    /**
     * Get all bindings for this device
     */
    public function bindings()
    {
        return $this->hasMany(DeviceBinding::class, 'device_fingerprint_id');
    }

    /**
     * Get active bindings for this device
     */
    public function activeBindings()
    {
        return $this->hasMany(DeviceBinding::class, 'device_fingerprint_id')
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Get violations for this device
     */
    public function violations()
    {
        return $this->hasMany(DeviceViolation::class, 'device_fingerprint_id');
    }

    /**
     * Get recent violations
     */
    public function recentViolations($hours = 24)
    {
        return $this->violations()
            ->where('created_at', '>', now()->subHours($hours))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Check if device is currently active
     */
    public function isActive()
    {
        return !$this->is_blocked && $this->activeBindings()->exists();
    }

    /**
     * Check if device can be trusted
     */
    public function canBeTrusted()
    {
        if ($this->is_blocked) {
            return false;
        }

        // Check violation history
        $recentViolations = $this->recentViolations(7 * 24)->count();
        if ($recentViolations > 3) {
            return false;
        }

        // Check usage history (at least 7 days old and used regularly)
        if ($this->created_at->diffInDays(now()) < 7) {
            return false;
        }

        // Check for suspicious patterns
        $suspiciousViolations = $this->violations()
            ->whereIn('violation_type', ['sharing_detected', 'suspicious_pattern'])
            ->count();

        return $suspiciousViolations === 0;
    }

    /**
     * Update last seen timestamp
     */
    public function updateLastSeen()
    {
        $this->last_seen = now();
        $this->save();
        return $this;
    }

    /**
     * Block the device
     */
    public function block($reason = null)
    {
        $this->is_blocked = true;
        $this->is_trusted = false;
        $this->save();

        // Deactivate all bindings
        $this->bindings()->update([
            'is_active' => false
        ]);

        // Log violation
        if ($reason) {
            DeviceViolation::create([
                'subscriber_id' => $this->subscriber_id,
                'device_fingerprint_id' => $this->id,
                'violation_type' => 'device_mismatch',
                'severity' => 'critical',
                'details' => json_encode(['reason' => $reason]),
                'action_taken' => 'device_blocked'
            ]);
        }

        return $this;
    }

    /**
     * Unblock the device
     */
    public function unblock()
    {
        $this->is_blocked = false;
        $this->save();
        return $this;
    }

    /**
     * Trust the device
     */
    public function trust()
    {
        if ($this->canBeTrusted()) {
            $this->is_trusted = true;
            $this->save();
        }
        return $this;
    }

    /**
     * Get device info summary
     */
    public function getDeviceInfoAttribute()
    {
        $info = [];

        if ($this->platform) {
            $info[] = $this->platform;
        }

        if ($this->screen_resolution) {
            $info[] = $this->screen_resolution;
        }

        if ($this->timezone) {
            $info[] = $this->timezone;
        }

        return implode(' | ', $info) ?: 'Unknown Device';
    }

    /**
     * Get violation summary
     */
    public function getViolationSummaryAttribute()
    {
        $violations = $this->violations()
            ->selectRaw('violation_type, COUNT(*) as count')
            ->groupBy('violation_type')
            ->get()
            ->pluck('count', 'violation_type')
            ->toArray();

        return $violations;
    }

    /**
     * Check if device matches fingerprint data
     */
    public function matches($fingerprintData)
    {
        // Primary match on device_id
        if ($this->device_id !== $fingerprintData['device_id']) {
            return false;
        }

        // Calculate similarity score
        $score = 0;
        $totalChecks = 0;

        // Check each component
        $components = [
            'canvas_fingerprint' => 30,  // Weight
            'webgl_fingerprint' => 30,
            'audio_fingerprint' => 20,
            'timezone' => 10,
            'screen_resolution' => 5,
            'platform' => 5
        ];

        foreach ($components as $field => $weight) {
            $totalChecks += $weight;
            if (isset($fingerprintData[$field]) && $this->$field === $fingerprintData[$field]) {
                $score += $weight;
            }
        }

        // Require at least 70% match
        return ($score / $totalChecks) >= 0.7;
    }

    /**
     * Scope: Only trusted devices
     */
    public function scopeTrusted($query)
    {
        return $query->where('is_trusted', true);
    }

    /**
     * Scope: Only blocked devices
     */
    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }

    /**
     * Scope: Active devices
     */
    public function scopeActive($query)
    {
        return $query->where('is_blocked', false)
            ->whereHas('bindings', function($q) {
                $q->where('is_active', true);
            });
    }

    /**
     * Scope: Recently seen devices
     */
    public function scopeRecentlySeen($query, $days = 30)
    {
        return $query->where('last_seen', '>', now()->subDays($days));
    }

    /**
     * Scope: By subscriber
     */
    public function scopeBySubscriber($query, $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    /**
     * Scope: Search by device info
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('device_id', 'LIKE', "%{$search}%")
              ->orWhere('platform', 'LIKE', "%{$search}%")
              ->orWhere('user_agent', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Generate a unique device ID from components
     */
    public static function generateDeviceId($components)
    {
        $data = [
            'canvas' => $components['canvas_fingerprint'] ?? '',
            'webgl' => $components['webgl_fingerprint'] ?? '',
            'audio' => $components['audio_fingerprint'] ?? '',
            'timezone' => $components['timezone'] ?? '',
            'screen' => $components['screen_resolution'] ?? '',
            'platform' => $components['platform'] ?? '',
            'languages' => $components['languages'] ?? [],
            'user_agent' => $components['user_agent'] ?? ''
        ];

        return hash('sha256', json_encode($data));
    }
}