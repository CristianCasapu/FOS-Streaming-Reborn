<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create device locking/fingerprinting tables
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        // Device Fingerprints
        if (!$schema->hasTable('device_fingerprints')) {
            $schema->create('device_fingerprints', function (Blueprint $table) {
                $table->id();
                $table->string('fingerprint_hash', 64)->unique()->comment('SHA256 hash of device attributes');
                $table->json('device_data')->comment('Browser, OS, screen resolution, etc.');
                $table->string('device_type', 20)->nullable()->comment('web, mobile, tv, stb');
                $table->string('browser', 100)->nullable();
                $table->string('os', 100)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
                $table->index('fingerprint_hash');
                $table->index('device_type');
            });
            echo "✓ device_fingerprints table created\n";
        }

        // Device Sessions
        if (!$schema->hasTable('device_sessions')) {
            $schema->create('device_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('subscriber_id');
                $table->unsignedBigInteger('fingerprint_id')->nullable();
                $table->string('session_token', 100)->unique();
                $table->string('ip_address', 45);
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('last_activity')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
                $table->foreign('fingerprint_id')->references('id')->on('device_fingerprints')->onDelete('set null');
                $table->index('subscriber_id');
                $table->index('session_token');
                $table->index('is_active');
                $table->index(['subscriber_id', 'is_active']);
            });
            echo "✓ device_sessions table created\n";
        }

        // Device Bindings (max devices per subscriber)
        if (!$schema->hasTable('device_bindings')) {
            $schema->create('device_bindings', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('subscriber_id');
                $table->unsignedBigInteger('fingerprint_id');
                $table->string('device_name', 255)->nullable()->comment('User-defined device name');
                $table->boolean('is_primary')->default(false);
                $table->timestamp('bound_at')->useCurrent();
                $table->timestamp('last_used')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
                $table->foreign('fingerprint_id')->references('id')->on('device_fingerprints')->onDelete('cascade');
                $table->unique(['subscriber_id', 'fingerprint_id']);
                $table->index('subscriber_id');
                $table->index('is_active');
            });
            echo "✓ device_bindings table created\n";
        }

        // Device Violations
        if (!$schema->hasTable('device_violations')) {
            $schema->create('device_violations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('subscriber_id');
                $table->unsignedBigInteger('fingerprint_id')->nullable();
                $table->string('violation_type', 100)->comment('max_devices_exceeded, unbound_device, etc.');
                $table->text('description')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->json('meta_data')->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
                $table->index('subscriber_id');
                $table->index('violation_type');
                $table->index('occurred_at');
            });
            echo "✓ device_violations table created\n";
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('device_violations');
        Capsule::schema()->dropIfExists('device_bindings');
        Capsule::schema()->dropIfExists('device_sessions');
        Capsule::schema()->dropIfExists('device_fingerprints');
    }
};
