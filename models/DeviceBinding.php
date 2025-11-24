<?php
/**
 * Device Binding Model
 *
 * Links device fingerprints to subscriptions with validation rules
 */

class DeviceBinding extends FosStreaming {

    protected $table = 'device_bindings';

    protected $fillable = [
        'subscription_id',
        'device_fingerprint_id',
        'device_name',
        'mac_address',
        'ip_address',
        'ip_range_start',
        'ip_range_end',
        'geolocation',
        'max_distance_km',
        'is_primary',
        'is_active',
        'validated_at',
        'expires_at'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'geolocation' => 'array',
        'ip_range_start' => 'integer',
        'ip_range_end' => 'integer',
        'max_distance_km' => 'integer',
        'validated_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the subscription for this binding
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the device fingerprint for this binding
     */
    public function deviceFingerprint()
    {
        return $this->belongsTo(DeviceFingerprint::class, 'device_fingerprint_id');
    }

    /**
     * Get all sessions for this binding
     */
    public function sessions()
    {
        return $this->hasMany(DeviceSession::class, 'device_binding_id');
    }

    /**
     * Get active sessions
     */
    public function activeSessions()
    {
        return $this->sessions()->where('is_active', true);
    }

    /**
     * Check if binding is valid
     */
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->deviceFingerprint->is_blocked) {
            return false;
        }

        return true;
    }

    /**
     * Validate IP address
     */
    public function validateIpAddress($ipAddress)
    {
        // If no IP restrictions, allow all
        if (!$this->ip_address && !$this->ip_range_start) {
            return true;
        }

        // Check exact IP match
        if ($this->ip_address && $this->ip_address === $ipAddress) {
            return true;
        }

        // Check IP range
        if ($this->ip_range_start && $this->ip_range_end) {
            $ipLong = ip2long($ipAddress);
            if ($ipLong >= $this->ip_range_start && $ipLong <= $this->ip_range_end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate geolocation
     */
    public function validateGeolocation($latitude, $longitude)
    {
        if (!$this->geolocation || !$this->max_distance_km) {
            return true;
        }

        $distance = $this->calculateDistance(
            $this->geolocation['latitude'],
            $this->geolocation['longitude'],
            $latitude,
            $longitude
        );

        return $distance <= $this->max_distance_km;
    }

    /**
     * Calculate distance between two points (Haversine formula)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Radius in kilometers

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Validate device for streaming
     */
    public function validateForStreaming($request)
    {
        $violations = [];

        // Check IP address
        if (!$this->validateIpAddress($request->ip())) {
            $violations[] = [
                'type' => 'location_mismatch',
                'message' => 'IP address not authorized'
            ];
        }

        // Check geolocation if provided
        if ($request->has('latitude') && $request->has('longitude')) {
            if (!$this->validateGeolocation($request->latitude, $request->longitude)) {
                $violations[] = [
                    'type' => 'location_mismatch',
                    'message' => 'Location outside authorized area'
                ];
            }
        }

        // Check concurrent streams
        $activeStreams = $this->activeSessions()->count();
        $maxStreams = $this->subscription->max_concurrent_streams ?? 1;

        if ($activeStreams >= $maxStreams) {
            $violations[] = [
                'type' => 'concurrent_limit',
                'message' => 'Maximum concurrent streams reached'
            ];
        }

        return $violations;
    }

    /**
     * Mark as validated
     */
    public function markAsValidated()
    {
        $this->validated_at = now();
        $this->save();
        return $this;
    }

    /**
     * Deactivate binding
     */
    public function deactivate($reason = null)
    {
        $this->is_active = false;
        $this->save();

        // End all active sessions
        $this->sessions()
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'ended_at' => now()
            ]);

        // Log if reason provided
        if ($reason) {
            DeviceViolation::create([
                'subscription_id' => $this->subscription_id,
                'device_fingerprint_id' => $this->device_fingerprint_id,
                'violation_type' => 'device_mismatch',
                'details' => json_encode(['reason' => $reason]),
                'action_taken' => 'device_blocked'
            ]);
        }

        return $this;
    }

    /**
     * Activate binding
     */
    public function activate()
    {
        $this->is_active = true;
        $this->save();
        return $this;
    }

    /**
     * Set as primary device
     */
    public function setAsPrimary()
    {
        // Remove primary from other devices
        static::where('subscription_id', $this->subscription_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->is_primary = true;
        $this->save();

        return $this;
    }

    /**
     * Update IP range from CIDR notation
     */
    public function setIpRange($cidr)
    {
        list($ip, $prefix) = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $mask = -1 << (32 - $prefix);

        $this->ip_range_start = $ipLong & $mask;
        $this->ip_range_end = $ipLong | ~$mask;
        $this->save();

        return $this;
    }

    /**
     * Get formatted device info
     */
    public function getFormattedInfoAttribute()
    {
        $info = [];

        if ($this->device_name) {
            $info[] = $this->device_name;
        } else {
            $info[] = $this->deviceFingerprint->device_info;
        }

        if ($this->is_primary) {
            $info[] = '(Primary)';
        }

        if ($this->mac_address) {
            $info[] = "MAC: {$this->mac_address}";
        }

        if ($this->ip_address) {
            $info[] = "IP: {$this->ip_address}";
        }

        return implode(' ', $info);
    }

    /**
     * Get validation status
     */
    public function getValidationStatusAttribute()
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }

        if (!$this->validated_at) {
            return 'pending';
        }

        if ($this->deviceFingerprint->is_trusted) {
            return 'trusted';
        }

        return 'validated';
    }

    /**
     * Scope: Active bindings
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: Primary devices
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope: By subscription
     */
    public function scopeBySubscription($query, $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    /**
     * Scope: Validated devices
     */
    public function scopeValidated($query)
    {
        return $query->whereNotNull('validated_at');
    }

    /**
     * Scope: Expiring soon
     */
    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('is_active', true)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }
}