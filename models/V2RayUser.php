<?php

use Illuminate\Database\Eloquent\Model;

class V2RayUser extends Model
{
    protected $table = 'v2ray_users';

    protected $fillable = [
        'subscriber_id',
        'vmess_id',
        'port',
        'protocol',
        'config',
        'is_active',
        'last_connected',
        'bytes_uploaded',
        'bytes_downloaded',
        'connections_count',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'last_connected' => 'datetime',
        'bytes_uploaded' => 'integer',
        'bytes_downloaded' => 'integer',
        'connections_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the subscriber
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get traffic statistics
     */
    public function trafficStats()
    {
        return $this->hasMany(V2RayTrafficStat::class, 'v2ray_user_id');
    }

    /**
     * Get logs
     */
    public function logs()
    {
        return $this->hasMany(V2RayLog::class, 'subscriber_id', 'subscriber_id');
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by protocol
     */
    public function scopeByProtocol($query, $protocol)
    {
        return $query->where('protocol', $protocol);
    }

    /**
     * Get total bandwidth used
     */
    public function getTotalBandwidthAttribute()
    {
        return $this->bytes_uploaded + $this->bytes_downloaded;
    }

    /**
     * Get total bandwidth in human readable format
     */
    public function getFormattedBandwidthAttribute()
    {
        return $this->formatBytes($this->total_bandwidth);
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Update connection stats
     */
    public function recordConnection()
    {
        $this->increment('connections_count');
        $this->update(['last_connected' => now()]);
    }

    /**
     * Update traffic
     */
    public function updateTraffic($uploaded, $downloaded)
    {
        $this->increment('bytes_uploaded', $uploaded);
        $this->increment('bytes_downloaded', $downloaded);

        // Update daily stats
        $today = now()->toDateString();

        V2RayTrafficStat::updateOrCreate(
            [
                'subscriber_id' => $this->subscriber_id,
                'v2ray_user_id' => $this->id,
                'date' => $today,
            ],
            [
                'bytes_uploaded' => \DB::raw("bytes_uploaded + $uploaded"),
                'bytes_downloaded' => \DB::raw("bytes_downloaded + $downloaded"),
                'bytes_total' => \DB::raw("bytes_total + " . ($uploaded + $downloaded)),
                'connections_count' => \DB::raw('connections_count + 1'),
            ]
        );
    }

    /**
     * Get today's traffic
     */
    public function getTodayTraffic()
    {
        return $this->trafficStats()
            ->where('date', now()->toDateString())
            ->first();
    }

    /**
     * Get monthly traffic
     */
    public function getMonthlyTraffic()
    {
        return $this->trafficStats()
            ->where('date', '>=', now()->startOfMonth())
            ->sum('bytes_total');
    }

    /**
     * Check if user exceeded quota
     */
    public function hasExceededQuota($quotaBytes)
    {
        $monthlyUsage = $this->getMonthlyTraffic();
        return $monthlyUsage >= $quotaBytes;
    }

    /**
     * Deactivate user
     */
    public function deactivate($reason = null)
    {
        $this->update(['is_active' => false]);

        // Log deactivation
        V2RayLog::create([
            'subscriber_id' => $this->subscriber_id,
            'action' => 'user_deactivated',
            'protocol' => $this->protocol,
            'details' => ['reason' => $reason],
        ]);
    }

    /**
     * Activate user
     */
    public function activate()
    {
        $this->update(['is_active' => true]);

        // Log activation
        V2RayLog::create([
            'subscriber_id' => $this->subscriber_id,
            'action' => 'user_activated',
            'protocol' => $this->protocol,
        ]);
    }

    /**
     * Regenerate configuration
     */
    public function regenerateConfig()
    {
        // This would call V2RayService to regenerate config
        $service = new \App\Services\V2RayService();
        $stream = $this->subscriber->activeSubscription->streams()->first();

        if ($stream) {
            $newConfig = $service->generateClientConfig(
                $this->subscriber,
                $stream,
                $this->protocol
            );

            $this->update(['config' => $newConfig]);
            return $newConfig;
        }

        return null;
    }
}

/**
 * V2Ray Traffic Statistics Model
 */
class V2RayTrafficStat extends Model
{
    protected $table = 'v2ray_traffic_stats';

    protected $fillable = [
        'subscriber_id',
        'v2ray_user_id',
        'date',
        'bytes_uploaded',
        'bytes_downloaded',
        'bytes_total',
        'connections_count',
        'active_minutes',
    ];

    protected $casts = [
        'date' => 'date',
        'bytes_uploaded' => 'integer',
        'bytes_downloaded' => 'integer',
        'bytes_total' => 'integer',
        'connections_count' => 'integer',
        'active_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the subscriber
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Get the V2Ray user
     */
    public function v2rayUser()
    {
        return $this->belongsTo(V2RayUser::class);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }

    /**
     * Scope for this month
     */
    public function scopeThisMonth($query)
    {
        return $query->where('date', '>=', now()->startOfMonth());
    }

    /**
     * Get formatted total bandwidth
     */
    public function getFormattedTotalAttribute()
    {
        return $this->formatBytes($this->bytes_total);
    }

    /**
     * Format bytes
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

/**
 * V2Ray Log Model
 */
class V2RayLog extends Model
{
    protected $table = 'v2ray_logs';

    public $timestamps = false;

    protected $fillable = [
        'subscriber_id',
        'stream_id',
        'action',
        'protocol',
        'ip_address',
        'user_agent',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

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
     * Scope by action
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope recent logs
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc');
    }
}
