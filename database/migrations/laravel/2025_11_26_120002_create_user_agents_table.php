<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create user_agents table
     *
     * Stores predefined user agents for FFmpeg/FFprobe stream connections
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        // Create user_agents table
        if (!$schema->hasTable('user_agents')) {
            $schema->create('user_agents', function (Blueprint $table) {
                $table->increments('id');
                $table->string('user_agent', 512);
                $table->string('name', 100)->nullable()->comment('Friendly name for the user agent');
                $table->string('version', 20)->nullable()->comment('Version number if applicable');
                $table->string('os', 50)->nullable()->comment('Operating system (iOS, Android, Windows, etc.)');
                $table->string('hardware_type', 50)->nullable()->comment('Hardware type (phone, mobile, tv, desktop, None)');
                $table->boolean('is_default')->default(false)->comment('Whether this is the default user agent');
                $table->boolean('enabled')->default(true)->comment('Whether this user agent is enabled for use');
                $table->timestamps();

                $table->unique(['user_agent'], 'idx_user_agent_unique');
                $table->index('enabled', 'idx_enabled');
                $table->index('is_default', 'idx_is_default');
                $table->index('os', 'idx_os');
                $table->index('hardware_type', 'idx_hardware_type');
            });

            Capsule::statement("ALTER TABLE user_agents COMMENT = 'Predefined user agents for stream connections'");
            echo "✓ Created user_agents table\n";
        }

        // Add user_agent_id to streams table
        if ($schema->hasTable('streams') && !$schema->hasColumn('streams', 'user_agent_id')) {
            $schema->table('streams', function (Blueprint $table) {
                $table->unsignedInteger('user_agent_id')->nullable()->after('trans_id');
                $table->foreign('user_agent_id', 'fk_streams_user_agent')
                    ->references('id')->on('user_agents')->onDelete('set null');
            });
            echo "✓ Added user_agent_id column to streams\n";
        }

        // Add default_user_agent_id to settings table
        if ($schema->hasTable('settings') && !$schema->hasColumn('settings', 'default_user_agent_id')) {
            $schema->table('settings', function (Blueprint $table) {
                $table->unsignedInteger('default_user_agent_id')->nullable();
                $table->foreign('default_user_agent_id', 'fk_settings_default_user_agent')
                    ->references('id')->on('user_agents')->onDelete('set null');
            });
            echo "✓ Added default_user_agent_id column to settings\n";
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        // Remove foreign key and column from settings
        if ($schema->hasTable('settings') && $schema->hasColumn('settings', 'default_user_agent_id')) {
            $schema->table('settings', function (Blueprint $table) {
                $table->dropForeign('fk_settings_default_user_agent');
                $table->dropColumn('default_user_agent_id');
            });
        }

        // Remove foreign key and column from streams
        if ($schema->hasTable('streams') && $schema->hasColumn('streams', 'user_agent_id')) {
            $schema->table('streams', function (Blueprint $table) {
                $table->dropForeign('fk_streams_user_agent');
                $table->dropColumn('user_agent_id');
            });
        }

        // Drop user_agents table
        $schema->dropIfExists('user_agents');
    }
};
