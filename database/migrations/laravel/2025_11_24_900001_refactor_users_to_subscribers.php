<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Refactor users table to subscribers table
     * Remove fields that belong in subscriptions: isp, package, expiration_date, max_connections
     * Remove is_reseller field (subscribers cannot be resellers)
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        // First, check if we need to rename the table
        if ($schema->hasTable('users') && !$schema->hasTable('subscribers')) {
            $schema->rename('users', 'subscribers');
        }

        // Remove columns in separate statements to avoid conflicts
        if ($schema->hasColumn('subscribers', 'isp')) {
            $schema->table('subscribers', function (Blueprint $table) {
                $table->dropColumn('isp');
            });
        }

        if ($schema->hasColumn('subscribers', 'package')) {
            $schema->table('subscribers', function (Blueprint $table) {
                $table->dropColumn('package');
            });
        }

        if ($schema->hasColumn('subscribers', 'expiration_date')) {
            $schema->table('subscribers', function (Blueprint $table) {
                $table->dropColumn('expiration_date');
            });
        }

        if ($schema->hasColumn('subscribers', 'max_connections')) {
            $schema->table('subscribers', function (Blueprint $table) {
                $table->dropColumn('max_connections');
            });
        }

        if ($schema->hasColumn('subscribers', 'is_reseller')) {
            $schema->table('subscribers', function (Blueprint $table) {
                $table->dropColumn('is_reseller');
            });
        }

        // Update foreign keys in related tables
        $this->updateForeignKeys();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        // Add back the removed columns first
        $schema->table('subscribers', function (Blueprint $table) {
            $table->string('isp', 255)->nullable();
            $table->string('package', 100)->nullable();
            $table->timestamp('expiration_date')->nullable();
            $table->integer('max_connections')->default(5);
            $table->boolean('is_reseller')->default(false);
        });

        // Rename back to users
        if ($schema->hasTable('subscribers') && !$schema->hasTable('users')) {
            $schema->rename('subscribers', 'users');
        }
    }

    /**
     * Update foreign keys in related tables
     */
    private function updateForeignKeys(): void
    {
        $schema = Capsule::schema();

        // Update subscriptions table foreign key
        if ($schema->hasTable('subscriptions')) {
            // Temporarily disable foreign key checks
            Capsule::statement('SET FOREIGN_KEY_CHECKS=0');

            // Drop old foreign key if exists
            try {
                $schema->table('subscriptions', function (Blueprint $table) {
                    $table->dropForeign(['subscriber_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, ignore
            }

            // Recreate foreign key
            $schema->table('subscriptions', function (Blueprint $table) {
                $table->foreign('subscriber_id')
                    ->references('id')
                    ->on('subscribers')
                    ->onDelete('cascade');
            });

            // Re-enable foreign key checks
            Capsule::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Update trials table foreign key
        if ($schema->hasTable('trials')) {
            Capsule::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                $schema->table('trials', function (Blueprint $table) {
                    $table->dropForeign(['subscriber_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, ignore
            }

            $schema->table('trials', function (Blueprint $table) {
                $table->foreign('subscriber_id')
                    ->references('id')
                    ->on('subscribers')
                    ->onDelete('cascade');
            });

            Capsule::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Update activity table foreign key (user_id -> subscriber_id reference)
        if ($schema->hasTable('activity')) {
            // Only update if the table has user_id column (old structure)
            // Fresh deployments will have subscriber_id already
            if ($schema->hasColumn('activity', 'user_id')) {
                Capsule::statement('SET FOREIGN_KEY_CHECKS=0');

                // The activity table uses 'user_id' but it should reference subscribers table now
                // We don't rename the column to maintain compatibility
                try {
                    $schema->table('activity', function (Blueprint $table) {
                        $table->dropForeign(['user_id']);
                    });
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }

                $schema->table('activity', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('subscribers')
                        ->onDelete('cascade');
                });

                Capsule::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
};
