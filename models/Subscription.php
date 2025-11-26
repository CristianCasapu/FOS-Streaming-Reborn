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
        'device_fingerprint',
        'ip_address',
        'last_ip_address',
        'isp',
        'user_agent',
        'last_connected',
        'connection_count',
        'max_concurrent_connections',
        'current_connections',
        'expire_date',
        'is_active',
        'auto_renew',
        'notes',
        'access_token',
        'token_expires_at',
        'allowed_isps',
        'allowed_ips'
    ];

    protected $casts = [
        'last_connected' => 'datetime',
        'expire_date' => 'datetime',
        'token_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'auto_renew' => 'boolean',
        'connection_count' => 'integer',
        'max_concurrent_connections' => 'integer',
        'current_connections' => 'integer',
        'subscriber_id' => 'integer',
        'package_id' => 'integer',
        'allowed_isps' => 'array',
        'allowed_ips' => 'array',
    ];

    protected $dates = [
        'last_connected',
        'expire_date',
        'token_expires_at',
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
        if (!$this->expire_date) {
            return false;
        }

        // Handle both DateTime objects and string dates
        if ($this->expire_date instanceof \DateTime) {
            return $this->expire_date < new \DateTime();
        }

        // If it's a string, compare using strtotime
        return strtotime($this->expire_date) < time();
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

        $now = new \DateTime();
        return $now->diff($this->expire_date)->days;
    }

    /**
     * Get hours until expiration
     */
    public function getHoursUntilExpirationAttribute()
    {
        if ($this->isExpired()) {
            return 0;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->expire_date);
        return ($interval->days * 24) + $interval->h;
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
        $this->last_connected = date('Y-m-d H:i:s');
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
                     ->where('expire_date', '>', date('Y-m-d H:i:s'));
    }

    /**
     * Scope: Expired subscriptions
     */
    public function scopeExpired($query)
    {
        return $query->where('expire_date', '<=', date('Y-m-d H:i:s'));
    }

    /**
     * Scope: Expiring soon
     */
    public function scopeExpiringSoon($query, $days = 7)
    {
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        return $query->where('is_active', 1)
                     ->whereBetween('expire_date', [$now, $future]);
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

    // =====================================================
    // ACCESS TOKEN & SECURITY METHODS
    // =====================================================

    /**
     * Generate a new access token
     * Token is tied to subscription expiry
     */
    public function generateAccessToken(): string
    {
        $token = bin2hex(random_bytes(64)); // 128 char hex
        $this->access_token = $token;
        $this->token_expires_at = $this->expire_date;
        $this->save();
        return $token;
    }

    /**
     * Get or create access token
     */
    public function getOrCreateAccessToken(): string
    {
        if (!$this->access_token || $this->isTokenExpired()) {
            return $this->generateAccessToken();
        }
        return $this->access_token;
    }

    /**
     * Check if access token is expired
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }
        return strtotime($this->token_expires_at) < time();
    }

    /**
     * Validate access token
     */
    public function validateAccessToken(string $token): bool
    {
        if (!$this->access_token) {
            return false;
        }
        return hash_equals($this->access_token, $token) && !$this->isTokenExpired();
    }

    /**
     * Revoke access token
     */
    public function revokeAccessToken(): void
    {
        $this->access_token = null;
        $this->token_expires_at = null;
        $this->save();
    }

    /**
     * Check if IP is allowed
     * Returns true if allowed_ips is empty or if IP matches
     */
    public function isIpAllowed(string $ip): bool
    {
        $allowed = $this->allowed_ips;
        if (empty($allowed)) {
            return true; // No restriction
        }

        foreach ($allowed as $allowedIp) {
            // Check for exact match
            if ($ip === $allowedIp) {
                return true;
            }
            // Check for CIDR range
            if (strpos($allowedIp, '/') !== false && $this->ipInCidr($ip, $allowedIp)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if ISP is allowed
     * Returns true if allowed_isps is empty or if ISP matches
     */
    public function isIspAllowed(?string $isp): bool
    {
        $allowed = $this->allowed_isps;
        if (empty($allowed)) {
            return true; // No restriction
        }

        if (!$isp) {
            return false; // ISP required but not provided
        }

        $isp = strtolower($isp);
        foreach ($allowed as $allowedIsp) {
            if (stripos($isp, strtolower($allowedIsp)) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        list($subnet, $mask) = explode('/', $cidr);
        $subnet = ip2long($subnet);
        $ip = ip2long($ip);
        $mask = -1 << (32 - $mask);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }

    /**
     * Check concurrent connection limit
     */
    public function canConnect(): bool
    {
        return ActiveConnection::canConnect($this->id);
    }

    /**
     * Get current active connection count
     */
    public function getActiveConnectionCount(): int
    {
        return ActiveConnection::getActiveCount($this->id);
    }

    /**
     * Register a new connection
     */
    public function registerConnection(
        ?int $streamId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $isp = null,
        ?string $deviceFingerprint = null
    ): ?ActiveConnection {
        return ActiveConnection::registerConnection(
            $this->id,
            $this->subscriber_id,
            $streamId,
            $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            $isp,
            $deviceFingerprint
        );
    }

    /**
     * Validate full access (subscription + IP + ISP + connections)
     * Returns array with 'valid' boolean and 'error' message if invalid
     */
    public function validateAccess(?string $ip = null, ?string $isp = null): array
    {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? null;

        // Check subscription validity
        if (!$this->isValid()) {
            if (!$this->is_active) {
                return ['valid' => false, 'error' => 'Subscription is not active'];
            }
            if ($this->isExpired()) {
                return ['valid' => false, 'error' => 'Subscription has expired'];
            }
            return ['valid' => false, 'error' => 'Subscription is invalid'];
        }

        // Check IP restriction
        if ($ip && !$this->isIpAllowed($ip)) {
            return ['valid' => false, 'error' => 'IP address not allowed for this subscription'];
        }

        // Check ISP restriction
        if (!$this->isIspAllowed($isp)) {
            return ['valid' => false, 'error' => 'ISP not allowed for this subscription'];
        }

        // Check concurrent connections
        if (!$this->canConnect()) {
            return [
                'valid' => false,
                'error' => "Maximum concurrent connections reached ({$this->max_concurrent_connections})"
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Get active connections for this subscription
     */
    public function activeConnections()
    {
        return $this->hasMany(ActiveConnection::class)->active();
    }

    /**
     * Scope: By access token
     */
    public function scopeByAccessToken($query, $token)
    {
        return $query->where('access_token', $token);
    }

    /**
     * Find subscription by access token (static)
     */
    public static function findByAccessToken(string $token): ?self
    {
        return self::where('access_token', $token)
            ->where('token_expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
    }
}
