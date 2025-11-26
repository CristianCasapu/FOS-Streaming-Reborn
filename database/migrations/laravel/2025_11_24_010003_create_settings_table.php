<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create settings table with columnar design
     *
     * This table uses a single-row columnar design for performance and type safety.
     * Each setting is a column rather than a key-value pair.
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        // Drop if exists (to handle migration from key-value design)
        $schema->dropIfExists('settings');

        $schema->create('settings', function (Blueprint $table) {
            $table->increments('id');

            // =========================================================================
            // Core System Settings
            // =========================================================================
            $table->string('ffmpeg_path', 255)->default('/usr/bin/ffmpeg')
                ->comment('Path to FFmpeg binary');
            $table->string('ffprobe_path', 255)->default('/usr/bin/ffprobe')
                ->comment('Path to FFprobe binary');
            $table->string('webip', 255)->nullable()
                ->comment('Web server domain or IP address');
            $table->integer('webport')->default(8000)
                ->comment('Web interface port');
            $table->string('hlsfolder', 255)->default('/tmp/hls')
                ->comment('Directory for HLS stream segments');
            $table->string('logourl', 255)->nullable()->default('/assets/logo-default.svg')
                ->comment('Application logo URL');
            $table->string('faviconurl', 255)->nullable()->default('/favicon.ico')
                ->comment('Application favicon URL');
            $table->string('user_agent', 255)->default('FOS-Streaming/v70.0')
                ->comment('Default user agent for HTTP requests');

            // =========================================================================
            // Sudo & System Commands
            // =========================================================================
            $table->string('sudo_user', 100)->nullable()
                ->comment('System user for sudo commands');
            $table->text('sudo_password')->nullable()
                ->comment('Encrypted sudo password (AES-256 via Laravel Crypt)');
            $table->boolean('system_commands_enabled')->default(false)
                ->comment('Enable/disable system command execution');
            $table->timestamp('last_command_at')->nullable()
                ->comment('Timestamp of last system command execution');

            // =========================================================================
            // Trial Subscription Settings
            // =========================================================================
            $table->integer('trial_duration_hours')->default(24)
                ->comment('Default trial subscription duration in hours');
            $table->boolean('trial_enabled')->default(true)
                ->comment('Enable/disable trial subscription system');
            $table->boolean('trial_requires_approval')->default(false)
                ->comment('Require admin approval for trial activation');
            $table->integer('max_trials_per_user')->default(1)
                ->comment('Maximum trials per subscriber');

            // =========================================================================
            // Device Fingerprinting & Security Settings
            // =========================================================================
            $table->integer('device_concurrent_stream_grace_seconds')->default(30)
                ->comment('Grace period for concurrent stream detection');
            $table->integer('device_session_timeout_minutes')->default(60)
                ->comment('Device session timeout in minutes');
            $table->integer('device_max_registration_per_day')->default(5)
                ->comment('Maximum device registrations per day per subscriber');
            $table->integer('device_fingerprint_ttl_days')->default(365)
                ->comment('Device fingerprint time-to-live in days');

            // =========================================================================
            // Device Violation Thresholds
            // =========================================================================
            $table->integer('device_violation_threshold_low')->default(3)
                ->comment('Low severity violation threshold');
            $table->integer('device_violation_threshold_medium')->default(5)
                ->comment('Medium severity violation threshold');
            $table->integer('device_violation_threshold_high')->default(10)
                ->comment('High severity violation threshold');
            $table->integer('device_violation_threshold_critical')->default(15)
                ->comment('Critical severity violation threshold');
            $table->integer('device_violation_window_hours')->default(24)
                ->comment('Time window for counting violations');
            $table->integer('device_location_accuracy_km')->default(100)
                ->comment('Maximum allowed distance for location-based violations');

            // =========================================================================
            // Streaming Protocol Settings
            // =========================================================================
            $table->enum('streaming_protocol', ['dash', 'hls', 'both'])->default('both')
                ->comment('Primary streaming protocol');
            $table->string('streams_path', 255)->nullable()
                ->comment('Path for stream segment storage');
            $table->unsignedInteger('rtmp_port')->default(1935)
                ->comment('RTMP ingest port');
            $table->unsignedInteger('streaming_port')->default(8000)
                ->comment('HTTP streaming server port');
            $table->string('nginx_user', 50)->nullable()
                ->comment('User to run nginx streaming service as');
            $table->integer('nginx_worker_processes')->default(0)
                ->comment('Nginx worker processes (0=auto)');
            $table->unsignedInteger('dash_fragment')->default(4)
                ->comment('DASH fragment duration in seconds');
            $table->unsignedInteger('dash_playlist_length')->default(30)
                ->comment('DASH playlist length in seconds');
            $table->boolean('dash_nested')->default(true)
                ->comment('Use nested directories for DASH streams');
            $table->boolean('dash_cleanup')->default(true)
                ->comment('Auto cleanup old DASH segments');
            $table->unsignedInteger('hls_fragment')->default(3)
                ->comment('HLS fragment duration in seconds');
            $table->unsignedInteger('hls_playlist_length')->default(60)
                ->comment('HLS playlist length in seconds');
            $table->boolean('hls_nested')->default(true)
                ->comment('Use nested directories for HLS streams');
            $table->boolean('hls_cleanup')->default(true)
                ->comment('Auto cleanup old HLS segments');
            $table->string('nginx_config_path', 255)->nullable()
                ->comment('Path to nginx streaming config file');
            $table->string('nginx_binary_path', 255)->nullable()
                ->comment('Path to nginx binary for streaming');

            // =========================================================================
            // Timestamps
            // =========================================================================
            $table->timestamps();

            // Indexes
            $table->index('webport');
            $table->index('trial_enabled');
            $table->index('system_commands_enabled');
            $table->index('streaming_protocol');
        });

        Capsule::statement("ALTER TABLE settings COMMENT = 'System settings - single row columnar design'");

        echo "✓ Settings table created (columnar design)\n";
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('settings');
        echo "✓ Settings table dropped\n";
    }
};
