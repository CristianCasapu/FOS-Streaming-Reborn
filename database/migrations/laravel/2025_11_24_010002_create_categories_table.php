<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('categories')) {
            $schema->create('categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('icon', 255)->nullable()->comment('Icon class or URL');
                $table->string('color', 20)->nullable()->comment('Hex color code');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
                $table->index('sort_order');
            });

            Capsule::statement("ALTER TABLE categories COMMENT = 'Stream categories (Sports, Movies, News, etc.)'");
            echo "✓ Categories table created\n";
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('categories');
    }
};
