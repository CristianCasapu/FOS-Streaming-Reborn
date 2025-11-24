<?php
/**
 * Transcode Profiles Seeder
 *
 * Creates default transcode profiles for testing live streams with FFprobe.
 * Most profiles use copy mode (pass-through) for optimal performance.
 *
 * Usage: php database/seeders/TranscodeProfilesSeeder.php
 */

require_once __DIR__ . '/../../config.php';

class TranscodeProfilesSeeder
{
    public function run()
    {
        echo "🌱 Seeding transcode profiles...\n\n";

        // Check if profiles already exist
        $existing = Transcode::count();
        if ($existing > 0) {
            echo "⚠️  Found {$existing} existing profiles. Clearing...\n";
            Transcode::query()->delete();
        }

        $profiles = [
            // ========== COPY PROFILES (Pass-through) - Most Common ==========
            [
                'name' => 'Default 1 - H264 Copy',
                'description' => 'Default pass-through for H264 streams. No transcoding, minimal CPU usage.',
                'video_codec' => 'copy',
                'video_profile' => null,
                'video_bitrate' => null,
                'video_width' => null,
                'video_height' => null,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 10000000 -probesize 10000000',
                'priority' => 100,
                'is_active' => 1
            ],
            [
                'name' => 'H264 Copy - Fast Buffer',
                'description' => 'H264 copy with fast buffering for low-latency streams.',
                'video_codec' => 'copy',
                'video_profile' => null,
                'video_bitrate' => null,
                'video_width' => null,
                'video_height' => null,
                'video_fps' => null,
                'video_preset' => 'ultrafast',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 5000000 -probesize 5000000 -fflags nobuffer',
                'priority' => 98,
                'is_active' => 1
            ],
            [
                'name' => 'H264 Copy - Large Buffer',
                'description' => 'H264 copy with large buffer for unstable streams.',
                'video_codec' => 'copy',
                'video_profile' => null,
                'video_bitrate' => null,
                'video_width' => null,
                'video_height' => null,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 20000000 -probesize 20000000 -max_muxing_queue_size 1024',
                'priority' => 96,
                'is_active' => 1
            ],
            [
                'name' => 'H265 Copy',
                'description' => 'Pass-through for H265/HEVC streams. No transcoding.',
                'video_codec' => 'copy',
                'video_profile' => null,
                'video_bitrate' => null,
                'video_width' => null,
                'video_height' => null,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 10000000 -probesize 10000000 -tag:v hvc1',
                'priority' => 95,
                'is_active' => 1
            ],
            [
                'name' => 'H265 Copy - HLS Optimized',
                'description' => 'H265 copy optimized for HLS delivery.',
                'video_codec' => 'copy',
                'video_profile' => null,
                'video_bitrate' => null,
                'video_width' => null,
                'video_height' => null,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 10000000 -probesize 10000000 -tag:v hvc1 -movflags +faststart',
                'priority' => 93,
                'is_active' => 1
            ],

            // ========== SD PROFILES (480p) ==========
            [
                'name' => 'SD 480p - H264 Copy',
                'description' => 'Copy mode for SD 480p H264 streams.',
                'video_codec' => 'copy',
                'video_profile' => 'main',
                'video_bitrate' => null,
                'video_width' => 854,
                'video_height' => 480,
                'video_fps' => null,
                'video_preset' => 'fast',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 5000000 -probesize 5000000',
                'priority' => 90,
                'is_active' => 1
            ],
            [
                'name' => 'SD 480p - H264 Encode',
                'description' => 'Transcode to SD 480p H264.',
                'video_codec' => 'h264',
                'video_profile' => 'main',
                'video_bitrate' => 800000,
                'video_width' => 854,
                'video_height' => 480,
                'video_fps' => 25,
                'video_preset' => 'fast',
                'audio_codec' => 'aac',
                'audio_bitrate' => 128000,
                'audio_sample_rate' => 44100,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 5000000 -probesize 5000000 -bufsize 1600k',
                'priority' => 85,
                'is_active' => 1
            ],
            [
                'name' => 'SD 480p - H265 Encode',
                'description' => 'Transcode to SD 480p H265 for better compression.',
                'video_codec' => 'h265',
                'video_profile' => 'main',
                'video_bitrate' => 600000,
                'video_width' => 854,
                'video_height' => 480,
                'video_fps' => 25,
                'video_preset' => 'medium',
                'audio_codec' => 'aac',
                'audio_bitrate' => 128000,
                'audio_sample_rate' => 44100,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 5000000 -probesize 5000000 -tag:v hvc1',
                'priority' => 83,
                'is_active' => 1
            ],

            // ========== HD 720p PROFILES ==========
            [
                'name' => 'HD 720p - H264 Copy',
                'description' => 'Copy mode for HD 720p H264 streams.',
                'video_codec' => 'copy',
                'video_profile' => 'high',
                'video_bitrate' => null,
                'video_width' => 1280,
                'video_height' => 720,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 8000000 -probesize 8000000',
                'priority' => 80,
                'is_active' => 1
            ],
            [
                'name' => 'HD 720p - H264 Encode',
                'description' => 'Transcode to HD 720p H264.',
                'video_codec' => 'h264',
                'video_profile' => 'high',
                'video_bitrate' => 2500000,
                'video_width' => 1280,
                'video_height' => 720,
                'video_fps' => 30,
                'video_preset' => 'medium',
                'audio_codec' => 'aac',
                'audio_bitrate' => 192000,
                'audio_sample_rate' => 48000,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 8000000 -probesize 8000000 -bufsize 5000k',
                'priority' => 75,
                'is_active' => 1
            ],
            [
                'name' => 'HD 720p - H265 Copy',
                'description' => 'Copy mode for HD 720p H265 streams.',
                'video_codec' => 'copy',
                'video_profile' => 'main',
                'video_bitrate' => null,
                'video_width' => 1280,
                'video_height' => 720,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 8000000 -probesize 8000000 -tag:v hvc1',
                'priority' => 78,
                'is_active' => 1
            ],
            [
                'name' => 'HD 720p - H265 Encode',
                'description' => 'Transcode to HD 720p H265.',
                'video_codec' => 'h265',
                'video_profile' => 'main',
                'video_bitrate' => 1800000,
                'video_width' => 1280,
                'video_height' => 720,
                'video_fps' => 30,
                'video_preset' => 'medium',
                'audio_codec' => 'aac',
                'audio_bitrate' => 192000,
                'audio_sample_rate' => 48000,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 8000000 -probesize 8000000 -tag:v hvc1',
                'priority' => 73,
                'is_active' => 1
            ],

            // ========== FHD 1080p PROFILES ==========
            [
                'name' => 'FHD 1080p - H264 Copy',
                'description' => 'Copy mode for Full HD 1080p H264 streams.',
                'video_codec' => 'copy',
                'video_profile' => 'high',
                'video_bitrate' => null,
                'video_width' => 1920,
                'video_height' => 1080,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 10000000 -probesize 10000000',
                'priority' => 70,
                'is_active' => 1
            ],
            [
                'name' => 'FHD 1080p - H264 Encode',
                'description' => 'Transcode to Full HD 1080p H264.',
                'video_codec' => 'h264',
                'video_profile' => 'high',
                'video_bitrate' => 4500000,
                'video_width' => 1920,
                'video_height' => 1080,
                'video_fps' => 30,
                'video_preset' => 'medium',
                'audio_codec' => 'aac',
                'audio_bitrate' => 256000,
                'audio_sample_rate' => 48000,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 10000000 -probesize 10000000 -bufsize 9000k',
                'priority' => 65,
                'is_active' => 1
            ],
            [
                'name' => 'FHD 1080p - H265 Copy',
                'description' => 'Copy mode for Full HD 1080p H265 streams.',
                'video_codec' => 'copy',
                'video_profile' => 'main',
                'video_bitrate' => null,
                'video_width' => 1920,
                'video_height' => 1080,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 10000000 -probesize 10000000 -tag:v hvc1',
                'priority' => 68,
                'is_active' => 1
            ],
            [
                'name' => 'FHD 1080p - H265 Encode',
                'description' => 'Transcode to Full HD 1080p H265.',
                'video_codec' => 'h265',
                'video_profile' => 'main',
                'video_bitrate' => 3200000,
                'video_width' => 1920,
                'video_height' => 1080,
                'video_fps' => 30,
                'video_preset' => 'medium',
                'audio_codec' => 'aac',
                'audio_bitrate' => 256000,
                'audio_sample_rate' => 48000,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 10000000 -probesize 10000000 -tag:v hvc1',
                'priority' => 63,
                'is_active' => 1
            ],
            [
                'name' => 'FHD 1080p - H264 60fps Copy',
                'description' => 'Copy mode for 1080p 60fps H264 streams.',
                'video_codec' => 'copy',
                'video_profile' => 'high',
                'video_bitrate' => null,
                'video_width' => 1920,
                'video_height' => 1080,
                'video_fps' => 60,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 10000000 -probesize 10000000 -r 60',
                'priority' => 67,
                'is_active' => 1
            ],

            // ========== 4K UHD PROFILES ==========
            [
                'name' => '4K UHD - H264 Copy',
                'description' => 'Copy mode for 4K 2160p H264 streams.',
                'video_codec' => 'copy',
                'video_profile' => 'high',
                'video_bitrate' => null,
                'video_width' => 3840,
                'video_height' => 2160,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 15000000 -probesize 15000000',
                'priority' => 60,
                'is_active' => 1
            ],
            [
                'name' => '4K UHD - H265 Copy',
                'description' => 'Copy mode for 4K 2160p H265 streams.',
                'video_codec' => 'copy',
                'video_profile' => 'main',
                'video_bitrate' => null,
                'video_width' => 3840,
                'video_height' => 2160,
                'video_fps' => null,
                'video_preset' => 'medium',
                'audio_codec' => 'copy',
                'audio_bitrate' => null,
                'audio_sample_rate' => null,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 15000000 -probesize 15000000 -tag:v hvc1',
                'priority' => 58,
                'is_active' => 1
            ],
            [
                'name' => '4K UHD - H264 Encode',
                'description' => 'Transcode to 4K 2160p H264. High CPU usage.',
                'video_codec' => 'h264',
                'video_profile' => 'high',
                'video_bitrate' => 15000000,
                'video_width' => 3840,
                'video_height' => 2160,
                'video_fps' => 30,
                'video_preset' => 'slow',
                'audio_codec' => 'aac',
                'audio_bitrate' => 256000,
                'audio_sample_rate' => 48000,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 15000000 -probesize 15000000 -bufsize 30000k',
                'priority' => 55,
                'is_active' => 1
            ],
            [
                'name' => '4K UHD - H265 Encode',
                'description' => 'Transcode to 4K 2160p H265. Better compression.',
                'video_codec' => 'h265',
                'video_profile' => 'main',
                'video_bitrate' => 10000000,
                'video_width' => 3840,
                'video_height' => 2160,
                'video_fps' => 30,
                'video_preset' => 'slow',
                'audio_codec' => 'aac',
                'audio_bitrate' => 256000,
                'audio_sample_rate' => 48000,
                'audio_channels' => 2,
                'container_format' => 'ts',
                'hw_accel_enabled' => 0,
                'hw_accel_type' => null,
                'custom_ffmpeg_params' => '-analyzeduration 15000000 -probesize 15000000 -tag:v hvc1',
                'priority' => 53,
                'is_active' => 1
            ]
        ];

        $created = 0;
        foreach ($profiles as $profileData) {
            try {
                $profile = Transcode::create($profileData);
                $created++;
                echo "✅ [{$profile->id}] {$profile->name}\n";
            } catch (Exception $e) {
                echo "❌ Failed to create {$profileData['name']}: {$e->getMessage()}\n";
            }
        }

        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✨ Created {$created} transcode profiles\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    }
}

// Run seeder if executed directly
if (php_sapi_name() === 'cli') {
    $seeder = new TranscodeProfilesSeeder();
    $seeder->run();
}
