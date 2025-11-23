<?php
/**
 * Subscription Model
 *
 * Represents a subscriber's subscription with device tracking and security
 */

class Subscription extends FosStreaming {

    protected $table = 'subscriptions';

    protected $fillable = [
        'subscriber_id',
        'package_id',
        'device',
        'device_mac',
        'ip_address',
        'isp',
        'user_agent',
        'last_connected',
        'connection_count',
        'expire_date',
        'is_active',
        'auto_renew',
        'notes'
    ];

    protected $casts = [
        'last_connected' => 'datetime',
        'expire_date' => 'datetime',
        'is_active' => 'boolean',
        'auto_renew' => 'boolean',
        'connection_count' => 'integer',
        'subscriber_id' => 'integer',
        'package_id' => 'integer',
    ];

    protected $dates = [
        'last_connected',
        'expire_date',
        'created_at',
        'updated_at'
    ];

    /**
     * Get the subscriber that owns this subscription
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class, 'subscriber_id');
    }

    /**
     * Get the package for this subscription
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Check if subscription is expired
     */
    public function isExpired()
    {
        return $this->expire_date && $this->expire_date->isPast();
    }

    /**
     * Check if subscription is valid (active and not expired)
     */
    public function isValid()
    {
        return $this->is_active && !$this->isExpired();
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpirationAttribute()
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->expire_date);
    }

    /**
     * Get hours until expiration
     */
    public function getHoursUntilExpirationAttribute()
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInHours($this->expire_date);
    }

    /**
     * Check if subscription is expiring soon (within 7 days)
     */
    public function isExpiringSoon($days = 7)
    {
        if ($this->isExpired()) {
            return false;
        }

        return $this->days_until_expiration <= $days;
    }

    /**
     * Renew subscription (extend expiration date)
     */
    public function renew($days = 30)
    {
        $this->expire_date = $this->expire_date->addDays($days);
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
     * Get all channels available in this subscription
     */
    public function getChannelsAttribute()
    {
        return $this->package ? $this->package->channels : collect();
    }

    /**
     * Get all bouquets available in this subscription
     */
    public function getBouquetsAttribute()
    {
        return $this->package ? $this->package->bouquets : collect();
    }

    /**
     * Check if subscription has access to a specific channel
     */
    public function hasAccessToChannel($channelId)
    {
        return $this->isValid() && $this->channels->contains('id', $channelId);
    }

    /**
     * Check if subscription has access to a specific bouquet
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
     * Scope: Only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope: Only valid subscriptions (active and not expired)
     */
    public function scopeValid($query)
    {
        return $query->where('is_active', 1)
                     ->where('expire_date', '>', now());
    }

    /**
     * Scope: Expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where('expire_date', '<=', now());
    }

    /**
     * Scope: Expiring soon
     */
    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->where('is_active', 1)
                     ->whereBetween('expire_date', [now(), now()->addDays($days)]);
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
     * Scope: By device MAC address
     */
    public function scopeByDeviceMac($query, $mac)
    {
        return $query->where('device_mac', $mac);
    }

    /**
     * Scope: By IP address
     */
    public function scopeByIpAddress($query, $ip)
    {
        return $query->where('ip_address', $ip);
    }
}
