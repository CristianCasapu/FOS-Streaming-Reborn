<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create trials table
     *
     * Trials are time-limited free access (one per subscriber)
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('trials')) {
            $schema->create('trials', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('subscriber_id')->unique()->comment('Only one trial per subscriber');
                $table->unsignedInteger('package_id');

                // Device & Security Information
                $table->string('device', 255)->nullable();
                $table->string('device_mac', 17)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('isp', 255)->nullable();
                $table->text('user_agent')->nullable();

                // Trial Duration
                $table->timestamp('started_at')->useCurrent();
                $table->integer('trial_duration_hours')->default(24)->comment('Trial duration in hours');
                $table->timestamp('expires_at')->nullable()->comment('Trial expiration time (calculated)');

                // Connection Tracking
                $table->timestamp('last_connected')->nullable();
                $table->integer('connection_count')->default(0);

                // Trial Status
                $table->boolean('is_active')->default(true);
                $table->boolean('converted_to_subscription')->default(false)->comment('Trial converted to paid subscription');

                // Additional Information
                $table->text('notes')->nullable();

                $table->timestamps();

                // Foreign Keys
                $table->foreign('subscriber_id')->references('id')->on('subscribers')->onDelete('cascade');
                $table->foreign('package_id')->references('id')->on('packages')->onDelete('restrict');

                // Indexes
                $table->index('package_id');
                $table->index('is_active');
                $table->index('expires_at');
                $table->index('device_mac');
                $table->index('ip_address');
                $table->index('started_at');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('trials');
    }
};
