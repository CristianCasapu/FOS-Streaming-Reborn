<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create streams table (foundation table)
     *
     * This is the core table for the IPTV platform - all streaming content
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('streams')) {
            $schema->create('streams', function (Blueprint $table) {
                $table->increments('id');

                // Basic Stream Information
                $table->string('name', 255)->nullable()->comment('Internal stream name');
                $table->string('stream_display_name', 255)->nullable()->comment('Display name for subscribers');
                $table->text('streamurl')->nullable()->comment('Primary stream URL');
                $table->text('streamurl2')->nullable()->comment('Backup stream URL');
                $table->text('streamurl3')->nullable()->comment('Tertiary stream URL');
                $table->text('source_url')->nullable()->comment('Original source URL');

                // Status & State
                $table->boolean('enabled')->default(true)->comment('Is stream enabled?');
                $table->tinyInteger('running')->default(0)->comment('Legacy: 0=stopped, 1=running');
                $table->tinyInteger('status')->default(0)->comment('Legacy: 0=stopped, 1=running, 2=error');
                $table->enum('state', ['stopped', 'starting', 'running', 'stopping', 'error', 'crashed'])
                    ->default('stopped')
                    ->comment('PM2 managed state');

                // Categories & Relations
                $table->unsignedInteger('cat_id')->nullable()->comment('Category ID');
                $table->unsignedInteger('trans_id')->nullable()->comment('Transcode profile ID');

                // Process Management
                $table->integer('pid')->nullable()->comment('Process ID when running');
                $table->boolean('restream')->default(false)->comment('Enable restreaming');
                $table->string('checker', 50)->nullable()->comment('Health checker type');

                // Stream Type & Mode
                $table->enum('stream_type', ['live', 'vod', 'on_demand'])->default('live');
                $table->string('stream_mode', 50)->nullable()->comment('restream, transcode, passthrough');

                // Media & Branding
                $table->string('logo', 500)->nullable()->comment('Channel logo URL');
                $table->string('tvid', 100)->nullable()->comment('EPG/TV guide ID');

                // Codec Information
                $table->string('video_codec_name', 50)->nullable();
                $table->string('audio_codec_name', 50)->nullable();
                $table->string('bitstreamfilter', 255)->nullable();

                // Security & Device Locking
                $table->boolean('require_device_lock')->default(false)
                    ->comment('Require device fingerprint verification');
                $table->json('allowed_device_types')->nullable()
                    ->comment('Allowed device types: web, mobile, tv, etc.');

                // Proxy & Encryption Settings
                $table->json('proxy_settings')->nullable()->comment('V2Ray/SRT proxy configuration');
                $table->json('encryption_settings')->nullable()->comment('Encryption keys and settings');
                $table->string('proxy_status', 20)->default('inactive')
                    ->comment('inactive, active, error');
                $table->unsignedInteger('proxy_port')->nullable()->comment('Assigned proxy port');
                $table->text('proxy_error')->nullable()->comment('Last proxy error message');

                // Connection Limits
                $table->unsignedInteger('max_connections')->default(100)
                    ->comment('Maximum concurrent connections');
                $table->unsignedInteger('current_connections')->default(0)
                    ->comment('Current active connections');

                // Analysis Status (FFprobe)
                $table->enum('analysis_status', ['pending', 'analyzing', 'completed', 'failed'])
                    ->default('pending')->comment('Stream analysis state');
                $table->text('analysis_error')->nullable()->comment('Analysis error message');
                $table->timestamp('last_analyzed')->nullable()->comment('Last successful analysis');

                // Container & Format Information
                $table->string('container_format', 50)->nullable()->comment('mp4, mkv, ts, etc.');
                $table->bigInteger('duration')->nullable()->comment('Duration in seconds (0 for live)');
                $table->bigInteger('bitrate')->nullable()->comment('Overall bitrate in bps');
                $table->bigInteger('file_size')->nullable()->comment('File size in bytes (VOD only)');

                // Video Stream Details
                $table->string('video_codec', 50)->nullable()->comment('h264, h265, vp9, etc.');
                $table->string('video_profile', 50)->nullable()->comment('Codec profile (high, main, etc.)');
                $table->unsignedInteger('video_width')->nullable()->comment('Video width in pixels');
                $table->unsignedInteger('video_height')->nullable()->comment('Video height in pixels');
                $table->decimal('video_fps', 8, 2)->nullable()->comment('Frames per second');
                $table->bigInteger('video_bitrate')->nullable()->comment('Video bitrate in bps');
                $table->string('pixel_format', 50)->nullable()->comment('yuv420p, etc.');
                $table->string('aspect_ratio', 20)->nullable()->comment('16:9, 4:3, etc.');

                // Audio Stream Details
                $table->string('audio_codec', 50)->nullable()->comment('aac, mp3, opus, etc.');
                $table->unsignedTinyInteger('audio_channels')->nullable()->comment('Number of audio channels');
                $table->unsignedInteger('audio_sample_rate')->nullable()->comment('Sample rate in Hz');
                $table->bigInteger('audio_bitrate')->nullable()->comment('Audio bitrate in bps');
                $table->string('audio_language', 10)->nullable()->comment('ISO language code');

                // Health & Quality
                $table->unsignedTinyInteger('health_score')->nullable()->comment('0-100 health score');
                $table->unsignedInteger('health_check_failures')->default(0)
                    ->comment('Consecutive health check failures');
                $table->timestamp('last_health_check')->nullable();
                $table->timestamp('last_checked')->nullable()->comment('Legacy health check field');

                // FFprobe Profile & Raw Data
                $table->string('ffprobe_profile', 100)->nullable()
                    ->comment('premium, standard, basic, poor');
                $table->json('ffprobe_raw_json')->nullable()->comment('Full FFprobe output');
                $table->json('recommended_settings')->nullable()
                    ->comment('Recommended transcode settings');

                // PM2 Command Queue
                $table->enum('scheduled_command', ['none', 'start', 'stop', 'restart'])
                    ->default('none')->comment('Queued command for stream-manager');
                $table->timestamp('command_queued_at')->nullable();
                $table->unsignedInteger('command_queued_by')->nullable()
                    ->comment('Admin ID who queued command');
                $table->timestamp('last_command_at')->nullable();
                $table->string('last_command_result', 50)->nullable()
                    ->comment('success, failed, timeout');

                // Stream Lifecycle
                $table->timestamp('stream_started_at')->nullable()
                    ->comment('When stream was last started');
                $table->timestamp('stream_stopped_at')->nullable()
                    ->comment('When stream was last stopped');
                $table->bigInteger('current_uptime')->default(0)
                    ->comment('Current/last session uptime in seconds');
                $table->bigInteger('total_uptime')->default(0)
                    ->comment('Total uptime across all sessions');

                // Auto-Restart Configuration
                $table->boolean('auto_restart_enabled')->default(true)
                    ->comment('Enable automatic restart on crash');
                $table->unsignedTinyInteger('max_restart_attempts')->default(3)
                    ->comment('Max auto-restart attempts before giving up');
                $table->unsignedTinyInteger('restart_attempts')->default(0)
                    ->comment('Current restart attempt count');
                $table->unsignedInteger('crash_count')->default(0)
                    ->comment('Total number of crashes');
                $table->timestamp('last_crash_at')->nullable();

                // Timestamps
                $table->timestamps();

                // Indexes for Performance
                $table->index('enabled');
                $table->index('running');
                $table->index('status');
                $table->index('state');
                $table->index('cat_id');
                $table->index('trans_id');
                $table->index('stream_type');
                $table->index('analysis_status');
                $table->index('scheduled_command');
                $table->index(['state', 'scheduled_command']); // Compound for worker queries
                $table->index('created_at');
            });

            Capsule::statement("ALTER TABLE streams COMMENT = 'IPTV streams - core content table'");

            echo "✓ Streams table created successfully\n";
        } else {
            echo "⊘ Streams table already exists\n";
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('streams');
        echo "✓ Streams table dropped\n";
    }
};
