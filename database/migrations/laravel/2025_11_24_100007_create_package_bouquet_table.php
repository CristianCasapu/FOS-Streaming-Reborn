<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create package_bouquet pivot table
     *
     * Many-to-many relationship: A package can have multiple bouquets,
     * and a bouquet can belong to multiple packages
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('package_bouquet')) {
            $schema->create('package_bouquet', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('package_id');
                $table->unsignedInteger('bouquet_id');
                $table->timestamp('created_at')->useCurrent();

                // Foreign Keys
                $table->foreign('package_id')->references('id')->on('packages')->onDelete('cascade');
                $table->foreign('bouquet_id')->references('id')->on('bouquets')->onDelete('cascade');

                // Unique constraint: package can have each bouquet only once
                $table->unique(['package_id', 'bouquet_id'], 'unique_package_bouquet');

                // Indexes
                $table->index('package_id');
                $table->index('bouquet_id');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('package_bouquet');
    }
};
