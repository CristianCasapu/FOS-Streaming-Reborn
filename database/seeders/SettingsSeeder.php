<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

class SettingsSeeder extends Seeder
{
    public $command;

    /**
     * Detect FFmpeg binary path
     *
     * Searches common locations and returns the first valid path found.
     */
    private function detectFfmpegPath(): string
    {
        $possiblePaths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/ffmpeg/bin/ffmpeg',
            '/snap/bin/ffmpeg',
        ];

        // Try 'which' command first
        $whichResult = trim(shell_exec('which ffmpeg 2>/dev/null') ?? '');
        if (!empty($whichResult) && is_executable($whichResult)) {
            return $whichResult;
        }

        // Check common paths
        foreach ($possiblePaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        // Default fallback
        return '/usr/bin/ffmpeg';
    }

    /**
     * Detect FFprobe binary path
     *
     * Searches common locations and returns the first valid path found.
     */
    private function detectFfprobePath(): string
    {
        $possiblePaths = [
            '/usr/bin/ffprobe',
            '/usr/local/bin/ffprobe',
            '/opt/ffmpeg/bin/ffprobe',
            '/snap/bin/ffprobe',
        ];

        // Try 'which' command first
        $whichResult = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');
        if (!empty($whichResult) && is_executable($whichResult)) {
            return $whichResult;
        }

        // Check common paths
        foreach ($possiblePaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        // Default fallback
        return '/usr/bin/ffprobe';
    }

    /**
     * Run the database seeds.
     *
     * Seed default settings with all columns from columnar schema
     */
    public function run(): void
    {
        $timestamp = date('Y-m-d H:i:s');

        // Detect FFmpeg and FFprobe paths
        $ffmpegPath = $this->detectFfmpegPath();
        $ffprobePath = $this->detectFfprobePath();

        if ($this->command) {
            $this->command->info("Detected FFmpeg at: {$ffmpegPath}");
            $this->command->info("Detected FFprobe at: {$ffprobePath}");
        }

        // Check if settings already exist
        $settingsExist = Capsule::table('settings')->where('id', 1)->exists();

        if (!$settingsExist) {
            Capsule::table('settings')->insert([
                'id' => 1,

                // Core System Settings (using detected paths)
                'ffmpeg_path' => $ffmpegPath,
                'ffprobe_path' => $ffprobePath,
                'webip' => null,
                'webport' => 8000,
                'hlsfolder' => '/tmp/hls',
                'logourl' => '/assets/logo-default.svg',
                'faviconurl' => '/favicon.ico',
                'user_agent' => 'FOS-Streaming/v70.0',

                // Sudo & System Commands
                'sudo_user' => null,
                'sudo_password' => null,
                'system_commands_enabled' => 0,
                'last_command_at' => null,

                // Trial Subscription Settings
                'trial_duration_hours' => 24,
                'trial_enabled' => 1,
                'trial_requires_approval' => 0,
                'max_trials_per_user' => 1,

                // Device Fingerprinting & Security Settings
                'device_concurrent_stream_grace_seconds' => 30,
                'device_session_timeout_minutes' => 60,
                'device_max_registration_per_day' => 5,
                'device_fingerprint_ttl_days' => 365,

                // Device Violation Thresholds
                'device_violation_threshold_low' => 3,
                'device_violation_threshold_medium' => 5,
                'device_violation_threshold_high' => 10,
                'device_violation_threshold_critical' => 15,
                'device_violation_window_hours' => 24,
                'device_location_accuracy_km' => 100,

                // Streaming Protocol Settings
                'streaming_protocol' => 'both',
                'streams_path' => null,
                'rtmp_port' => 1935,
                'streaming_port' => 8000,
                'nginx_user' => null,
                'nginx_worker_processes' => 0,
                'dash_fragment' => 4,
                'dash_playlist_length' => 30,
                'dash_nested' => 1,
                'dash_cleanup' => 1,
                'hls_fragment' => 3,
                'hls_playlist_length' => 60,
                'hls_nested' => 1,
                'hls_cleanup' => 1,
                'nginx_config_path' => null,
                'nginx_binary_path' => null,

                // Timestamps
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            if ($this->command) {
                $this->command->info('Settings seeded successfully with detected FFmpeg paths!');
            }
        } else {
            // Update existing settings - use detected paths as fallback for COALESCE
            Capsule::table('settings')->where('id', 1)->update([
                // Core System Settings (use COALESCE to keep existing values, detected path as fallback)
                'ffmpeg_path' => Capsule::raw("COALESCE(NULLIF(ffmpeg_path, ''), '{$ffmpegPath}')"),
                'ffprobe_path' => Capsule::raw("COALESCE(NULLIF(ffprobe_path, ''), '{$ffprobePath}')"),
                'webport' => Capsule::raw('COALESCE(webport, 8000)'),
                'hlsfolder' => Capsule::raw("COALESCE(hlsfolder, '/tmp/hls')"),
                'logourl' => Capsule::raw("COALESCE(logourl, '/assets/logo-default.svg')"),
                'faviconurl' => Capsule::raw("COALESCE(faviconurl, '/favicon.ico')"),
                'user_agent' => Capsule::raw("COALESCE(user_agent, 'FOS-Streaming/v70.0')"),

                // Sudo & System Commands
                'system_commands_enabled' => Capsule::raw('COALESCE(system_commands_enabled, 0)'),

                // Trial Settings
                'trial_duration_hours' => Capsule::raw('COALESCE(trial_duration_hours, 24)'),
                'trial_enabled' => Capsule::raw('COALESCE(trial_enabled, 1)'),
                'trial_requires_approval' => Capsule::raw('COALESCE(trial_requires_approval, 0)'),
                'max_trials_per_user' => Capsule::raw('COALESCE(max_trials_per_user, 1)'),

                // Device Security Settings
                'device_concurrent_stream_grace_seconds' => Capsule::raw('COALESCE(device_concurrent_stream_grace_seconds, 30)'),
                'device_session_timeout_minutes' => Capsule::raw('COALESCE(device_session_timeout_minutes, 60)'),
                'device_max_registration_per_day' => Capsule::raw('COALESCE(device_max_registration_per_day, 5)'),
                'device_fingerprint_ttl_days' => Capsule::raw('COALESCE(device_fingerprint_ttl_days, 365)'),

                // Device Violation Thresholds
                'device_violation_threshold_low' => Capsule::raw('COALESCE(device_violation_threshold_low, 3)'),
                'device_violation_threshold_medium' => Capsule::raw('COALESCE(device_violation_threshold_medium, 5)'),
                'device_violation_threshold_high' => Capsule::raw('COALESCE(device_violation_threshold_high, 10)'),
                'device_violation_threshold_critical' => Capsule::raw('COALESCE(device_violation_threshold_critical, 15)'),
                'device_violation_window_hours' => Capsule::raw('COALESCE(device_violation_window_hours, 24)'),
                'device_location_accuracy_km' => Capsule::raw('COALESCE(device_location_accuracy_km, 100)'),

                // Streaming Protocol Settings
                'streaming_protocol' => Capsule::raw("COALESCE(streaming_protocol, 'both')"),
                'rtmp_port' => Capsule::raw('COALESCE(rtmp_port, 1935)'),
                'streaming_port' => Capsule::raw('COALESCE(streaming_port, 8000)'),
                'nginx_worker_processes' => Capsule::raw('COALESCE(nginx_worker_processes, 0)'),
                'dash_fragment' => Capsule::raw('COALESCE(dash_fragment, 4)'),
                'dash_playlist_length' => Capsule::raw('COALESCE(dash_playlist_length, 30)'),
                'dash_nested' => Capsule::raw('COALESCE(dash_nested, 1)'),
                'dash_cleanup' => Capsule::raw('COALESCE(dash_cleanup, 1)'),
                'hls_fragment' => Capsule::raw('COALESCE(hls_fragment, 3)'),
                'hls_playlist_length' => Capsule::raw('COALESCE(hls_playlist_length, 60)'),
                'hls_nested' => Capsule::raw('COALESCE(hls_nested, 1)'),
                'hls_cleanup' => Capsule::raw('COALESCE(hls_cleanup, 1)'),

                'updated_at' => $timestamp,
            ]);

            if ($this->command) {
                $this->command->info('Settings updated with detected FFmpeg paths!');
            }
        }
    }
}
