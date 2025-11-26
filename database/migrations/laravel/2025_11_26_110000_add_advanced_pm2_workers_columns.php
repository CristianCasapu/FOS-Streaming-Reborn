<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Migration: Add Advanced PM2 Workers Columns
 *
 * Adds all the columns needed for comprehensive PM2 worker management:
 * - Display name for UI
 * - Working directory and arguments
 * - Restart behavior configuration
 * - Logging configuration
 * - Watch settings
 * - Timeout settings
 * - Categorization and priority
 */
return new class
{
    public function up(): void
    {
        $schema = Capsule::schema();

        // Add missing columns to pm2_workers table
        if ($schema->hasTable('pm2_workers')) {
            $schema->table('pm2_workers', function ($table) {
                // Display info
                if (!Capsule::schema()->hasColumn('pm2_workers', 'display_name')) {
                    $table->string('display_name', 255)->nullable()->after('name')->comment('Human-friendly display name');
                }

                // Working directory and args
                if (!Capsule::schema()->hasColumn('pm2_workers', 'cwd')) {
                    $table->string('cwd', 512)->nullable()->after('script')->comment('Working directory');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'args')) {
                    $table->text('args')->nullable()->after('cwd')->comment('Command line arguments');
                }

                // Restart behavior
                if (!Capsule::schema()->hasColumn('pm2_workers', 'max_restarts')) {
                    $table->integer('max_restarts')->default(10)->after('max_memory_restart')->comment('Max restarts before giving up');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'min_uptime')) {
                    $table->string('min_uptime', 50)->default('10s')->after('max_restarts')->comment('Minimum uptime to be considered started');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'restart_delay')) {
                    $table->integer('restart_delay')->default(5000)->after('min_uptime')->comment('Delay between restarts in ms');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'autorestart')) {
                    $table->boolean('autorestart')->default(true)->after('restart_delay')->comment('Auto restart on crash');
                }

                // Logging
                if (!Capsule::schema()->hasColumn('pm2_workers', 'log_level')) {
                    $table->string('log_level', 20)->default('warn')->after('cron_restart')->comment('Log level (error, warn, info, debug)');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'error_file')) {
                    $table->string('error_file', 512)->nullable()->after('log_level')->comment('Path to error log file');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'out_file')) {
                    $table->string('out_file', 512)->nullable()->after('error_file')->comment('Path to output log file');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'log_file')) {
                    $table->string('log_file', 512)->nullable()->after('out_file')->comment('Path to combined log file');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'log_date_format')) {
                    $table->string('log_date_format', 100)->default('YYYY-MM-DD HH:mm:ss Z')->after('log_file')->comment('Log date format');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'merge_logs')) {
                    $table->boolean('merge_logs')->default(true)->after('log_date_format')->comment('Merge logs from all instances');
                }

                // Watch settings
                if (!Capsule::schema()->hasColumn('pm2_workers', 'ignore_watch')) {
                    $table->text('ignore_watch')->nullable()->after('watch')->comment('Paths to ignore when watching (JSON array)');
                }

                // Timeouts
                if (!Capsule::schema()->hasColumn('pm2_workers', 'kill_timeout')) {
                    $table->integer('kill_timeout')->default(5000)->after('ignore_watch')->comment('Timeout before SIGKILL in ms');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'listen_timeout')) {
                    $table->integer('listen_timeout')->default(3000)->after('kill_timeout')->comment('Timeout for listening in ms');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'shutdown_with_message')) {
                    $table->boolean('shutdown_with_message')->default(true)->after('listen_timeout')->comment('Send shutdown message to process');
                }

                // Status and priority
                if (!Capsule::schema()->hasColumn('pm2_workers', 'auto_start')) {
                    $table->boolean('auto_start')->default(true)->after('enabled')->comment('Start automatically on PM2 startup');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'priority')) {
                    $table->integer('priority')->default(50)->after('auto_start')->comment('Startup priority (lower = earlier)');
                }

                // Categorization
                if (!Capsule::schema()->hasColumn('pm2_workers', 'category')) {
                    $table->string('category', 100)->default('general')->after('priority')->comment('Worker category for grouping');
                }
                if (!Capsule::schema()->hasColumn('pm2_workers', 'tags')) {
                    $table->text('tags')->nullable()->after('category')->comment('Worker tags (JSON array)');
                }
            });

            echo "✓ Added advanced columns to pm2_workers table\n";
        }
    }

    public function down(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('pm2_workers')) {
            $schema->table('pm2_workers', function ($table) {
                $columnsToRemove = [
                    'display_name', 'cwd', 'args', 'max_restarts', 'min_uptime',
                    'restart_delay', 'autorestart', 'log_level', 'error_file',
                    'out_file', 'log_file', 'log_date_format', 'merge_logs',
                    'ignore_watch', 'kill_timeout', 'listen_timeout',
                    'shutdown_with_message', 'auto_start', 'priority',
                    'category', 'tags'
                ];

                foreach ($columnsToRemove as $column) {
                    if (Capsule::schema()->hasColumn('pm2_workers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
