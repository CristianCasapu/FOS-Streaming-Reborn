<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create subscriptions table
     *
     * Subscriptions are active service contracts for subscribers
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('subscriptions')) {
            $schema->create('subscriptions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('subscriber_id');
                $table->unsignedInteger('package_id');

                // Device & Security Information
                $table->string('device', 255)->nullable()->comment('Device name/type');
                $table->string('device_mac', 17)->nullable()->comment('MAC address');
                $table->string('device_fingerprint', 64)->nullable()->comment('Unique device fingerprint hash');
                $table->string('ip_address', 45)->nullable()->comment('IPv4 or IPv6 address');
                $table->string('last_ip_address', 45)->nullable()->comment('Most recent IP address used');
                $table->string('isp', 255)->nullable()->comment('Internet Service Provider');
                $table->text('user_agent')->nullable()->comment('Browser/App user agent');

                // Connection Tracking
                $table->timestamp('last_connected')->nullable()->comment('Last successful connection');
                $table->integer('connection_count')->default(0)->comment('Total connections made');
                $table->integer('max_concurrent_connections')->default(1)->comment('Max simultaneous connections allowed');
                $table->integer('current_connections')->default(0)->comment('Current number of active connections');

                // Subscription Status
                $table->timestamp('expire_date')->nullable()->comment('Subscription expiration date');
                $table->boolean('is_active')->default(true)->comment('Active/Inactive status');
                $table->boolean('auto_renew')->default(false)->comment('Auto-renewal enabled');

                // Additional Information
                $table->text('notes')->nullable()->comment('Admin notes');

                $table->timestamps();

                // Foreign Keys
                $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
                $table->foreign('package_id')->references('id')->on('packages')->onDelete('restrict');

                // Indexes
                $table->index('subscriber_id');
                $table->index('package_id');
                $table->index('is_active');
                $table->index('expire_date');
                $table->index('device_mac');
                $table->index('device_fingerprint');
                $table->index('ip_address');
                $table->index('last_ip_address');
                $table->index('last_connected');
                $table->index('max_concurrent_connections');
                $table->index('current_connections');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('subscriptions');
    }
};
