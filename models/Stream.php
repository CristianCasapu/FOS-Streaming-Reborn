<?php
class Stream extends FosStreaming {

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
        $return = [];
        $return['label'] = 'danger';
        $return['text'] = 'STOPPED';

        if ($this->status == '1') {
            $return['label'] = 'success';
            $return['text'] = 'RUNNING';
        } else if ($this->status == '2') {
            $return['label'] = 'danger';
            $return['text'] = 'ERROR';
        }

        return $return;
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
}