<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Migrate existing database to use streams
     *
     * This migration handles the transition for existing databases
     */
    public function up(): void
    {
        $schema = Capsule::schema();
        $db = Capsule::connection();

        echo "→ Migrating bouquets to use streams directly...\n";

        // Step 1: Add stream_ids column if it doesn't exist
        if ($schema->hasTable('bouquets') && !$schema->hasColumn('bouquets', 'stream_ids')) {
            echo "  → Adding stream_ids column to bouquets table...\n";
            $schema->table('bouquets', function (Blueprint $table) {
                $table->json('stream_ids')->nullable()->after('description')
                    ->comment('JSON array of stream IDs in this bouquet');
            });
            echo "  ✓ stream_ids column added\n";
        }

        // Step 2: Migrate data from bouquet_channel to bouquets.stream_ids
        if ($schema->hasTable('bouquet_channel') && $schema->hasTable('channels')) {
            echo "  → Migrating data from bouquet_channel to stream_ids...\n";

            $bouquets = $db->table('bouquets')->get();
            $migratedCount = 0;

            foreach ($bouquets as $bouquet) {
                // Get all channels for this bouquet with their stream_ids
                $streamIds = $db->table('bouquet_channel')
                    ->join('channels', 'bouquet_channel.channel_id', '=', 'channels.id')
                    ->where('bouquet_channel.bouquet_id', $bouquet->id)
                    ->orderBy('bouquet_channel.sort_order')
                    ->pluck('channels.stream_id')
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($streamIds)) {
                    $db->table('bouquets')
                        ->where('id', $bouquet->id)
                        ->update(['stream_ids' => json_encode($streamIds)]);
                    $migratedCount++;
                    echo "    ✓ Migrated {$bouquet->name}: " . count($streamIds) . " stream(s)\n";
                }
            }

            echo "  ✓ Migrated $migratedCount bouquet(s)\n";
        }

        // Step 3: Drop channels column if it exists
        if ($schema->hasColumn('bouquets', 'channels')) {
            echo "  → Dropping old channels column...\n";
            $schema->table('bouquets', function (Blueprint $table) {
                $table->dropColumn('channels');
            });
            echo "  ✓ channels column dropped\n";
        }

        // Step 4: Drop bouquet_channel table
        if ($schema->hasTable('bouquet_channel')) {
            echo "  → Dropping bouquet_channel pivot table...\n";
            $schema->dropIfExists('bouquet_channel');
            echo "  ✓ bouquet_channel table dropped\n";
        }

        // Step 5: Drop channels table
        if ($schema->hasTable('channels')) {
            echo "  → Dropping channels table...\n";
            $schema->dropIfExists('channels');
            echo "  ✓ channels table dropped\n";
        }

        echo "✓ Migration to streams completed successfully\n";
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        echo "→ Reversing migration (recreating channel tables)...\n";

        // Recreate channels table
        if (!$schema->hasTable('channels')) {
            $schema->create('channels', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('stream_id');
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->unsignedInteger('category_id')->nullable();
                $table->string('logo_url', 512)->nullable();
                $table->string('epg_id', 100)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('stream_id')->references('id')->on('streams')->onDelete('cascade');
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
                $table->index('stream_id');
                $table->index('category_id');
                $table->index('is_active');
            });
            echo "  ✓ channels table recreated\n";
        }

        // Recreate bouquet_channel table
        if (!$schema->hasTable('bouquet_channel')) {
            $schema->create('bouquet_channel', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('bouquet_id');
                $table->unsignedInteger('channel_id');
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('bouquet_id')->references('id')->on('bouquets')->onDelete('cascade');
                $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
                $table->unique(['bouquet_id', 'channel_id'], 'unique_bouquet_channel');
                $table->index('bouquet_id');
                $table->index('channel_id');
                $table->index('sort_order');
            });
            echo "  ✓ bouquet_channel table recreated\n";
        }

        // Add back channels column
        if ($schema->hasTable('bouquets') && !$schema->hasColumn('bouquets', 'channels')) {
            $schema->table('bouquets', function (Blueprint $table) {
                $table->json('channels')->nullable()->comment('JSON array of channel IDs');
            });
            echo "  ✓ channels column added back\n";
        }

        // Drop stream_ids column
        if ($schema->hasColumn('bouquets', 'stream_ids')) {
            $schema->table('bouquets', function (Blueprint $table) {
                $table->dropColumn('stream_ids');
            });
            echo "  ✓ stream_ids column dropped\n";
        }

        echo "✓ Rollback completed\n";
    }
};
