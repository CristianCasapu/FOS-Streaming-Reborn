<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations - Phase 4 V2Ray Integration
     */
    public function up(): void
    {
        // V2Ray user configurations
        \Illuminate\Database\Capsule\Manager::schema()->create('v2ray_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('subscriber_id');
            $table->string('vmess_id', 100)->unique()->comment('UUID or password for protocol');
            $table->unsignedInteger('port');
            $table->enum('protocol', ['vmess', 'vless', 'trojan', 'shadowsocks'])->default('vmess');
            $table->json('config')->comment('Full configuration JSON');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_connected')->nullable();
            $table->bigInteger('bytes_uploaded')->default(0);
            $table->bigInteger('bytes_downloaded')->default(0);
            $table->unsignedInteger('connections_count')->default(0);
            $table->timestamps();

            $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
            $table->index(['subscriber_id', 'protocol']);
            $table->index('is_active');
            $table->index('port');
        });

        // V2Ray servers (for load balancing)
        \Illuminate\Database\Capsule\Manager::schema()->create('v2ray_servers', function (Blueprint $table) {
            $table->id();
            $table->string('tag', 50)->unique()->comment('Server identifier');
            $table->string('name', 255);
            $table->string('address', 255)->comment('IP or domain');
            $table->unsignedInteger('port')->default(443);
            $table->enum('location', ['us', 'eu', 'asia', 'other'])->default('other');
            $table->string('country_code', 2)->nullable();
            $table->unsignedTinyInteger('weight')->default(100)->comment('Load balancing weight');
            $table->decimal('load', 5, 2)->default(0.00)->comment('Current load percentage');
            $table->unsignedInteger('max_connections')->default(1000);
            $table->unsignedInteger('current_connections')->default(0);
            $table->json('config')->nullable()->comment('Server-specific configuration');
            $table->string('health_check_url', 500)->nullable();
            $table->string('health_status', 20)->default('unknown')->comment('healthy, unhealthy, unknown');
            $table->timestamp('last_health_check')->nullable();
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('enabled');
            $table->index('health_status');
            $table->index(['load', 'enabled']);
        });

        // V2Ray activity logs
        \Illuminate\Database\Capsule\Manager::schema()->create('v2ray_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('subscriber_id')->nullable();
            $table->unsignedInteger('stream_id')->nullable();
            $table->string('action', 100)->comment('config_generated, connection_started, etc.');
            $table->string('protocol', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subscriber_id', 'created_at']);
            $table->index('action');
            $table->index('created_at');
        });

        // V2Ray traffic statistics (for billing and monitoring)
        \Illuminate\Database\Capsule\Manager::schema()->create('v2ray_traffic_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('subscriber_id');
            $table->unsignedBigInteger('v2ray_user_id');
            $table->date('date');
            $table->bigInteger('bytes_uploaded')->default(0);
            $table->bigInteger('bytes_downloaded')->default(0);
            $table->bigInteger('bytes_total')->default(0);
            $table->unsignedInteger('connections_count')->default(0);
            $table->unsignedInteger('active_minutes')->default(0)->comment('Total active time in minutes');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['subscriber_id', 'v2ray_user_id', 'date']);
            $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
            $table->foreign('v2ray_user_id')->references('id')->on('v2ray_users')->onDelete('cascade');
            $table->index(['subscriber_id', 'date']);
            $table->index('date');
        });

        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE v2ray_users COMMENT = "V2Ray user configurations and credentials"');
        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE v2ray_servers COMMENT = "V2Ray server nodes for load balancing"');
        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE v2ray_logs COMMENT = "V2Ray activity and event logs"');
        \Illuminate\Database\Capsule\Manager::statement('ALTER TABLE v2ray_traffic_stats COMMENT = "Daily traffic statistics per user"');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('v2ray_traffic_stats');
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('v2ray_logs');
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('v2ray_servers');
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('v2ray_users');
    }
};
