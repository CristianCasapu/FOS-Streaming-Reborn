<?php

use Illuminate\Database\Eloquent\Model;

class V2RayServer extends Model
{
    protected $table = 'v2ray_servers';

    protected $fillable = [
        'tag',
        'name',
        'address',
        'port',
        'location',
        'country_code',
        'weight',
        'load',
        'max_connections',
        'current_connections',
        'config',
        'health_check_url',
        'health_status',
        'last_health_check',
        'enabled',
        'notes',
    ];

    protected $casts = [
        'port' => 'integer',
        'weight' => 'integer',
        'load' => 'decimal:2',
        'max_connections' => 'integer',
        'current_connections' => 'integer',
        'config' => 'array',
        'last_health_check' => 'datetime',
        'enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope for enabled servers
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope for healthy servers
     */
    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    /**
     * Scope for available servers (enabled and healthy)
     */
    public function scopeAvailable($query)
    {
        return $query->enabled()->healthy();
    }

    /**
     * Scope by location
     */
    public function scopeByLocation($query, $location)
    {
        return $query->where('location', $location);
    }

    /**
     * Scope ordered by load (least loaded first)
     */
    public function scopeByLoad($query)
    {
        return $query->orderBy('load', 'asc');
    }

    /**
     * Get server load percentage
     */
    public function getLoadPercentageAttribute()
    {
        if ($this->max_connections == 0) {
            return 0;
        }
        return round(($this->current_connections / $this->max_connections) * 100, 2);
    }

    /**
     * Check if server can accept more connections
     */
    public function canAcceptConnection()
    {
        if (!$this->enabled) {
            return false;
        }

        if ($this->health_status !== 'healthy') {
            return false;
        }

        if ($this->max_connections > 0 && $this->current_connections >= $this->max_connections) {
            return false;
        }

        return true;
    }

    /**
     * Increment connection count
     */
    public function incrementConnections()
    {
        $this->increment('current_connections');
        $this->updateLoad();
    }

    /**
     * Decrement connection count
     */
    public function decrementConnections()
    {
        if ($this->current_connections > 0) {
            $this->decrement('current_connections');
            $this->updateLoad();
        }
    }

    /**
     * Update load percentage
     */
    public function updateLoad()
    {
        if ($this->max_connections > 0) {
            $load = ($this->current_connections / $this->max_connections) * 100;
            $this->update(['load' => round($load, 2)]);
        }
    }

    /**
     * Perform health check
     */
    public function performHealthCheck()
    {
        if (!$this->health_check_url) {
            $this->update([
                'health_status' => 'unknown',
                'last_health_check' => now(),
            ]);
            return 'unknown';
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $response = $client->get($this->health_check_url);

            $isHealthy = $response->getStatusCode() === 200;

            $this->update([
                'health_status' => $isHealthy ? 'healthy' : 'unhealthy',
                'last_health_check' => now(),
            ]);

            return $isHealthy ? 'healthy' : 'unhealthy';

        } catch (\Exception $e) {
            $this->update([
                'health_status' => 'unhealthy',
                'last_health_check' => now(),
            ]);

            \Log::error("V2Ray server health check failed for {$this->tag}: " . $e->getMessage());

            return 'unhealthy';
        }
    }

    /**
     * Mark server as unhealthy
     */
    public function markUnhealthy()
    {
        $this->update([
            'health_status' => 'unhealthy',
            'last_health_check' => now(),
        ]);
    }

    /**
     * Mark server as healthy
     */
    public function markHealthy()
    {
        $this->update([
            'health_status' => 'healthy',
            'last_health_check' => now(),
        ]);
    }

    /**
     * Get server stats
     */
    public function getStats()
    {
        return [
            'tag' => $this->tag,
            'name' => $this->name,
            'address' => $this->address,
            'port' => $this->port,
            'location' => $this->location,
            'enabled' => $this->enabled,
            'health_status' => $this->health_status,
            'current_connections' => $this->current_connections,
            'max_connections' => $this->max_connections,
            'load' => $this->load,
            'load_percentage' => $this->load_percentage,
            'last_health_check' => $this->last_health_check?->toIso8601String(),
        ];
    }

    /**
     * Get best server for load balancing
     */
    public static function getBestServer($location = null)
    {
        $query = self::available()->byLoad();

        if ($location) {
            // Try to find server in same location first
            $server = $query->clone()->byLocation($location)->first();
            if ($server && $server->canAcceptConnection()) {
                return $server;
            }
        }

        // Otherwise get least loaded server
        $servers = $query->get();

        foreach ($servers as $server) {
            if ($server->canAcceptConnection()) {
                return $server;
            }
        }

        return null;
    }

    /**
     * Get load balancing configuration
     */
    public static function getLoadBalancingConfig()
    {
        $servers = self::available()->byLoad()->get();

        $config = [
            'strategy' => 'leastLoad',
            'servers' => [],
        ];

        foreach ($servers as $server) {
            $config['servers'][] = [
                'tag' => $server->tag,
                'address' => $server->address,
                'port' => $server->port,
                'weight' => $server->weight,
                'load' => $server->load,
                'max_connections' => $server->max_connections,
                'current_connections' => $server->current_connections,
            ];
        }

        return $config;
    }

    /**
     * Disable server
     */
    public function disable($reason = null)
    {
        $this->update([
            'enabled' => false,
            'notes' => $reason ? "Disabled: $reason" : $this->notes,
        ]);
    }

    /**
     * Enable server
     */
    public function enable()
    {
        // Perform health check before enabling
        $healthStatus = $this->performHealthCheck();

        if ($healthStatus === 'healthy') {
            $this->update(['enabled' => true]);
            return true;
        }

        return false;
    }
}
