<?php
/**
 * Setting Model
 *
 * Represents system-wide configuration settings
 * Single-row table with columnar design for performance
 *
 * @property int $id
 * @property string $ffmpeg_path
 * @property string $ffprobe_path
 * @property string|null $webip
 * @property int $webport
 * @property string $hlsfolder
 * @property string|null $logourl
 * @property string|null $faviconurl
 * @property string $user_agent
 * @property string|null $sudo_user
 * @property string|null $sudo_password Encrypted
 * @property bool $system_commands_enabled
 * @property \Carbon\Carbon|null $last_command_at
 * @property int $trial_duration_hours
 * @property bool $trial_enabled
 * @property bool $trial_requires_approval
 * @property int $max_trials_per_user
 * @property int $device_concurrent_stream_grace_seconds
 * @property int $device_session_timeout_minutes
 * @property int $device_max_registration_per_day
 * @property int $device_fingerprint_ttl_days
 * @property int $device_violation_threshold_low
 * @property int $device_violation_threshold_medium
 * @property int $device_violation_threshold_high
 * @property int $device_violation_threshold_critical
 * @property int $device_violation_window_hours
 * @property int $device_location_accuracy_km
 * @property string $streaming_protocol dash|hls|both
 * @property string $streams_path Path for stream segment storage
 * @property int $rtmp_port RTMP ingest port
 * @property int $streaming_port HTTP streaming server port
 * @property string|null $nginx_user User to run nginx as
 * @property int $nginx_worker_processes Nginx workers (0=auto)
 * @property int $dash_fragment DASH fragment duration (seconds)
 * @property int $dash_playlist_length DASH playlist length (seconds)
 * @property bool $dash_nested Use nested directories for DASH
 * @property bool $dash_cleanup Auto cleanup old DASH segments
 * @property int $hls_fragment HLS fragment duration (seconds)
 * @property int $hls_playlist_length HLS playlist length (seconds)
 * @property bool $hls_nested Use nested directories for HLS
 * @property bool $hls_cleanup Auto cleanup old HLS segments
 * @property string|null $nginx_config_path Path to nginx streaming config
 * @property string|null $nginx_binary_path Path to nginx binary
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Setting extends FosStreaming {

    protected $table = 'settings';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        // Core System Settings
        'ffmpeg_path',
        'ffprobe_path',
        'webip',
        'webport',
        'hlsfolder',
        'logourl',
        'faviconurl',
        'user_agent',

        // Sudo & System Commands
        'sudo_user',
        'sudo_password',
        'system_commands_enabled',
        'last_command_at',

        // Trial Subscription Settings
        'trial_duration_hours',
        'trial_enabled',
        'trial_requires_approval',
        'max_trials_per_user',

        // Device Fingerprinting & Security Settings
        'device_concurrent_stream_grace_seconds',
        'device_session_timeout_minutes',
        'device_max_registration_per_day',
        'device_fingerprint_ttl_days',

        // Device Violation Thresholds
        'device_violation_threshold_low',
        'device_violation_threshold_medium',
        'device_violation_threshold_high',
        'device_violation_threshold_critical',
        'device_violation_window_hours',
        'device_location_accuracy_km',

        // Streaming Protocol Settings
        'streaming_protocol',
        'streams_path',
        'rtmp_port',
        'streaming_port',
        'nginx_user',
        'nginx_worker_processes',

        // MPEG-DASH Settings
        'dash_fragment',
        'dash_playlist_length',
        'dash_nested',
        'dash_cleanup',

        // HLS Settings
        'hls_fragment',
        'hls_playlist_length',
        'hls_nested',
        'hls_cleanup',

        // Nginx Paths
        'nginx_config_path',
        'nginx_binary_path',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'id' => 'integer',
        'webport' => 'integer',
        'system_commands_enabled' => 'boolean',
        'last_command_at' => 'datetime',
        'trial_duration_hours' => 'integer',
        'trial_enabled' => 'boolean',
        'trial_requires_approval' => 'boolean',
        'max_trials_per_user' => 'integer',
        'device_concurrent_stream_grace_seconds' => 'integer',
        'device_session_timeout_minutes' => 'integer',
        'device_max_registration_per_day' => 'integer',
        'device_fingerprint_ttl_days' => 'integer',
        'device_violation_threshold_low' => 'integer',
        'device_violation_threshold_medium' => 'integer',
        'device_violation_threshold_high' => 'integer',
        'device_violation_threshold_critical' => 'integer',
        'device_violation_window_hours' => 'integer',
        'device_location_accuracy_km' => 'integer',
        // Streaming Protocol Settings
        'rtmp_port' => 'integer',
        'streaming_port' => 'integer',
        'nginx_worker_processes' => 'integer',
        'dash_fragment' => 'integer',
        'dash_playlist_length' => 'integer',
        'dash_nested' => 'boolean',
        'dash_cleanup' => 'boolean',
        'hls_fragment' => 'integer',
        'hls_playlist_length' => 'integer',
        'hls_nested' => 'boolean',
        'hls_cleanup' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Attributes that should be mutated to dates
     */
    protected $dates = [
        'last_command_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Get the singleton settings instance
     *
     * @return Setting|null
     */
    public static function getInstance()
    {
        return static::first();
    }

    /**
     * Get a setting value with fallback
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue($key, $default = null)
    {
        $settings = static::first();
        return $settings ? ($settings->$key ?? $default) : $default;
    }

    /**
     * Update a setting value
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function setValue($key, $value)
    {
        $settings = static::first();
        if (!$settings) {
            return false;
        }

        $settings->$key = $value;
        return $settings->save();
    }
}