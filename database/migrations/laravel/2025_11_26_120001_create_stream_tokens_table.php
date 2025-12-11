<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class extends Migration
{
    /**
     * Run the migrations - Create stream_tokens table
     *
     * Enterprise-Grade Streaming Security:
     * Provides secure, time-limited token-based authentication for streaming access
     */
    public function up(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable('stream_tokens')) {
            $schema->create('stream_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('token', 64)->unique()->comment('Cryptographically secure token (64-char hex)');
                $table->unsignedInteger('stream_id')->comment('Stream being accessed');
                $table->enum('type', ['staff', 'subscriber', 'api'])->default('subscriber')->comment('Token type');
                $table->unsignedInteger('user_id')->nullable()->comment('Admin ID (staff) or Subscriber ID (subscriber)');
                $table->string('ip_address', 45)->nullable()->comment('Bound IP address for security');
                $table->text('user_agent')->nullable()->comment('Client user agent');
                $table->json('metadata')->nullable()->comment('Additional token metadata');
                $table->dateTime('created_at')->useCurrent();
                $table->dateTime('expires_at')->comment('Token expiration time');
                $table->dateTime('last_used_at')->nullable()->comment('Last usage timestamp');
                $table->unsignedInteger('use_count')->default(0)->comment('Number of times token used');
                $table->boolean('is_revoked')->default(false)->comment('Token revocation status');

                $table->index('token', 'idx_token');
                $table->index('stream_id', 'idx_stream_id');
                $table->index('user_id', 'idx_user_id');
                $table->index('type', 'idx_type');
                $table->index('expires_at', 'idx_expires_at');
                $table->index('is_revoked', 'idx_is_revoked');
                $table->index(['is_revoked', 'expires_at'], 'idx_valid_tokens');

                $table->foreign('stream_id')->references('id')->on('streams')->onDelete('cascade');
            });

            Capsule::statement("ALTER TABLE stream_tokens COMMENT = 'Secure streaming access tokens for staff and subscribers'");
            echo "✓ Created stream_tokens table\n";
        }
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Capsule::schema()->dropIfExists('stream_tokens');
    }
};
