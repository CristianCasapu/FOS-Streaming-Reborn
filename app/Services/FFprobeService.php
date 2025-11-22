<?php

namespace App\Services;

use Exception;

/**
 * FFprobe Stream Analysis Service
 *
 * Analyzes media streams using FFprobe to extract technical characteristics
 * following FFmpeg best practices from official documentation.
 *
 * @see https://ffmpeg.org/ffprobe.html
 */
class FFprobeService
{
    private string $ffprobePath;
    private int $timeout = 30; // seconds
    private int $analyzeDuration = 10; // seconds to analyze for live streams

    public function __construct()
    {
        // Get FFprobe path from environment or use default
        $this->ffprobePath = env('FFPROBE_PATH', '/usr/bin/ffprobe');

        if (!file_exists($this->ffprobePath)) {
            throw new Exception("FFprobe not found at: {$this->ffprobePath}");
        }
    }

    /**
     * Analyze a stream and return comprehensive technical data
     *
     * @param string $streamUrl Stream URL to analyze
     * @param bool $isLive Whether this is a live stream
     * @return array Analysis results
     * @throws Exception If analysis fails
     */
    public function analyzeStream(string $streamUrl, bool $isLive = true): array
    {
        try {
            // Get raw FFprobe data
            $probeData = $this->executeFFprobe($streamUrl, $isLive);

            // Parse and extract relevant information
            $analysis = [
                'status' => 'completed',
                'timestamp' => date('Y-m-d H:i:s'),
                'stream_url' => $streamUrl,
                'is_live' => $isLive,

                // Container/Format info
                'container_format' => $this->extractContainerFormat($probeData),
                'duration' => $this->extractDuration($probeData, $isLive),
                'bitrate' => $this->extractBitrate($probeData),
                'file_size' => $this->extractFileSize($probeData, $isLive),

                // Video stream info
                'video' => $this->extractVideoInfo($probeData),

                // Audio stream info
                'audio' => $this->extractAudioInfo($probeData),

                // Quality metrics
                'health' => $this->calculateHealthScore($probeData),

                // FFprobe profile recommendation
                'profile' => $this->determineProfile($probeData),

                // Recommended settings for playback
                'recommended_settings' => $this->generateRecommendedSettings($probeData),

                // Raw FFprobe output for reference
                'raw_json' => json_encode($probeData, JSON_PRETTY_PRINT)
            ];

            return $analysis;

        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Execute FFprobe command with best practices
     *
     * Uses recommended FFprobe options:
     * - show_format: Display container format information
     * - show_streams: Display stream information
     * - show_entries: Limit output to relevant fields
     * - v quiet: Suppress FFmpeg banner
     * - print_format json: Output in JSON format
     * - analyzeduration: How long to analyze (for live streams)
     * - probesize: How much data to probe
     *
     * @param string $streamUrl Stream URL
     * @param bool $isLive Is this a live stream
     * @return array Parsed JSON output
     * @throws Exception If FFprobe fails
     */
    private function executeFFprobe(string $streamUrl, bool $isLive): array
    {
        $command = [
            escapeshellarg($this->ffprobePath),
            '-v', 'quiet',  // Suppress banner
            '-print_format', 'json',  // JSON output
            '-show_format',  // Show format/container info
            '-show_streams',  // Show stream info
            '-show_error',  // Show errors if any
        ];

        // For live streams, limit analysis duration and probe size
        if ($isLive) {
            $command[] = '-analyzeduration';
            $command[] = ($this->analyzeDuration * 1000000);  // Convert to microseconds
            $command[] = '-probesize';
            $command[] = '10000000';  // 10MB
        }

        // Add timeout for network streams
        $command[] = '-timeout';
        $command[] = ($this->timeout * 1000000);  // Convert to microseconds

        // Add stream URL (must be last)
        $command[] = escapeshellarg($streamUrl);

        $cmd = implode(' ', $command);

        // Execute command with timeout
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new Exception('Failed to execute FFprobe');
        }

        // Set timeout on output stream
        stream_set_timeout($pipes[1], $this->timeout);

        // Read output
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        // Close pipes
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        // Get exit code
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new Exception("FFprobe failed: " . trim($error));
        }

        $data = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse FFprobe JSON output: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Extract container format information
     */
    private function extractContainerFormat(array $probeData): ?string
    {
        return $probeData['format']['format_name'] ?? null;
    }

    /**
     * Extract duration (null for live streams)
     */
    private function extractDuration(array $probeData, bool $isLive): ?float
    {
        if ($isLive) {
            return null;
        }

        $duration = $probeData['format']['duration'] ?? null;
        return $duration ? (float)$duration : null;
    }

    /**
     * Extract total bitrate
     */
    private function extractBitrate(array $probeData): ?int
    {
        $bitrate = $probeData['format']['bit_rate'] ?? null;
        return $bitrate ? (int)$bitrate : null;
    }

    /**
     * Extract file size (null for live streams)
     */
    private function extractFileSize(array $probeData, bool $isLive): ?int
    {
        if ($isLive) {
            return null;
        }

        $size = $probeData['format']['size'] ?? null;
        return $size ? (int)$size : null;
    }

    /**
     * Extract video stream information
     */
    private function extractVideoInfo(array $probeData): ?array
    {
        $streams = $probeData['streams'] ?? [];

        foreach ($streams as $stream) {
            if ($stream['codec_type'] === 'video') {
                return [
                    'codec' => $stream['codec_name'] ?? null,
                    'profile' => $stream['profile'] ?? null,
                    'width' => isset($stream['width']) ? (int)$stream['width'] : null,
                    'height' => isset($stream['height']) ? (int)$stream['height'] : null,
                    'fps' => $this->calculateFPS($stream),
                    'bitrate' => isset($stream['bit_rate']) ? (int)$stream['bit_rate'] : null,
                    'pixel_format' => $stream['pix_fmt'] ?? null,
                    'aspect_ratio' => $stream['display_aspect_ratio'] ?? null,
                    'level' => $stream['level'] ?? null,
                    'color_space' => $stream['color_space'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Calculate FPS from stream data
     */
    private function calculateFPS(array $stream): ?float
    {
        if (isset($stream['avg_frame_rate'])) {
            $parts = explode('/', $stream['avg_frame_rate']);
            if (count($parts) === 2 && $parts[1] > 0) {
                return round((float)$parts[0] / (float)$parts[1], 3);
            }
        }

        if (isset($stream['r_frame_rate'])) {
            $parts = explode('/', $stream['r_frame_rate']);
            if (count($parts) === 2 && $parts[1] > 0) {
                return round((float)$parts[0] / (float)$parts[1], 3);
            }
        }

        return null;
    }

    /**
     * Extract audio stream information
     */
    private function extractAudioInfo(array $probeData): ?array
    {
        $streams = $probeData['streams'] ?? [];

        foreach ($streams as $stream) {
            if ($stream['codec_type'] === 'audio') {
                return [
                    'codec' => $stream['codec_name'] ?? null,
                    'channels' => isset($stream['channels']) ? (int)$stream['channels'] : null,
                    'sample_rate' => isset($stream['sample_rate']) ? (int)$stream['sample_rate'] : null,
                    'bitrate' => isset($stream['bit_rate']) ? (int)$stream['bit_rate'] : null,
                    'language' => $stream['tags']['language'] ?? null,
                    'channel_layout' => $stream['channel_layout'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Calculate stream health score (0-100)
     */
    private function calculateHealthScore(array $probeData): int
    {
        $score = 100;

        // Check if we have both video and audio
        $hasVideo = false;
        $hasAudio = false;

        foreach ($probeData['streams'] ?? [] as $stream) {
            if ($stream['codec_type'] === 'video') $hasVideo = true;
            if ($stream['codec_type'] === 'audio') $hasAudio = true;
        }

        if (!$hasVideo) $score -= 50;
        if (!$hasAudio) $score -= 20;

        // Check if we have bitrate info
        if (!isset($probeData['format']['bit_rate'])) {
            $score -= 10;
        }

        // Check for errors
        if (isset($probeData['error'])) {
            $score -= 30;
        }

        return max(0, $score);
    }

    /**
     * Determine recommended FFprobe profile based on stream characteristics
     */
    private function determineProfile(array $probeData): string
    {
        $streams = $probeData['streams'] ?? [];
        $videoStream = null;
        $audioStream = null;

        foreach ($streams as $stream) {
            if ($stream['codec_type'] === 'video' && !$videoStream) {
                $videoStream = $stream;
            }
            if ($stream['codec_type'] === 'audio' && !$audioStream) {
                $audioStream = $stream;
            }
        }

        // Determine profile based on characteristics
        if (!$videoStream) {
            return 'audio_only';
        }

        $width = $videoStream['width'] ?? 0;
        $codec = $videoStream['codec_name'] ?? '';

        // 4K content
        if ($width >= 3840) {
            return 'uhd_4k';
        }

        // Full HD
        if ($width >= 1920) {
            if (in_array($codec, ['h265', 'hevc', 'vp9'])) {
                return 'fhd_hevc';
            }
            return 'fhd_h264';
        }

        // HD
        if ($width >= 1280) {
            return 'hd_720p';
        }

        // SD
        if ($width >= 720) {
            return 'sd_high';
        }

        return 'sd_standard';
    }

    /**
     * Generate recommended settings for optimal playback
     */
    private function generateRecommendedSettings(array $probeData): array
    {
        $settings = [
            'buffer_size' => '5M',
            'max_analyze_duration' => 5000000,  // 5 seconds
            'max_probe_size' => 5000000,  // 5MB
        ];

        // Get video info
        $videoInfo = $this->extractVideoInfo($probeData);

        if ($videoInfo) {
            // Adjust buffer based on resolution
            $width = $videoInfo['width'] ?? 0;

            if ($width >= 3840) {
                $settings['buffer_size'] = '20M';
                $settings['max_analyze_duration'] = 10000000;
                $settings['max_probe_size'] = 10000000;
            } elseif ($width >= 1920) {
                $settings['buffer_size'] = '10M';
                $settings['max_analyze_duration'] = 8000000;
                $settings['max_probe_size'] = 8000000;
            }

            // Codec-specific settings
            if (in_array($videoInfo['codec'], ['h265', 'hevc'])) {
                $settings['hwaccel'] = 'auto';  // Try hardware acceleration
            }
        }

        return $settings;
    }

    /**
     * Quick stream availability check (lightweight)
     *
     * @param string $streamUrl Stream URL to check
     * @return bool True if stream is accessible
     */
    public function isStreamAccessible(string $streamUrl): bool
    {
        try {
            $command = sprintf(
                '%s -v quiet -read_intervals %%+1 -show_entries format=duration -print_format json %s 2>&1',
                escapeshellarg($this->ffprobePath),
                escapeshellarg($streamUrl)
            );

            $output = shell_exec($command);

            if (!$output) {
                return false;
            }

            $data = json_decode($output, true);
            return isset($data['format']);

        } catch (Exception $e) {
            return false;
        }
    }
}
