<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Add subscription-level access controls
     *
     * Adds enterprise-grade security features:
     * 1. access_token: Subscription-level access token (auto-generated on activation)
     * 2. allowed_isps: JSON array of allowed ISPs (empty/null = no restriction)
     * 3. allowed_ips: JSON array of allowed IPs (empty/null = no restriction)
     * 4. token_expires_at: When the access token should be regenerated
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        // Add columns to subscriptions table
        if ($schema->hasTable('subscriptions')) {
            if (!$schema->hasColumn('subscriptions', 'access_token')) {
                $schema->table('subscriptions', function (Blueprint $table) {
                    $table->string('access_token', 128)->nullable();
                    $table->index('access_token', 'idx_subscriptions_access_token');
                });
                echo "✓ Added access_token column to subscriptions\n";
            }

            if (!$schema->hasColumn('subscriptions', 'token_expires_at')) {
                $schema->table('subscriptions', function (Blueprint $table) {
                    $table->dateTime('token_expires_at')->nullable();
                });
                echo "✓ Added token_expires_at column to subscriptions\n";
            }

            if (!$schema->hasColumn('subscriptions', 'allowed_isps')) {
                $schema->table('subscriptions', function (Blueprint $table) {
                    $table->json('allowed_isps')->nullable();
                });
                echo "✓ Added allowed_isps column to subscriptions\n";
            }

            if (!$schema->hasColumn('subscriptions', 'allowed_ips')) {
                $schema->table('subscriptions', function (Blueprint $table) {
                    $table->json('allowed_ips')->nullable();
                });
                echo "✓ Added allowed_ips column to subscriptions\n";
            }
        }

        // Create active_connections table
        if (!$schema->hasTable('active_connections')) {
            $schema->create('active_connections', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('subscription_id');
                $table->unsignedInteger('subscriber_id');
                $table->unsignedInteger('stream_id')->nullable();
                $table->string('session_id', 64);
                $table->string('ip_address', 45);
                $table->text('user_agent')->nullable();
                $table->string('isp', 255)->nullable();
                $table->string('device_fingerprint', 64)->nullable();
                $table->dateTime('connected_at')->useCurrent();
                $table->dateTime('last_heartbeat')->useCurrent();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('bandwidth')->default(0);

                $table->index('subscription_id', 'idx_active_connections_subscription');
                $table->index('subscriber_id', 'idx_active_connections_subscriber');
                $table->index('session_id', 'idx_active_connections_session');
                $table->index('is_active', 'idx_active_connections_active');
                $table->index('last_heartbeat', 'idx_active_connections_heartbeat');
            });

            Capsule::statement("ALTER TABLE active_connections COMMENT = 'Track concurrent streaming connections'");
            echo "✓ Created active_connections table\n";
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        $schema = Capsule::schema();

        // Drop active_connections table
        $schema->dropIfExists('active_connections');

        // Remove columns from subscriptions
        if ($schema->hasTable('subscriptions')) {
            $schema->table('subscriptions', function (Blueprint $table) use ($schema) {
                if ($schema->hasColumn('subscriptions', 'access_token')) {
                    $table->dropIndex('idx_subscriptions_access_token');
                    $table->dropColumn('access_token');
                }
                if ($schema->hasColumn('subscriptions', 'token_expires_at')) {
                    $table->dropColumn('token_expires_at');
                }
                if ($schema->hasColumn('subscriptions', 'allowed_isps')) {
                    $table->dropColumn('allowed_isps');
                }
                if ($schema->hasColumn('subscriptions', 'allowed_ips')) {
                    $table->dropColumn('allowed_ips');
                }
            });
        }
    }
};
