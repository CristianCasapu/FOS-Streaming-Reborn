<?php
/**
 * ActiveConnection Model
 *
 * Tracks active streaming connections for concurrent connection management.
 * Used to enforce subscription-level connection limits.
 */

class ActiveConnection extends FosStreaming
{
    protected $table = 'active_connections';

    protected $fillable = [
        'subscription_id',
        'subscriber_id',
        'stream_id',
        'session_id',
        'ip_address',
        'user_agent',
        'isp',
        'device_fingerprint',
        'connected_at',
        'last_heartbeat',
        'is_active',
        'bandwidth'
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_heartbeat' => 'datetime',
        'is_active' => 'boolean',
        'bandwidth' => 'integer',
        'subscription_id' => 'integer',
        'subscriber_id' => 'integer',
        'stream_id' => 'integer',
    ];

    public $timestamps = false;

    /**
     * Get the subscription
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the subscriber
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get the stream
     */
    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * Generate a unique session ID
     */
    public static function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Create or update a connection
     */
    public static function registerConnection(
        int $subscriptionId,
        int $subscriberId,
        ?int $streamId,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $isp = null,
        ?string $deviceFingerprint = null
    ): ?self {
        $subscription = Subscription::find($subscriptionId);
        if (!$subscription) {
            return null;
        }

        // Check concurrent connection limit
        $activeCount = self::where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->where('last_heartbeat', '>', date('Y-m-d H:i:s', time() - 60)) // Active in last 60 seconds
            ->count();

        if ($activeCount >= $subscription->max_concurrent_connections) {
            return null; // Limit reached
        }

        // Create or update connection
        $sessionId = self::generateSessionId();

        $connection = self::create([
            'subscription_id' => $subscriptionId,
            'subscriber_id' => $subscriberId,
            'stream_id' => $streamId,
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'isp' => $isp,
            'device_fingerprint' => $deviceFingerprint,
            'connected_at' => date('Y-m-d H:i:s'),
            'last_heartbeat' => date('Y-m-d H:i:s'),
            'is_active' => true,
            'bandwidth' => 0
        ]);

        // Update subscription's current_connections count
        $subscription->current_connections = self::where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->where('last_heartbeat', '>', date('Y-m-d H:i:s', time() - 60))
            ->count();
        $subscription->save();

        return $connection;
    }

    /**
     * Update heartbeat for a session
     */
    public static function heartbeat(string $sessionId, int $bandwidth = 0): bool
    {
        $connection = self::where('session_id', $sessionId)->first();
        if (!$connection) {
            return false;
        }

        $connection->last_heartbeat = date('Y-m-d H:i:s');
        $connection->bandwidth += $bandwidth;
        $connection->is_active = true;
        $connection->save();

        return true;
    }

    /**
     * Close a connection
     */
    public static function closeConnection(string $sessionId): bool
    {
        $connection = self::where('session_id', $sessionId)->first();
        if (!$connection) {
            return false;
        }

        $subscriptionId = $connection->subscription_id;
        $connection->is_active = false;
        $connection->save();

        // Update subscription's current_connections count
        $subscription = Subscription::find($subscriptionId);
        if ($subscription) {
            $subscription->current_connections = self::where('subscription_id', $subscriptionId)
                ->where('is_active', true)
                ->where('last_heartbeat', '>', date('Y-m-d H:i:s', time() - 60))
                ->count();
            $subscription->save();
        }

        return true;
    }

    /**
     * Get active connection count for a subscription
     */
    public static function getActiveCount(int $subscriptionId): int
    {
        return self::where('subscription_id', $subscriptionId)
            ->where('is_active', true)
            ->where('last_heartbeat', '>', date('Y-m-d H:i:s', time() - 60))
            ->count();
    }

    /**
     * Check if subscription can accept more connections
     */
    public static function canConnect(int $subscriptionId): bool
    {
        $subscription = Subscription::find($subscriptionId);
        if (!$subscription) {
            return false;
        }

        $activeCount = self::getActiveCount($subscriptionId);
        return $activeCount < $subscription->max_concurrent_connections;
    }

    /**
     * Clean up stale connections (no heartbeat for > 60 seconds)
     */
    public static function cleanupStaleConnections(): int
    {
        $staleTime = date('Y-m-d H:i:s', time() - 60);

        // Get affected subscriptions before cleanup
        $subscriptionIds = self::where('is_active', true)
            ->where('last_heartbeat', '<', $staleTime)
            ->pluck('subscription_id')
            ->unique()
            ->toArray();

        // Mark stale connections as inactive
        $count = self::where('is_active', true)
            ->where('last_heartbeat', '<', $staleTime)
            ->update(['is_active' => false]);

        // Update current_connections count for affected subscriptions
        foreach ($subscriptionIds as $subscriptionId) {
            $subscription = Subscription::find($subscriptionId);
            if ($subscription) {
                $subscription->current_connections = self::getActiveCount($subscriptionId);
                $subscription->save();
            }
        }

        return $count;
    }

    /**
     * Delete old inactive connections (older than 24 hours)
     */
    public static function purgeOldConnections(): int
    {
        $oldTime = date('Y-m-d H:i:s', time() - 86400);
        return self::where('is_active', false)
            ->where('last_heartbeat', '<', $oldTime)
            ->delete();
    }

    /**
     * Scope: Active connections
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('last_heartbeat', '>', date('Y-m-d H:i:s', time() - 60));
    }

    /**
     * Scope: By subscription
     */
    public function scopeBySubscription($query, $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    /**
     * Scope: By subscriber
     */
    public function scopeBySubscriber($query, $subscriberId)
    {
        return $query->where('subscriber_id', $subscriberId);
    }
}
