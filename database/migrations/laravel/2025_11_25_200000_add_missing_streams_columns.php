<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Add missing columns to streams table
     *
     * Adds columns that were in SQL migrations but not in the original PHP migration:
     * - last_ffmpeg_command: For debugging FFmpeg commands
     * - xui_id: External XUI provider ID for M3U import
     * - timeshift: Timeshift/catchup capability in days
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('streams')) {
            $schema->table('streams', function (Blueprint $table) use ($schema) {
                // Add last_ffmpeg_command for debugging
                if (!$schema->hasColumn('streams', 'last_ffmpeg_command')) {
                    $table->text('last_ffmpeg_command')->nullable()
                        ->after('ffprobe_raw_json')
                        ->comment('Last FFmpeg command used to start this stream');
                }

                // Add xui_id for external provider ID
                if (!$schema->hasColumn('streams', 'xui_id')) {
                    $table->string('xui_id', 100)->nullable()
                        ->after('tvid')
                        ->comment('External XUI provider ID');
                    $table->index('xui_id', 'idx_streams_xui_id');
                }

                // Add timeshift for catchup capability
                if (!$schema->hasColumn('streams', 'timeshift')) {
                    $table->unsignedInteger('timeshift')->nullable()
                        ->after('xui_id')
                        ->comment('Timeshift/catchup capability in days');
                }
            });

            echo "✓ Added missing columns to streams table\n";
        } else {
            echo "⊘ Streams table does not exist, skipping\n";
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('streams')) {
            $schema->table('streams', function (Blueprint $table) use ($schema) {
                if ($schema->hasColumn('streams', 'last_ffmpeg_command')) {
                    $table->dropColumn('last_ffmpeg_command');
                }
                if ($schema->hasColumn('streams', 'xui_id')) {
                    $table->dropIndex('idx_streams_xui_id');
                    $table->dropColumn('xui_id');
                }
                if ($schema->hasColumn('streams', 'timeshift')) {
                    $table->dropColumn('timeshift');
                }
            });
        }
    }
};
