<?php
/**
 * Trial Model
 *
 * Represents a time-limited trial subscription for a subscriber
 */

class Trial extends FosStreaming {

    protected $table = 'trials';

    protected $fillable = [
        'subscriber_id',
        'package_id',
        'device',
        'device_mac',
        'ip_address',
        'isp',
        'user_agent',
        'started_at',
        'trial_duration_hours',
        'last_connected',
        'connection_count',
        'is_active',
        'converted_to_subscription',
        'notes'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_connected' => 'datetime',
        'is_active' => 'boolean',
        'converted_to_subscription' => 'boolean',
        'trial_duration_hours' => 'integer',
        'connection_count' => 'integer',
        'subscriber_id' => 'integer',
        'package_id' => 'integer',
    ];

    protected $dates = [
        'started_at',
        'expires_at',
        'last_connected',
        'created_at',
        'updated_at'
    ];

    /**
     * Get the subscriber that owns this trial
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    /**
     * Get the package for this trial
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Check if trial is expired
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if trial is valid (active and not expired)
     */
    public function isValid()
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Get remaining hours
     */
    public function getRemainingHoursAttribute()
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInHours($this->expires_at);
    }

    /**
     * Get remaining minutes
     */
    public function getRemainingMinutesAttribute()
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInMinutes($this->expires_at);
    }

    /**
     * Get remaining time as formatted string
     */
    public function getRemainingTimeAttribute()
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        $hours = $this->remaining_hours;
        $minutes = $this->remaining_minutes % 60;

        if ($hours > 24) {
            $days = floor($hours / 24);
            $hours = $hours % 24;
            return "{$days}d {$hours}h {$minutes}m";
        }

        return "{$hours}h {$minutes}m";
    }

    /**
     * Get progress percentage (0-100)
     */
    public function getProgressPercentageAttribute()
    {
        if ($this->isExpired()) {
            return 100;
        }

        $totalMinutes = $this->trial_duration_hours * 60;
        $elapsedMinutes = $this->started_at->diffInMinutes(now());
        $percentage = ($elapsedMinutes / $totalMinutes) * 100;

        return min(100, round($percentage, 2));
    }

    /**
     * Convert trial to subscription
     */
    public function convertToSubscription($expireDate = null, $autoRenew = false)
    {
        if ($this->converted_to_subscription) {
            return null; // Already converted
        }

        // Create subscription with same package and device info
        $subscription = new Subscription();
        $subscription->subscriber_id = $this->subscriber_id;
        $subscription->package_id = $this->package_id;
        $subscription->device = $this->device;
        $subscription->device_mac = $this->device_mac;
        $subscription->ip_address = $this->ip_address;
        $subscription->isp = $this->isp;
        $subscription->user_agent = $this->user_agent;
        $subscription->last_connected = $this->last_connected;
        $subscription->expire_date = $expireDate ?? now()->addDays(30);
        $subscription->is_active = 1;
        $subscription->auto_renew = $autoRenew;
        $subscription->notes = "Converted from trial {$this->id}";
        $subscription->save();

        // Mark trial as converted
        $this->converted_to_subscription = 1;
        $this->is_active = 0;
        $this->save();

        return $subscription;
    }

    /**
     * Extend trial duration
     */
    public function extendTrial($hours = 24)
    {
        $this->trial_duration_hours += $hours;
        $this->save();

        return $this;
    }

    /**
     * Record a connection
     */
    public function recordConnection($ipAddress = null, $userAgent = null, $isp = null)
    {
        $this->last_connected = now();
        $this->connection_count++;

        if ($ipAddress) {
            $this->ip_address = $ipAddress;
        }

        if ($userAgent) {
            $this->user_agent = $userAgent;
        }

        if ($isp) {
            $this->isp = $isp;
        }

        $this->save();
    }

    /**
     * Get all channels available in this trial
     */
    public function getChannelsAttribute()
    {
        return $this->package ? $this->package->channels : collect();
    }

    /**
     * Get all bouquets available in this trial
     */
    public function getBouquetsAttribute()
    {
        return $this->package ? $this->package->bouquets : collect();
    }

    /**
     * Check if trial has access to a specific channel
     */
    public function hasAccessToChannel($channelId)
    {
        return $this->isValid() && $this->channels->contains('id', $channelId);
    }

    /**
     * Check if trial has access to a specific bouquet
     */
    public function hasAccessToBouquet($bouquetId)
    {
        return $this->isValid() && $this->bouquets->contains('id', $bouquetId);
    }

    /**
     * Get formatted device info
     */
    public function getDeviceInfoAttribute()
    {
        $info = [];

        if ($this->device) {
            $info[] = $this->device;
        }

        if ($this->device_mac) {
            $info[] = "MAC: {$this->device_mac}";
        }

        return implode(' | ', $info) ?: 'Unknown Device';
    }

    /**
     * Get formatted connection info
     */
    public function getConnectionInfoAttribute()
    {
        $info = [];

        if ($this->ip_address) {
            $info[] = "IP: {$this->ip_address}";
        }

        if ($this->isp) {
            $info[] = "ISP: {$this->isp}";
        }

        return implode(' | ', $info) ?: 'No connection data';
    }

    /**
     * Scope: Only active trials
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope: Only valid trials (active and not expired)
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', 1)
                     ->where('expires_at', '>', now());
    }

    /**
     * Scope: Expired trials
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope: Trials not yet converted
     */
    public function scopeNotConverted($query)
    {
        return $query->where('converted_to_subscription', 0);
    }

    /**
     * Scope: Trials converted to subscriptions
     */
    public function scopeConverted($query)
    {
        return $query->where('converted_to_subscription', 1);
    }

    /**
     * Scope: By subscriber
     */
    public function scopeBySubscriber($query, $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }

    /**
     * Scope: By package
     */
    public function scopeByPackage($query, $packageId)
    {
        return $query->where('package_id', $packageId);
    }

    /**
     * Static: Get default trial duration from settings
     */
    public static function getDefaultDuration()
    {
        $settings = Setting::first();
        return $settings ? ($settings->trial_duration_hours ?? 24) : 24;
    }

    /**
     * Static: Check if trials are enabled in settings
     */
    public static function isTrialSystemEnabled()
    {
        $settings = Setting::first();
        return $settings ? ($settings->trial_enabled ?? 1) : 1;
    }

    /**
     * Static: Check if trial requires approval
     */
    public static function requiresApproval()
    {
        $settings = Setting::first();
        return $settings ? ($settings->trial_requires_approval ?? 0) : 0;
    }
}
