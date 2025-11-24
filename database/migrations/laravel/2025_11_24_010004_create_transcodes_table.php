<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('transcodes')) {
            $schema->create('transcodes', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->text('description')->nullable();

                // Video settings
                $table->string('video_codec', 50)->default('h264')->comment('h264, h265, vp9');
                $table->string('video_profile', 50)->nullable()->comment('high, main, baseline');
                $table->unsignedInteger('video_bitrate')->nullable()->comment('Bitrate in kbps');
                $table->unsignedInteger('video_width')->nullable();
                $table->unsignedInteger('video_height')->nullable();
                $table->unsignedInteger('video_fps')->nullable();
                $table->string('video_preset', 50)->default('medium')
                    ->comment('ultrafast, superfast, veryfast, faster, fast, medium, slow, slower, veryslow');

                // Audio settings
                $table->string('audio_codec', 50)->default('aac')->comment('aac, mp3, opus');
                $table->unsignedInteger('audio_bitrate')->nullable()->comment('Bitrate in kbps');
                $table->unsignedInteger('audio_sample_rate')->nullable()->comment('44100, 48000');
                $table->unsignedTinyInteger('audio_channels')->default(2)->comment('1=mono, 2=stereo, 6=5.1');

                // Container
                $table->string('container_format', 20)->default('ts')->comment('ts, mp4, mkv, flv');

                // Hardware acceleration
                $table->boolean('hw_accel_enabled')->default(false);
                $table->string('hw_accel_type', 50)->nullable()->comment('nvenc, qsv, vaapi');

                // Advanced
                $table->text('custom_ffmpeg_params')->nullable()->comment('Additional FFmpeg parameters');
                $table->integer('priority')->default(0)->comment('Transcode priority');

                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
                $table->index('priority');
            });

            Capsule::statement("ALTER TABLE transcodes COMMENT = 'Transcode profiles for streams'");
            echo "✓ Transcodes table created\n";
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('transcodes');
    }
};
