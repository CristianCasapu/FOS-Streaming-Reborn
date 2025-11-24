<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations - Phase 1 Foundation (Role-Based Access Control)
     * NOTE: This migration is for upgrading existing deployments only.
     * Fresh deployments already have all these columns in the staff table.
     */
    public function up(): void
    {
        $schema = \Illuminate\Database\Capsule\Manager::schema();

        // Check if staff table exists (fresh deployment) or admins table (old deployment)
        $tableName = $schema->hasTable('staff') ? 'staff' : 'admins';

        if (!$schema->hasTable($tableName)) {
            // Table doesn't exist - skip this migration (fresh deployment handles it)
            echo "⊘ Skipping RBAC migration - table created fresh with all columns\n";
            return;
        }

        // Only add columns if they don't already exist
        $schema->table($tableName, function (Blueprint $table) use ($schema, $tableName) {
            // Add role column for RBAC
            if (!$schema->hasColumn($tableName, 'role')) {
                $table->enum('role', ['admin', 'supervisor', 'support'])->default('support')->after('password');
            }

            // Permissions and restrictions
            if (!$schema->hasColumn($tableName, 'permissions')) {
                $table->json('permissions')->nullable()->after('role')->comment('Granular permissions array');
            }
            if (!$schema->hasColumn($tableName, 'restrictions')) {
                $table->json('restrictions')->nullable()->after('permissions')->comment('IP restrictions, time restrictions');
            }

            // Two-factor authentication
            if (!$schema->hasColumn($tableName, 'tfa_enabled')) {
                $table->boolean('tfa_enabled')->default(false)->after('restrictions');
            }
            if (!$schema->hasColumn($tableName, 'tfa_secret')) {
                $table->string('tfa_secret', 255)->nullable()->after('tfa_enabled');
            }
            if (!$schema->hasColumn($tableName, 'tfa_enabled_at')) {
                $table->timestamp('tfa_enabled_at')->nullable()->after('tfa_secret');
            }

            // Session and security
            if (!$schema->hasColumn($tableName, 'last_ip')) {
                $table->string('last_ip', 45)->nullable()->after('tfa_enabled_at');
            }
            if (!$schema->hasColumn($tableName, 'last_login')) {
                $table->timestamp('last_login')->nullable()->after('last_ip');
            }
            if (!$schema->hasColumn($tableName, 'login_count')) {
                $table->unsignedInteger('login_count')->default(0)->after('last_login');
            }
            if (!$schema->hasColumn($tableName, 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('login_count');
            }
            if (!$schema->hasColumn($tableName, 'force_password_change')) {
                $table->boolean('force_password_change')->default(false)->after('password_changed_at');
            }

            // Status tracking
            if (!$schema->hasColumn($tableName, 'status')) {
                $table->string('status', 20)->default('active')->after('force_password_change')->comment('active, inactive, suspended');
            }
            if (!$schema->hasColumn($tableName, 'notes')) {
                $table->text('notes')->nullable()->after('status')->comment('Admin notes');
            }

            if (!$schema->hasColumn($tableName, 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Create staff_activity_logs table for RBAC audit
        if (!$schema->hasTable('staff_activity_logs')) {
            $schema->create('staff_activity_logs', function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->unsignedInteger('staff_id');
                $table->string('action', 255);
                $table->string('entity_type', 50)->nullable();
                $table->unsignedInteger('entity_id')->nullable();
                $table->json('changes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('staff_id')->references('id')->on($tableName)->onDelete('cascade');
                $table->index(['staff_id', 'created_at']);
                $table->index('action');
                $table->index(['entity_type', 'entity_id']);
            });
            echo "✓ staff_activity_logs table created\n";
        }

        // Create staff_sessions table
        if (!$schema->hasTable('staff_sessions')) {
            $schema->create('staff_sessions', function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->unsignedInteger('staff_id');
                $table->string('session_token', 64)->unique();
                $table->string('ip_address', 45);
                $table->text('user_agent');
                $table->timestamp('last_activity');
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->foreign('staff_id')->references('id')->on($tableName)->onDelete('cascade');
                $table->index(['staff_id', 'expires_at']);
                $table->index('session_token');
            });
            echo "✓ staff_sessions table created\n";
        }

        if ($schema->hasTable($tableName)) {
            \Illuminate\Database\Capsule\Manager::statement("ALTER TABLE {$tableName} COMMENT = 'Staff accounts with role-based access control'");
        }
        if ($schema->hasTable('staff_activity_logs')) {
            \Illuminate\Database\Capsule\Manager::statement("ALTER TABLE staff_activity_logs COMMENT = 'Activity logs for staff actions'");
        }
        if ($schema->hasTable('staff_sessions')) {
            \Illuminate\Database\Capsule\Manager::statement("ALTER TABLE staff_sessions COMMENT = 'Staff session tracking for security'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('staff_sessions');
        \Illuminate\Database\Capsule\Manager::schema()->dropIfExists('staff_activity_logs');

        \Illuminate\Database\Capsule\Manager::schema()->table('admins', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropIndex(['admins_role_index']);
            $table->dropIndex(['admins_status_index']);
            $table->dropIndex(['admins_role_status_index']);

            $table->dropColumn([
                'role',
                'permissions',
                'restrictions',
                'tfa_enabled',
                'tfa_secret',
                'tfa_enabled_at',
                'last_ip',
                'last_login',
                'login_count',
                'password_changed_at',
                'force_password_change',
                'status',
                'notes'
            ]);
        });
    }
};
