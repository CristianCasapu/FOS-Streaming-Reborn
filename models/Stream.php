<?php
class Stream extends FosStreaming {

    protected $fillable = [
        'name',
        'streamurl',
        'streamurl2',
        'streamurl3',
        'source_url',
        'enabled',  // Whether stream can be started/managed (0=disabled, 1=enabled)
        'state',  // Primary state field: stopped, starting, running, stopping, error, crashed
        'cat_id',
        'trans_id',
        'pid',
        'restream',
        'video_codec_name',
        'audio_codec_name',
        'bitstreamfilter',
        'checker',
        'stream_type',
        'stream_mode',
        'logo',
        'tvid',
        'xui_id',
        'timeshift',
        'require_device_lock',
        'allowed_device_types',
        'proxy_settings',
        'encryption_settings',
        'proxy_status',
        'proxy_port',
        'proxy_error',
        'max_connections',
        'current_connections',
        'last_checked'
    ];

    // ========================================
    // Computed Accessors (derived from state)
    // ========================================

    /**
     * Get legacy status value derived from state
     * 0 = stopped, 1 = running, 2 = error
     * @return int
     */
    public function getStatusAttribute()
    {
        return match($this->state) {
            'running' => 1,
            'starting' => 1,
            'error', 'crashed' => 2,
            default => 0
        };
    }

    /**
     * Get legacy running flag derived from state
     * @return int
     */
    public function getRunningAttribute()
    {
        return in_array($this->state, ['running', 'starting']) ? 1 : 0;
    }

    /**
     * Check if stream is currently active (running or starting)
     * @return bool
     */
    public function isActive(): bool
    {
        return in_array($this->state, ['running', 'starting']);
    }

    /**
     * Check if stream is in a stopped/inactive state
     * @return bool
     */
    public function isStopped(): bool
    {
        return in_array($this->state, ['stopped', 'error', 'crashed']);
    }

    /**
     * Check if stream is enabled (can be started/managed)
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }

    /**
     * Check if stream is disabled (should not be started)
     * @return bool
     */
    public function isDisabled(): bool
    {
        return !$this->isEnabled();
    }

    /**
     * Enable the stream
     * @return bool
     */
    public function enable(): bool
    {
        $this->enabled = 1;
        return $this->save();
    }

    /**
     * Disable the stream (will be stopped by monitor worker)
     * @return bool
     */
    public function disable(): bool
    {
        $this->enabled = 0;
        return $this->save();
    }

    public function category()
    {
        return $this->hasOne(Category::class, 'id', 'cat_id');
    }

    public function transcode()
    {
        return $this->hasOne(Transcode::class, 'id', 'trans_id');
    }

    public function getStatusLabelAttribute()
    {
        // Use state as single source of truth
        return match($this->state) {
            'running' => ['label' => 'success', 'text' => 'RUNNING'],
            'starting' => ['label' => 'info', 'text' => 'STARTING'],
            'stopping' => ['label' => 'warning', 'text' => 'STOPPING'],
            'error' => ['label' => 'danger', 'text' => 'ERROR'],
            'crashed' => ['label' => 'danger', 'text' => 'CRASHED'],
            default => ['label' => 'secondary', 'text' => 'STOPPED']
        };
    }

    /**
     * Get analysis status with label
     */
    public function getAnalysisStatusLabelAttribute()
    {
        $labels = [
            'pending' => ['label' => 'secondary', 'text' => 'Pending Analysis'],
            'analyzing' => ['label' => 'info', 'text' => 'Analyzing...'],
            'completed' => ['label' => 'success', 'text' => 'Analyzed'],
            'failed' => ['label' => 'danger', 'text' => 'Analysis Failed']
        ];

        return $labels[$this->analysis_status] ?? ['label' => 'secondary', 'text' => 'Unknown'];
    }

    /**
     * Get video resolution as formatted string
     */
    public function getVideoResolutionAttribute()
    {
        if ($this->video_width && $this->video_height) {
            return "{$this->video_width}x{$this->video_height}";
        }
        return null;
    }

    /**
     * Get video quality label (4K, FHD, HD, SD)
     */
    public function getVideoQualityAttribute()
    {
        if (!$this->video_width) {
            return null;
        }

        if ($this->video_width >= 3840) return '4K UHD';
        if ($this->video_width >= 1920) return 'Full HD';
        if ($this->video_width >= 1280) return 'HD';
        if ($this->video_width >= 720) return 'SD';

        return 'Low Quality';
    }

    /**
     * Get formatted bitrate (Mbps, Kbps)
     */
    public function getFormattedBitrateAttribute()
    {
        if (!$this->bitrate) {
            return null;
        }

        $mbps = $this->bitrate / 1000000;
        if ($mbps >= 1) {
            return number_format($mbps, 2) . ' Mbps';
        }

        $kbps = $this->bitrate / 1000;
        return number_format($kbps, 0) . ' Kbps';
    }

    /**
     * Get formatted duration (HH:MM:SS)
     */
    public function getFormattedDurationAttribute()
    {
        if (!$this->duration) {
            return 'Live';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get audio channel description
     */
    public function getAudioChannelsDescriptionAttribute()
    {
        if (!$this->audio_channels) {
            return null;
        }

        $descriptions = [
            1 => 'Mono',
            2 => 'Stereo',
            6 => '5.1 Surround',
            8 => '7.1 Surround'
        ];

        return $descriptions[$this->audio_channels] ?? "{$this->audio_channels} Channels";
    }

    /**
     * Update stream with FFprobe analysis results
     *
     * @param array $analysis Analysis results from FFprobeService
     * @return bool
     */
    public function updateFromAnalysis(array $analysis): bool
    {
        if ($analysis['status'] === 'failed') {
            $this->analysis_status = 'failed';
            $this->analysis_error = $analysis['error'] ?? 'Unknown error';
            return $this->save();
        }

        // Update analysis status
        $this->analysis_status = 'completed';
        $this->last_analyzed = date('Y-m-d H:i:s');
        $this->analysis_error = null;

        // Update container/format info
        $this->container_format = $analysis['container_format'];
        $this->duration = $analysis['duration'];
        $this->bitrate = $analysis['bitrate'];
        $this->file_size = $analysis['file_size'];

        // Update video info
        if ($analysis['video']) {
            $this->video_codec = $analysis['video']['codec'];
            $this->video_profile = $analysis['video']['profile'];
            $this->video_width = $analysis['video']['width'];
            $this->video_height = $analysis['video']['height'];
            $this->video_fps = $analysis['video']['fps'];
            $this->video_bitrate = $analysis['video']['bitrate'];
            $this->pixel_format = $analysis['video']['pixel_format'];
            $this->aspect_ratio = $analysis['video']['aspect_ratio'];
        }

        // Update audio info
        if ($analysis['audio']) {
            $this->audio_codec = $analysis['audio']['codec'];
            $this->audio_channels = $analysis['audio']['channels'];
            $this->audio_sample_rate = $analysis['audio']['sample_rate'];
            $this->audio_bitrate = $analysis['audio']['bitrate'];
            $this->audio_language = $analysis['audio']['language'];
        }

        // Update health metrics
        $this->health_score = $analysis['health'];

        // Update profile and settings
        $this->ffprobe_profile = $analysis['profile'];
        $this->ffprobe_raw_json = $analysis['raw_json'];
        $this->recommended_settings = json_encode($analysis['recommended_settings']);

        return $this->save();
    }

    /**
     * Check if stream needs analysis
     *
     * @return bool
     */
    public function needsAnalysis(): bool
    {
        // Never analyzed
        if ($this->analysis_status === 'pending' || !$this->last_analyzed) {
            return true;
        }

        // Analysis failed and it's been more than 1 hour
        if ($this->analysis_status === 'failed') {
            $lastAttempt = strtotime($this->last_analyzed ?? '1970-01-01');
            return (time() - $lastAttempt) > 3600;
        }

        // Re-analyze successful streams every 24 hours
        if ($this->analysis_status === 'completed') {
            $lastAnalyzed = strtotime($this->last_analyzed);
            return (time() - $lastAnalyzed) > 86400;
        }

        return false;
    }

    /**
     * Get technical summary for display
     *
     * @return array
     */
    public function getTechnicalSummary(): array
    {
        return [
            'resolution' => $this->videoResolution,
            'quality' => $this->videoQuality,
            'fps' => $this->video_fps ? round($this->video_fps) . ' fps' : null,
            'video_codec' => strtoupper($this->video_codec ?? 'N/A'),
            'audio_codec' => strtoupper($this->audio_codec ?? 'N/A'),
            'audio_channels' => $this->audioChannelsDescription,
            'bitrate' => $this->formattedBitrate,
            'duration' => $this->formattedDuration,
            'container' => strtoupper($this->container_format ?? 'N/A'),
            'profile' => $this->ffprobe_profile,
            'health_score' => $this->health_score
        ];
    }

    // ========================================
    // PM2 Stream Management Methods
    // ========================================

    /**
     * Get stream state label with color
     *
     * @return array
     */
    public function getStateLabelAttribute()
    {
        $labels = [
            'stopped' => ['label' => 'secondary', 'text' => 'Stopped', 'color' => 'gray'],
            'starting' => ['label' => 'info', 'text' => 'Starting...', 'color' => 'blue'],
            'running' => ['label' => 'success', 'text' => 'Running', 'color' => 'green'],
            'stopping' => ['label' => 'warning', 'text' => 'Stopping...', 'color' => 'yellow'],
            'error' => ['label' => 'danger', 'text' => 'Error', 'color' => 'red'],
            'crashed' => ['label' => 'danger', 'text' => 'Crashed', 'color' => 'red']
        ];

        return $labels[$this->state] ?? ['label' => 'secondary', 'text' => 'Unknown', 'color' => 'gray'];
    }

    /**
     * Queue a command to be executed by stream-manager worker
     *
     * @param string $command Command to queue (start, stop, restart)
     * @param int|null $queuedBy Admin/user ID who queued the command
     * @return bool
     */
    public function queueCommand(string $command, ?int $queuedBy = null): bool
    {
        $validCommands = ['start', 'stop', 'restart'];

        if (!in_array($command, $validCommands)) {
            return false;
        }

        // Prevent starting disabled streams
        if (in_array($command, ['start', 'restart']) && $this->isDisabled()) {
            return false;
        }

        $this->scheduled_command = $command;
        $this->command_queued_at = date('Y-m-d H:i:s');
        $this->command_queued_by = $queuedBy;

        return $this->save();
    }

    /**
     * Clear scheduled command
     *
     * @param string $result Result of command execution
     * @return bool
     */
    public function clearScheduledCommand(string $result = 'success'): bool
    {
        $this->scheduled_command = 'none';
        $this->last_command_at = date('Y-m-d H:i:s');
        $this->last_command_result = $result;
        $this->command_queued_at = null;
        $this->command_queued_by = null;

        return $this->save();
    }

    /**
     * Update stream state
     *
     * @param string $newState New state
     * @return bool
     */
    public function updateState(string $newState): bool
    {
        $validStates = ['stopped', 'starting', 'running', 'stopping', 'error', 'crashed'];

        if (!in_array($newState, $validStates)) {
            return false;
        }

        $this->state = $newState;

        // Update lifecycle timestamps based on state
        if ($newState === 'running' && !$this->stream_started_at) {
            $this->stream_started_at = date('Y-m-d H:i:s');
        }

        if (in_array($newState, ['stopped', 'error', 'crashed'])) {
            $this->stream_stopped_at = date('Y-m-d H:i:s');
            $this->pid = null; // Clear PID when stopped

            // Calculate uptime if we have start time
            if ($this->stream_started_at) {
                $uptime = strtotime($this->stream_stopped_at) - strtotime($this->stream_started_at);
                $this->current_uptime = $uptime;
                $this->total_uptime += $uptime;
            }
        }

        return $this->save();
    }

    /**
     * Mark stream as crashed and handle auto-restart
     *
     * @return bool
     */
    public function markAsCrashed(): bool
    {
        $this->state = 'crashed';
        $this->crash_count++;
        $this->last_crash_at = date('Y-m-d H:i:s');
        $this->pid = null;

        // Check if we should auto-restart
        if ($this->auto_restart_enabled && $this->restart_attempts < $this->max_restart_attempts) {
            $this->scheduled_command = 'start';
            $this->command_queued_at = date('Y-m-d H:i:s');
            $this->restart_attempts++;
        } else {
            // Max restarts reached, mark as error
            $this->state = 'error';
            $this->restart_attempts = 0; // Reset for manual intervention
        }

        return $this->save();
    }

    /**
     * Reset restart attempts counter
     * Called when stream successfully runs for min_uptime
     *
     * @return bool
     */
    public function resetRestartAttempts(): bool
    {
        $this->restart_attempts = 0;
        return $this->save();
    }

    /**
     * Check if PID is still running
     *
     * @return bool
     */
    public function isPidAlive(): bool
    {
        if (!$this->pid) {
            return false;
        }

        // Check if process exists
        exec("ps -p {$this->pid} > /dev/null 2>&1", $output, $exitCode);
        return $exitCode === 0;
    }

    /**
     * Get current uptime in seconds
     *
     * @return int
     */
    public function getCurrentUptimeSeconds(): int
    {
        if ($this->state !== 'running' || !$this->stream_started_at) {
            return 0;
        }

        return time() - strtotime($this->stream_started_at);
    }

    /**
     * Update health check timestamp
     *
     * @param bool $healthy Is stream healthy?
     * @return bool
     */
    public function recordHealthCheck(bool $healthy): bool
    {
        $this->last_health_check = date('Y-m-d H:i:s');

        if (!$healthy) {
            $this->health_check_failures++;
        } else {
            $this->health_check_failures = 0;
        }

        return $this->save();
    }

    /**
     * Check if stream should be auto-restarted
     *
     * @return bool
     */
    public function shouldAutoRestart(): bool
    {
        return $this->isEnabled()  // Must be enabled
            && $this->auto_restart_enabled
            && $this->restart_attempts < $this->max_restart_attempts
            && in_array($this->state, ['crashed', 'error']);
    }
}