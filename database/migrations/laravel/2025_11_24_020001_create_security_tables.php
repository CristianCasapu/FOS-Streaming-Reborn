<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create security-related tables
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        // Banned IPs
        if (!$schema->hasTable('banned_ips')) {
            $schema->create('banned_ips', function (Blueprint $table) {
                $table->increments('id');
                $table->string('ip_address', 45)->unique();
                $table->text('reason')->nullable();
                $table->timestamp('banned_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->unsignedInteger('banned_by')->nullable();
                $table->index('ip_address');
                $table->index('expires_at');
            });
            echo "✓ banned_ips table created\n";
        }

        // Blocked IPs (temporary blocks, auto-expires)
        if (!$schema->hasTable('blocked_ips')) {
            $schema->create('blocked_ips', function (Blueprint $table) {
                $table->increments('id');
                $table->string('ip_address', 45);
                $table->string('reason', 255)->nullable();
                $table->unsignedInteger('violation_count')->default(1);
                $table->timestamp('first_violation')->useCurrent();
                $table->timestamp('last_violation')->useCurrent();
                $table->timestamp('blocked_until')->nullable();
                $table->index('ip_address');
                $table->index('blocked_until');
            });
            echo "✓ blocked_ips table created\n";
        }

        // Blocked User Agents
        if (!$schema->hasTable('blocked_user_agents')) {
            $schema->create('blocked_user_agents', function (Blueprint $table) {
                $table->increments('id');
                $table->string('pattern', 255)->comment('Regex pattern or exact match');
                $table->string('type', 20)->default('exact')->comment('exact, regex, contains');
                $table->text('reason')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('is_active');
            });
            echo "✓ blocked_user_agents table created\n";
        }

        // Failed Login Attempts
        if (!$schema->hasTable('failed_login_attempts')) {
            $schema->create('failed_login_attempts', function (Blueprint $table) {
                $table->id();
                $table->string('username', 255)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('ip_address', 45);
                $table->string('user_agent', 500)->nullable();
                $table->string('type', 20)->default('admin')->comment('admin, subscriber, reseller');
                $table->timestamp('attempted_at')->useCurrent();
                $table->index('ip_address');
                $table->index('attempted_at');
                $table->index(['ip_address', 'attempted_at']);
            });
            echo "✓ failed_login_attempts table created\n";
        }

        // Security Events
        if (!$schema->hasTable('security_events')) {
            $schema->create('security_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_type', 100)->comment('brute_force, port_scan, sql_injection, etc.');
                $table->string('severity', 20)->default('medium')->comment('low, medium, high, critical');
                $table->string('ip_address', 45);
                $table->string('user_agent', 500)->nullable();
                $table->string('url', 500)->nullable();
                $table->text('description');
                $table->json('meta_data')->nullable()->comment('Additional event details');
                $table->boolean('is_blocked')->default(false);
                $table->timestamp('created_at')->useCurrent();
                $table->index('event_type');
                $table->index('severity');
                $table->index('ip_address');
                $table->index('created_at');
                $table->index(['ip_address', 'event_type']);
            });
            echo "✓ security_events table created\n";
        }

        // UFW Rules
        if (!$schema->hasTable('ufw_rules')) {
            $schema->create('ufw_rules', function (Blueprint $table) {
                $table->increments('id');
                $table->enum('action', ['allow', 'deny', 'reject', 'limit'])->default('allow');
                $table->string('from_ip', 45)->nullable()->comment('Source IP or subnet');
                $table->string('to_port', 20)->nullable()->comment('Port or port range');
                $table->string('protocol', 10)->nullable()->comment('tcp, udp, any');
                $table->text('comment')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('is_active');
                $table->index('action');
            });
            echo "✓ ufw_rules table created\n";
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('ufw_rules');
        Capsule::schema()->dropIfExists('security_events');
        Capsule::schema()->dropIfExists('failed_login_attempts');
        Capsule::schema()->dropIfExists('blocked_user_agents');
        Capsule::schema()->dropIfExists('blocked_ips');
        Capsule::schema()->dropIfExists('banned_ips');
    }
};
