<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Add PID check columns to stream_health_logs
     *
     * Adds columns needed for PID monitoring by stream-monitor-worker:
     * - check_type: Type of health check (pid_check, url_check, ffprobe_check)
     * - pid: Process ID that was checked
     * - pid_exists: Whether PID existed at check time
     * - action_taken: Action taken (restart_queued, marked_as_error, none)
     * - url_checked: URL that was health checked
     * - http_status: HTTP response status code
     * - response_time: Response time in milliseconds
     * - error_details: Additional error details as JSON
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('stream_health_logs')) {
            $schema->table('stream_health_logs', function (Blueprint $table) use ($schema) {
                // Add check_type column
                if (!$schema->hasColumn('stream_health_logs', 'check_type')) {
                    $table->string('check_type', 30)->default('pid_check')
                        ->after('stream_id')
                        ->comment('Type of health check: pid_check, url_check, ffprobe_check');
                }

                // Add pid column
                if (!$schema->hasColumn('stream_health_logs', 'pid')) {
                    $table->unsignedInteger('pid')->nullable()
                        ->after('check_type')
                        ->comment('Process ID that was checked');
                }

                // Add pid_exists column
                if (!$schema->hasColumn('stream_health_logs', 'pid_exists')) {
                    $table->boolean('pid_exists')->default(false)
                        ->after('pid')
                        ->comment('Whether PID existed at check time');
                }

                // Add action_taken column
                if (!$schema->hasColumn('stream_health_logs', 'action_taken')) {
                    $table->string('action_taken', 50)->nullable()
                        ->after('pid_exists')
                        ->comment('Action taken: restart_queued, marked_as_error, none');
                }

                // Add url_checked column
                if (!$schema->hasColumn('stream_health_logs', 'url_checked')) {
                    $table->string('url_checked', 500)->nullable()
                        ->after('action_taken')
                        ->comment('URL that was health checked');
                }

                // Add http_status column
                if (!$schema->hasColumn('stream_health_logs', 'http_status')) {
                    $table->unsignedInteger('http_status')->nullable()
                        ->after('url_checked')
                        ->comment('HTTP response status code');
                }

                // Add response_time column (separate from response_time_ms for URL checks)
                if (!$schema->hasColumn('stream_health_logs', 'response_time')) {
                    $table->unsignedInteger('response_time')->nullable()
                        ->after('http_status')
                        ->comment('Response time in milliseconds');
                }

                // Add error_details column
                if (!$schema->hasColumn('stream_health_logs', 'error_details')) {
                    $table->longText('error_details')->nullable()
                        ->after('error_message')
                        ->comment('Additional error details as JSON');
                }
            });

            // Add indexes for efficient querying
            $this->addIndexIfNotExists('stream_health_logs', 'idx_health_check_type', 'check_type');
            $this->addIndexIfNotExists('stream_health_logs', 'idx_health_stream_status', ['stream_id', 'status']);

            echo "✓ Added PID check columns to stream_health_logs\n";
        } else {
            echo "⊘ stream_health_logs table does not exist, skipping\n";
        }
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string $indexName, string|array $columns): void
    {
        $indexes = Capsule::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        if (empty($indexes)) {
            $columns = is_array($columns) ? implode(', ', $columns) : $columns;
            Capsule::statement("CREATE INDEX {$indexName} ON {$table} ({$columns})");
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('stream_health_logs')) {
            $schema->table('stream_health_logs', function (Blueprint $table) use ($schema) {
                $columns = ['check_type', 'pid', 'pid_exists', 'action_taken', 'url_checked', 'http_status', 'response_time', 'error_details'];

                foreach ($columns as $column) {
                    if ($schema->hasColumn('stream_health_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });

            // Drop indexes
            try {
                Capsule::statement('DROP INDEX IF EXISTS idx_health_check_type ON stream_health_logs');
                Capsule::statement('DROP INDEX IF EXISTS idx_health_stream_status ON stream_health_logs');
            } catch (\Exception $e) {
                // Indexes may not exist
            }
        }
    }
};
