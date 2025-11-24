<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create bouquets table
     *
     * Bouquets are groups of streams (e.g., Sports, Movies, News)
     * Directly references streams without the need for a channels table
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('bouquets')) {
            $schema->create('bouquets', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255)->unique();
                $table->text('description')->nullable();
                $table->json('stream_ids')->nullable()->comment('JSON array of stream IDs in this bouquet');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0)->comment('Display order');
                $table->timestamps();

                $table->index('is_active');
                $table->index('sort_order');
            });
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('bouquets');
    }
};
