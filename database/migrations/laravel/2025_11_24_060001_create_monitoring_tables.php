<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create monitoring/logging tables
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        // Activity Log - Streaming Sessions Tracker
        // Tracks subscriber streaming sessions with start/end times
        if (!$schema->hasTable('activity')) {
            $schema->create('activity', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id')->comment('Reference to subscribers (users table)');
                $table->unsignedInteger('stream_id')->comment('Stream being watched');
                $table->dateTime('date_start')->default('0000-00-00 00:00:00')->comment('Session start time');
                $table->dateTime('date_end')->default('0000-00-00 00:00:00')->comment('Session end time');
                $table->string('user_ip', 45)->nullable()->comment('Subscriber IP address');
                $table->text('user_agent')->nullable()->comment('Browser/App user agent');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                // Indexes for performance
                $table->index('user_id');
                $table->index('stream_id');
                $table->index('date_start');
                $table->index('date_end');
                $table->index('user_ip');
            });
            echo "✓ activity table created (streaming sessions tracker)\n";
        }

        // Stream Health Logs
        if (!$schema->hasTable('stream_health_logs')) {
            $schema->create('stream_health_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('stream_id');
                $table->string('status', 20)->comment('healthy, unhealthy, degraded');
                $table->unsignedTinyInteger('health_score')->nullable()->comment('0-100');
                $table->unsignedInteger('response_time_ms')->nullable();
                $table->bigInteger('current_bitrate')->nullable();
                $table->unsignedInteger('current_connections')->default(0);
                $table->boolean('is_accessible')->default(true);
                $table->text('error_message')->nullable();
                $table->json('metrics')->nullable()->comment('Detailed metrics');
                $table->timestamp('checked_at')->useCurrent();
                $table->foreign('stream_id')->references('id')->on('streams')->onDelete('cascade');
                $table->index('stream_id');
                $table->index('status');
                $table->index('checked_at');
                $table->index(['stream_id', 'checked_at']);
            });
            echo "✓ stream_health_logs table created\n";
        }

        // Website Health Logs
        if (!$schema->hasTable('website_health_logs')) {
            $schema->create('website_health_logs', function (Blueprint $table) {
                $table->id();
                $table->string('endpoint', 255)->comment('URL being monitored');
                $table->string('status', 20)->comment('up, down, degraded');
                $table->unsignedInteger('response_code')->nullable();
                $table->unsignedInteger('response_time_ms')->nullable();
                $table->text('error_message')->nullable();
                $table->json('headers')->nullable();
                $table->timestamp('checked_at')->useCurrent();
                $table->index('endpoint');
                $table->index('status');
                $table->index('checked_at');
            });
            echo "✓ website_health_logs table created\n";
        }

        // System Command Logs
        if (!$schema->hasTable('system_command_logs')) {
            $schema->create('system_command_logs', function (Blueprint $table) {
                $table->id();
                $table->string('command', 255)->comment('Command that was executed');
                $table->text('full_command')->nullable()->comment('Full command with arguments');
                $table->unsignedInteger('executed_by')->nullable()->comment('Admin ID');
                $table->string('execution_type', 50)->default('manual')->comment('manual, scheduled, webhook');
                $table->text('output')->nullable();
                $table->text('error_output')->nullable();
                $table->integer('exit_code')->nullable();
                $table->unsignedInteger('execution_time_ms')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->index('command');
                $table->index('executed_by');
                $table->index('started_at');
            });
            echo "✓ system_command_logs table created\n";
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('system_command_logs');
        Capsule::schema()->dropIfExists('website_health_logs');
        Capsule::schema()->dropIfExists('stream_health_logs');
        Capsule::schema()->dropIfExists('activity');
    }
};
