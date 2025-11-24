<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create pm2_workers table
     *
     * Stores PM2 worker configurations that are auto-loaded in Settings
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('pm2_workers')) {
            $schema->create('pm2_workers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255)->unique()->comment('Worker name (unique identifier)');
                $table->string('script', 512)->comment('Path to worker script');
                $table->integer('instances')->default(1)->comment('Number of instances to run');
                $table->enum('exec_mode', ['fork', 'cluster'])->default('fork')->comment('Execution mode');
                $table->boolean('watch')->default(false)->comment('Watch for file changes');
                $table->string('max_memory_restart', 50)->nullable()->comment('Memory limit before restart (e.g., 500M)');
                $table->text('env_vars')->nullable()->comment('Environment variables as JSON');
                $table->string('cron_restart', 100)->nullable()->comment('Cron pattern for scheduled restarts');
                $table->boolean('enabled')->default(true)->comment('Worker enabled/disabled');
                $table->boolean('auto_restart')->default(true)->comment('Auto-restart on crash');
                $table->text('description')->nullable()->comment('Worker description');
                $table->timestamps();

                $table->index('enabled');
                $table->index('name');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('pm2_workers');
    }
};
