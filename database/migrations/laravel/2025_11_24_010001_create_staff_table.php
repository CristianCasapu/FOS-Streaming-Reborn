<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create staff table
     *
     * Staff members (administrators/operators) for the platform
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('staff')) {
            $schema->create('staff', function (Blueprint $table) {
                $table->increments('id');
                $table->string('username', 100)->unique();
                $table->string('email', 255)->unique()->nullable();
                $table->string('password', 255)->comment('MD5 hashed password');

                // RBAC (Role-Based Access Control)
                $table->enum('role', ['admin', 'supervisor', 'support'])->default('support')
                    ->comment('admin=full access, supervisor=most access, support=read-only');
                $table->json('permissions')->nullable()
                    ->comment('JSON array of specific permissions');
                $table->json('restrictions')->nullable()
                    ->comment('IP restrictions, time restrictions');

                // Profile
                $table->string('full_name', 255)->nullable();
                $table->string('phone', 50)->nullable();

                // Status
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
                $table->timestamp('last_login')->nullable();
                $table->unsignedInteger('login_count')->default(0);
                $table->string('last_ip', 45)->nullable();

                // Password Management
                $table->timestamp('password_changed_at')->nullable();
                $table->boolean('force_password_change')->default(false);

                // Two-Factor Authentication
                $table->boolean('tfa_enabled')->default(false);
                $table->string('tfa_secret', 255)->nullable();
                $table->timestamp('tfa_enabled_at')->nullable();
                $table->boolean('two_factor_enabled')->default(false);
                $table->string('two_factor_secret', 255)->nullable();

                // API Access
                $table->string('api_token', 80)->nullable()->unique();
                $table->timestamp('api_token_expires_at')->nullable();

                // Metadata
                $table->text('notes')->nullable()->comment('Staff notes');
                $table->unsignedInteger('created_by')->nullable()
                    ->comment('Staff ID who created this account');

                $table->timestamps();
                $table->softDeletes();

                // Indexes
                $table->index('username');
                $table->index('email');
                $table->index('status');
                $table->index('role');
                $table->index('last_login');
                $table->index(['role', 'status']);
            });

            Capsule::statement("ALTER TABLE staff COMMENT = 'Staff members with RBAC'");
            echo "✓ Staff table created\n";
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('staff');
    }
};
