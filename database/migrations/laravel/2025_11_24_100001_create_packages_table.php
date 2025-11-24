<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create packages table
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('packages')) {
            $schema->create('packages', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255)->unique();
                $table->text('description')->nullable();
                $table->integer('max_concurrent_devices')->default(1)->comment('Maximum concurrent devices/connections allowed');
                $table->integer('bandwidth_limit_mbps')->nullable()->comment('Bandwidth limit in Mbps (null = unlimited)');
                $table->enum('video_quality', ['SD', 'HD', 'FHD', 'UHD'])->default('HD')->comment('Maximum video quality allowed');
                $table->boolean('allow_recording')->default(false)->comment('Allow stream recording/download');
                $table->boolean('allow_timeshifting')->default(false)->comment('Allow timeshift/pause live TV');
                $table->json('features')->nullable()->comment('Additional package features as JSON');
                $table->decimal('price', 10, 2)->nullable()->comment('Package price (optional)');
                $table->integer('duration_days')->nullable()->comment('Default subscription duration in days (optional)');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('packages');
    }
};
