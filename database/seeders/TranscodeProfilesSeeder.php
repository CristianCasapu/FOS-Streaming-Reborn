<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Transcode Profiles Seeder
 *
 * Creates default transcode profiles for testing live streams with FFprobe.
 * Most profiles use copy mode (pass-through) for optimal performance.
 */
class TranscodeProfilesSeeder extends Seeder
{
    public $command;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->command) {
            $this->command->info('Seeding transcode profiles...');
        }

        $timestamp = date('Y-m-d H:i:s');

        // Check if profiles already exist
        $existing = Capsule::table('transcodes')->count();
        if ($existing > 0) {
            if ($this->command) {
                $this->command->warn("Found {$existing} existing profiles. Clearing...");
            }
            Capsule::table('transcodes')->truncate();
        }

        $profiles = $this->getProfiles($timestamp);

        $created = 0;
        foreach ($profiles as $profileData) {
            try {
                Capsule::table('transcodes')->insert($profileData);
                $created++;
                if ($this->command) {
                    $this->command->line("  [{$created}] {$profileData['name']}");
                }
            } catch (\Exception $e) {
                if ($this->command) {
                    $this->command->error("  Failed to create {$profileData['name']}: {$e->getMessage()}");
                }
            }
        }

        if ($this->command) {
            $this->command->newLine();
            $this->command->info("Created {$created} transcode profiles!");
        }
    }

    /**
     * Get all transcode profile definitions
     */
    private function getProfiles(string $timestamp): array
    {
        return [
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
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
                'is_active' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];
    }
}
