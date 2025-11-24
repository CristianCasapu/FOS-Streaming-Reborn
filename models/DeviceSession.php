<?php
/**
 * Device Session Model
 *
 * Tracks active streaming sessions per device
 */

class DeviceSession extends FosStreaming {

    protected $table = 'device_sessions';

    protected $fillable = [
        'device_binding_id',
        'stream_id',
        'session_token',
        'ip_address',
        'user_agent',
        'started_at',
        'last_activity',
        'ended_at',
        'data_transferred',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'data_transferred' => 'integer',
        'started_at' => 'datetime',
        'last_activity' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Generate session token on creation
        static::creating(function ($session) {
            if (!$session->session_token) {
                $session->session_token = bin2hex(random_bytes(32));
            }
            if (!$session->started_at) {
                $session->started_at = now();
            }
            if (!$session->last_activity) {
                $session->last_activity = now();
            }
        });
    }

    /**
     * Get the device binding for this session
     */
    public function deviceBinding()
    {
        return $this->belongsTo(DeviceBinding::class, 'device_binding_id');
    }

    /**
     * Get the stream for this session
     */
    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    /**
     * Get the subscriber through device binding
     */
    public function getSubscriberAttribute()
    {
        return $this->deviceBinding->subscription->subscriber;
    }

    /**
     * Get the device fingerprint through binding
     */
    public function getDeviceFingerprintAttribute()
    {
        return $this->deviceBinding->deviceFingerprint;
    }

    /**
     * Check if session is valid
     */
    public function isValid()
    {
        if (!$this->is_active) {
            return false;
        }

        // Check timeout
        $timeoutMinutes = Setting::where('id', 1)->value('device_session_timeout_minutes') ?? 30;
        if ($this->last_activity->diffInMinutes(now()) > $timeoutMinutes) {
            $this->end('Session timeout');
            return false;
        }

        // Check if device binding is still valid
        if (!$this->deviceBinding->isValid()) {
            $this->end('Device binding invalid');
            return false;
        }

        return true;
    }

    /**
     * Update activity timestamp
     */
    public function updateActivity()
    {
        $this->last_activity = now();
        $this->save();
        return $this;
    }

    /**
     * End the session
     */
    public function end($reason = null)
    {
        $this->is_active = false;
        $this->ended_at = now();

        if ($reason) {
            $details = $this->details ?? [];
            $details['end_reason'] = $reason;
            $this->details = $details;
        }

        $this->save();

        return $this;
    }

    /**
     * Add data transfer
     */
    public function addDataTransfer($bytes)
    {
        $this->data_transferred += $bytes;
        $this->save();
        return $this;
    }

    /**
     * Get session duration in seconds
     */
    public function getDurationSecondsAttribute()
    {
        $endTime = $this->ended_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get session duration formatted
     */
    public function getDurationFormattedAttribute()
    {
        $seconds = $this->duration_seconds;

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * Get data transferred formatted
     */
    public function getDataTransferredFormattedAttribute()
    {
        $bytes = $this->data_transferred;

        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Get average bitrate
     */
    public function getAverageBitrateAttribute()
    {
        if ($this->duration_seconds == 0) {
            return 0;
        }

        $bitsPerSecond = ($this->data_transferred * 8) / $this->duration_seconds;

        if ($bitsPerSecond >= 1000000) {
            return round($bitsPerSecond / 1000000, 2) . ' Mbps';
        } elseif ($bitsPerSecond >= 1000) {
            return round($bitsPerSecond / 1000, 2) . ' Kbps';
        }

        return round($bitsPerSecond, 2) . ' bps';
    }

    /**
     * Check for concurrent sessions
     */
    public function hasConcurrentSessions()
    {
        return static::where('device_binding_id', $this->device_binding_id)
            ->where('id', '!=', $this->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get concurrent session count
     */
    public function getConcurrentCountAttribute()
    {
        return static::where('device_binding_id', $this->device_binding_id)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Validate session for streaming
     */
    public function validateForStreaming($streamId = null)
    {
        $violations = [];

        // Check if session is still valid
        if (!$this->isValid()) {
            $violations[] = 'Session invalid or expired';
        }

        // Check IP address change
        if ($this->ip_address !== request()->ip()) {
            // Check if IP jump is allowed
            $binding = $this->deviceBinding;
            if (!$binding->validateIpAddress(request()->ip())) {
                $violations[] = 'IP address not authorized';

                // Log IP jump violation
                DeviceViolation::create([
                    'subscriber_id' => $this->subscriber->id,
                    'subscription_id' => $binding->subscription_id,
                    'device_fingerprint_id' => $binding->device_fingerprint_id,
                    'violation_type' => 'ip_jump',
                    'severity' => 'medium',
                    'details' => [
                        'original_ip' => $this->ip_address,
                        'new_ip' => request()->ip()
                    ],
                    'ip_address' => request()->ip()
                ]);
            }
        }

        // Check concurrent streams if switching
        if ($streamId && $this->stream_id != $streamId) {
            $maxConcurrent = $this->deviceBinding->subscription->max_concurrent_streams ?? 1;
            $activeSessions = static::where('device_binding_id', $this->device_binding_id)
                ->where('is_active', true)
                ->where('stream_id', '!=', $streamId)
                ->count();

            if ($activeSessions >= $maxConcurrent) {
                $violations[] = 'Concurrent stream limit exceeded';
            }
        }

        return $violations;
    }

    /**
     * Get session statistics
     */
    public function getStatistics()
    {
        return [
            'duration' => $this->duration_formatted,
            'data_transferred' => $this->data_transferred_formatted,
            'average_bitrate' => $this->average_bitrate,
            'concurrent_sessions' => $this->concurrent_count,
            'ip_address' => $this->ip_address,
            'started_at' => $this->started_at->format('Y-m-d H:i:s'),
            'last_activity' => $this->last_activity->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Scope: Active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Inactive sessions
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope: By device binding
     */
    public function scopeByDeviceBinding($query, $bindingId)
    {
        return $query->where('device_binding_id', $bindingId);
    }

    /**
     * Scope: By stream
     */
    public function scopeByStream($query, $streamId)
    {
        return $query->where('stream_id', $streamId);
    }

    /**
     * Scope: Recent sessions
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('started_at', '>', now()->subHours($hours));
    }

    /**
     * Scope: Long sessions
     */
    public function scopeLongSessions($query, $hours = 2)
    {
        return $query->whereRaw('TIMESTAMPDIFF(HOUR, started_at, IFNULL(ended_at, NOW())) > ?', [$hours]);
    }

    /**
     * Scope: High data usage
     */
    public function scopeHighDataUsage($query, $gigabytes = 1)
    {
        $bytes = $gigabytes * 1073741824;
        return $query->where('data_transferred', '>', $bytes);
    }

    /**
     * Clean up old sessions
     */
    public static function cleanup()
    {
        // End timed-out sessions
        $timeoutMinutes = Setting::where('id', 1)->value('device_session_timeout_minutes') ?? 30;

        static::where('is_active', true)
            ->where('last_activity', '<', now()->subMinutes($timeoutMinutes))
            ->update([
                'is_active' => false,
                'ended_at' => now()
            ]);

        // Delete old inactive sessions (older than 7 days)
        static::where('is_active', false)
            ->where('ended_at', '<', now()->subDays(7))
            ->delete();
    }
}