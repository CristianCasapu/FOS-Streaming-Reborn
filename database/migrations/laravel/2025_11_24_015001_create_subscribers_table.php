<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create subscribers table
     *
     * Subscribers are customers who can have multiple subscriptions
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        // If 'users' table exists, rename it to 'subscribers'
        if ($schema->hasTable('users') && !$schema->hasTable('subscribers')) {
            $schema->rename('users', 'subscribers');
        }

        // Create subscribers table if it doesn't exist
        if (!$schema->hasTable('subscribers')) {
            $schema->create('subscribers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 255)->unique();
                $table->string('password', 255);
                $table->string('email', 255)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('country', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->index('email');
                $table->index('enabled');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('subscribers');
    }
};
