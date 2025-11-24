<?php
class Transcode extends FosStreaming {

    protected $fillable = [
        'name',
        'description',
        'video_codec',
        'video_profile',
        'video_bitrate',
        'video_width',
        'video_height',
        'video_fps',
        'video_preset',
        'audio_codec',
        'audio_bitrate',
        'audio_sample_rate',
        'audio_channels',
        'container_format',
        'hw_accel_enabled',
        'hw_accel_type',
        'custom_ffmpeg_params',
        'priority',
        'is_active'
    ];

    protected $casts = [
        'hw_accel_enabled' => 'boolean',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'video_bitrate' => 'integer',
        'video_width' => 'integer',
        'video_height' => 'integer',
        'video_fps' => 'integer',
        'audio_bitrate' => 'integer',
        'audio_sample_rate' => 'integer',
        'audio_channels' => 'integer'
    ];
}