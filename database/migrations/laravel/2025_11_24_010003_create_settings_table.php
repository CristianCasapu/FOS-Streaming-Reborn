<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key', 100)->unique();
                $table->text('value')->nullable();
                $table->string('type', 20)->default('string')
                    ->comment('string, int, bool, json, array');
                $table->string('group', 50)->default('general')
                    ->comment('general, streaming, security, email, etc.');
                $table->string('label', 255)->nullable()->comment('Display label');
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false)
                    ->comment('Can be accessed without authentication');
                $table->timestamps();

                $table->index('key');
                $table->index('group');
                $table->index('is_public');
            });

            Capsule::statement("ALTER TABLE settings COMMENT = 'Application settings/configuration'");
            echo "✓ Settings table created\n";
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('settings');
    }
};
