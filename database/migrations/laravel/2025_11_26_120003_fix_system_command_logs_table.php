<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Fix system_command_logs table
     *
     * Adds missing columns to match model expectations
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('system_command_logs')) {
            // Add missing columns
            if (!$schema->hasColumn('system_command_logs', 'admin_id')) {
                $schema->table('system_command_logs', function (Blueprint $table) {
                    $table->unsignedInteger('admin_id')->nullable()->after('id');
                    $table->index('admin_id', 'idx_admin_id');
                });
                echo "✓ Added admin_id column to system_command_logs\n";
            }

            if (!$schema->hasColumn('system_command_logs', 'description')) {
                $schema->table('system_command_logs', function (Blueprint $table) {
                    $table->string('description', 255)->nullable()->after('command');
                });
                echo "✓ Added description column to system_command_logs\n";
            }

            if (!$schema->hasColumn('system_command_logs', 'execution_time')) {
                $schema->table('system_command_logs', function (Blueprint $table) {
                    $table->float('execution_time')->nullable()->after('exit_code');
                });
                echo "✓ Added execution_time column to system_command_logs\n";
            }

            if (!$schema->hasColumn('system_command_logs', 'success')) {
                $schema->table('system_command_logs', function (Blueprint $table) {
                    $table->boolean('success')->default(false)->after('execution_time');
                });
                echo "✓ Added success column to system_command_logs\n";
            }

            if (!$schema->hasColumn('system_command_logs', 'ip_address')) {
                $schema->table('system_command_logs', function (Blueprint $table) {
                    $table->string('ip_address', 45)->nullable()->after('success');
                });
                echo "✓ Added ip_address column to system_command_logs\n";
            }

            if (!$schema->hasColumn('system_command_logs', 'user_agent')) {
                $schema->table('system_command_logs', function (Blueprint $table) {
                    $table->text('user_agent')->nullable()->after('ip_address');
                });
                echo "✓ Added user_agent column to system_command_logs\n";
            }

            if (!$schema->hasColumn('system_command_logs', 'created_at')) {
                $schema->table('system_command_logs', function (Blueprint $table) {
                    $table->timestamp('created_at')->nullable()->useCurrent()->after('user_agent');
                });
                echo "✓ Added created_at column to system_command_logs\n";
            }

            // Copy data from old columns to new columns
            Capsule::statement("
                UPDATE system_command_logs
                SET admin_id = executed_by
                WHERE admin_id IS NULL AND executed_by IS NOT NULL
            ");

            Capsule::statement("
                UPDATE system_command_logs
                SET execution_time = execution_time_ms / 1000
                WHERE execution_time IS NULL AND execution_time_ms IS NOT NULL
            ");

            Capsule::statement("
                UPDATE system_command_logs
                SET created_at = started_at
                WHERE created_at IS NULL AND started_at IS NOT NULL
            ");

            Capsule::statement("
                UPDATE system_command_logs
                SET success = CASE WHEN exit_code = 0 THEN 1 ELSE 0 END
                WHERE exit_code IS NOT NULL
            ");

            echo "✓ Migrated data to new columns in system_command_logs\n";
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        if ($schema->hasTable('system_command_logs')) {
            $schema->table('system_command_logs', function (Blueprint $table) use ($schema) {
                if ($schema->hasColumn('system_command_logs', 'admin_id')) {
                    $table->dropIndex('idx_admin_id');
                    $table->dropColumn('admin_id');
                }
                if ($schema->hasColumn('system_command_logs', 'description')) {
                    $table->dropColumn('description');
                }
                if ($schema->hasColumn('system_command_logs', 'execution_time')) {
                    $table->dropColumn('execution_time');
                }
                if ($schema->hasColumn('system_command_logs', 'success')) {
                    $table->dropColumn('success');
                }
                if ($schema->hasColumn('system_command_logs', 'ip_address')) {
                    $table->dropColumn('ip_address');
                }
                if ($schema->hasColumn('system_command_logs', 'user_agent')) {
                    $table->dropColumn('user_agent');
                }
                if ($schema->hasColumn('system_command_logs', 'created_at')) {
                    $table->dropColumn('created_at');
                }
            });
        }
    }
};
