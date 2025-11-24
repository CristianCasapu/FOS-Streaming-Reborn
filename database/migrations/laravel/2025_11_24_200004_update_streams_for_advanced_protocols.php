<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations - Phase 1 Foundation & Phase 2 SRT
     *
     * NOTE: Most columns are now in create_streams_table migration
     * This only adds NEW columns that weren't in the original design
     */
    public function up(): void
    {
        $schema = \Illuminate\Database\Capsule\Manager::schema();

        // Skip if streams table doesn't exist yet
        if (!$schema->hasTable('streams')) {
            echo "⊘ Streams table doesn't exist, skipping update migration\n";
            return;
        }

        $schema->table('streams', function (Blueprint $table) use ($schema) {
            // Only add columns that don't already exist

            // VOD support - add if not exist
            if (!$schema->hasColumn('streams', 'vod_path')) {
                $table->string('vod_path', 500)->nullable()->comment('Path to VOD file');
            }
            if (!$schema->hasColumn('streams', 'vod_duration_seconds')) {
                $table->unsignedInteger('vod_duration_seconds')->nullable();
            }
            if (!$schema->hasColumn('streams', 'vod_metadata')) {
                $table->json('vod_metadata')->nullable()->comment('Title, description, thumbnail, etc.');
            }

            // Protocol - add if not exist
            if (!$schema->hasColumn('streams', 'protocol')) {
                $table->string('protocol', 20)->default('rtmp')->comment('rtmp, srt, http, hls');
            }
            if (!$schema->hasColumn('streams', 'srt_enabled')) {
                $table->boolean('srt_enabled')->default(false);
            }

            // Multi-source fallback - add if not exist
            if (!$schema->hasColumn('streams', 'fallback_url_1')) {
                $table->string('fallback_url_1', 500)->nullable();
            }
            if (!$schema->hasColumn('streams', 'fallback_url_2')) {
                $table->string('fallback_url_2', 500)->nullable();
            }
            if (!$schema->hasColumn('streams', 'fallback_url_3')) {
                $table->string('fallback_url_3', 500)->nullable();
            }
            if (!$schema->hasColumn('streams', 'auto_fallback')) {
                $table->boolean('auto_fallback')->default(false);
            }

            // Buffering - add if not exist
            if (!$schema->hasColumn('streams', 'buffer_size_mb')) {
                $table->unsignedInteger('buffer_size_mb')->default(10)->comment('Buffer size in MB');
            }
            if (!$schema->hasColumn('streams', 'segment_duration_seconds')) {
                $table->unsignedInteger('segment_duration_seconds')->default(2);
            }

            // Health monitoring - add if not exist
            if (!$schema->hasColumn('streams', 'health_check_interval')) {
                $table->unsignedInteger('health_check_interval')->default(30)->comment('Seconds between checks');
            }
            if (!$schema->hasColumn('streams', 'health_metrics')) {
                $table->json('health_metrics')->nullable()->comment('Bitrate, fps, codec info');
            }

        });

        echo "✓ Streams table updated with advanced protocol columns\n";

        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE streams COMMENT = "Streams with support for SRT, QUIC, V2Ray, VOD, and proxy-only mode"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Database\Capsule\Manager::schema()->table('streams', function (Blueprint $table) {
            $table->dropIndex(['streams_stream_type_index']);
            $table->dropIndex(['streams_stream_mode_index']);
            $table->dropIndex(['streams_protocol_index']);
            $table->dropIndex(['streams_srt_enabled_index']);
            $table->dropIndex(['streams_proxy_status_index']);
            $table->dropIndex(['streams_enabled_stream_mode_stream_type_index']);

            $table->dropColumn([
                'stream_type',
                'stream_mode',
                'vod_path',
                'vod_duration_seconds',
                'vod_metadata',
                'protocol',
                'srt_enabled',
                'encryption_settings',
                'proxy_settings',
                'proxy_status',
                'proxy_port',
                'proxy_error',
                'max_connections',
                'current_connections',
                'fallback_url_1',
                'fallback_url_2',
                'fallback_url_3',
                'auto_fallback',
                'buffer_size_mb',
                'segment_duration_seconds',
                'container_format',
                'last_checked',
                'health_check_interval',
                'health_metrics'
            ]);
        });
    }
};
